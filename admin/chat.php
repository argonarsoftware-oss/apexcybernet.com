<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Admin-only: must be logged in via admin session
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . base_url('admin/'));
    exit;
}

// ── AJAX: delete message ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false]); exit; }
    try {
        $pdo->prepare("DELETE FROM cafe_comments WHERE id = ?")->execute([$id]);
        echo json_encode(['ok'=>true]);
    } catch (Exception $e) { echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: clear all messages ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'clear_all' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $pdo->exec("DELETE FROM cafe_comments");
        echo json_encode(['ok'=>true]);
    } catch (Exception $e) { echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
    exit;
}

// ── AJAX: fetch latest messages ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'messages') {
    header('Content-Type: application/json');
    $after = max(0, (int)($_GET['after'] ?? 0));
    try {
        $st = $pdo->prepare("SELECT id, account_id, display_name, message, created_at
            FROM cafe_comments WHERE id > ? ORDER BY id ASC LIMIT 100");
        $st->execute([$after]);
        echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) { echo json_encode([]); }
    exit;
}

// Load initial messages
$messages = [];
$last_id  = 0;
try {
    $messages = $pdo->query("SELECT id, account_id, display_name, message, created_at
        FROM cafe_comments ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
    $messages = array_reverse($messages);
    $last_id  = !empty($messages) ? (int)end($messages)['id'] : 0;
} catch (Exception $e) {}

$total = count($messages);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chat Admin — Argonar</title>
<link rel="stylesheet" href="<?= base_url('app.css') ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body { background: #0f0f13; color: #e5e7eb; font-family: 'Inter', sans-serif; margin: 0; padding: 1.5rem; }
.page-header {
    display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;
}
.page-header h1 { font-size: 1.3rem; font-weight: 800; margin: 0; }
.live-pill {
    background: #e53e3e; color: #fff;
    font-size: 0.62rem; font-weight: 800; letter-spacing: 0.06em;
    padding: 0.15rem 0.5rem; border-radius: 4px;
}
.btn {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.4rem 0.9rem; border-radius: 8px; border: none;
    font-size: 0.8rem; font-weight: 700; cursor: pointer;
    text-decoration: none; transition: opacity 0.15s;
}
.btn:hover { opacity: 0.82; }
.btn-danger  { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
.btn-ghost   { background: rgba(255,255,255,0.05); color: #9ca3af; border: 1px solid rgba(255,255,255,0.1); }
.btn-primary { background: var(--accent,#7c3aed); color: #fff; }
.stats-row { display: flex; gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
.stat-card {
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px; padding: 0.7rem 1.2rem; min-width: 110px;
}
.stat-num  { font-size: 1.5rem; font-weight: 800; color: #a78bfa; }
.stat-lbl  { font-size: 0.7rem; color: #6b7280; font-weight: 600; margin-top: 1px; }
.chat-wrap {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px; overflow: hidden; max-width: 860px;
}
.chat-toolbar {
    display: flex; align-items: center; gap: 0.6rem;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    flex-wrap: wrap;
}
.chat-toolbar span { font-size: 0.78rem; color: #6b7280; margin-right: auto; }
.msg-list { max-height: 600px; overflow-y: auto; }
.msg-list::-webkit-scrollbar { width: 4px; }
.msg-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }
.msg-row {
    display: flex; align-items: flex-start; gap: 0.75rem;
    padding: 0.6rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    transition: background 0.1s;
}
.msg-row:hover { background: rgba(255,255,255,0.03); }
.msg-row.deleted { opacity: 0.3; transition: opacity 0.3s; pointer-events: none; }
.msg-name { font-size: 0.75rem; font-weight: 700; flex-shrink: 0; min-width: 110px; }
.msg-text { font-size: 0.8rem; color: #d1d5db; flex: 1; word-break: break-word; line-height: 1.45; }
.msg-time { font-size: 0.65rem; color: #4b5563; flex-shrink: 0; white-space: nowrap; }
.msg-del  {
    flex-shrink: 0; background: none; border: none; color: #4b5563;
    cursor: pointer; font-size: 0.9rem; padding: 0 0.25rem;
    transition: color 0.15s;
}
.msg-del:hover { color: #f87171; }
.empty-state { text-align: center; padding: 3rem 1rem; color: #4b5563; font-size: 0.85rem; }
</style>
</head>
<body>

<div class="page-header">
    <a href="<?= base_url('admin/') ?>" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Admin</a>
    <h1><i class="bi bi-chat-dots-fill" style="color:#a78bfa;"></i> Live Chat</h1>
    <span class="live-pill">LIVE</span>
</div>

<div class="stats-row">
    <div class="stat-card">
        <div class="stat-num" id="statTotal"><?= $total ?></div>
        <div class="stat-lbl">Total Messages</div>
    </div>
</div>

<div class="chat-wrap">
    <div class="chat-toolbar">
        <span id="toolbarInfo"><?= $total ?> messages loaded</span>
        <button class="btn btn-ghost" onclick="scrollBottom()"><i class="bi bi-arrow-down-circle"></i> Jump to latest</button>
        <button class="btn btn-danger" onclick="confirmClearAll()"><i class="bi bi-trash3-fill"></i> Clear All</button>
    </div>
    <div class="msg-list" id="msgList">
        <?php if (empty($messages)): ?>
        <div class="empty-state"><i class="bi bi-chat-square-dots" style="font-size:2rem; display:block; margin-bottom:0.5rem;"></i>No messages yet.</div>
        <?php else: ?>
        <?php foreach ($messages as $m):
            $hue = (crc32($m['display_name']) & 0x7FFFFFFF) % 360;
            $col = "hsl({$hue},65%,65%)";
        ?>
        <div class="msg-row" id="msg-<?= $m['id'] ?>">
            <span class="msg-name" style="color:<?= $col ?>;"><?= htmlspecialchars($m['display_name']) ?></span>
            <span class="msg-text"><?= htmlspecialchars($m['message']) ?></span>
            <span class="msg-time"><?= date('M d H:i', strtotime($m['created_at'])) ?></span>
            <button class="msg-del" title="Delete" onclick="deleteMsg(<?= $m['id'] ?>, this)">
                <i class="bi bi-x-circle"></i>
            </button>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
let lastId = <?= $last_id ?>;
let msgCount = <?= $total ?>;

function nameColor(name) {
    let h = 0;
    for (let i = 0; i < name.length; i++) h = name.charCodeAt(i) + ((h << 5) - h);
    return `hsl(${Math.abs(h) % 360},65%,65%)`;
}
function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtDate(dt) {
    const d = new Date(dt.replace(' ','T'));
    return d.toLocaleDateString('en-US',{month:'short',day:'numeric'}) + ' ' +
           String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
}

function scrollBottom() {
    const list = document.getElementById('msgList');
    list.scrollTop = list.scrollHeight;
}
scrollBottom();

function updateInfo() {
    document.getElementById('toolbarInfo').textContent = msgCount + ' messages';
    document.getElementById('statTotal').textContent = msgCount;
}

// ── Delete single message ──
function deleteMsg(id, btn) {
    const row = document.getElementById('msg-' + id);
    const fd = new FormData();
    fd.append('id', id);
    fetch('chat.php?ajax=delete', { method:'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                row.classList.add('deleted');
                setTimeout(() => row.remove(), 320);
                msgCount = Math.max(0, msgCount - 1);
                updateInfo();
            }
        }).catch(() => {});
}

// ── Clear all ──
function confirmClearAll() {
    if (!confirm('Delete ALL chat messages? This cannot be undone.')) return;
    fetch('chat.php?ajax=clear_all', { method:'POST' })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                document.getElementById('msgList').innerHTML =
                    '<div class="empty-state"><i class="bi bi-chat-square-dots" style="font-size:2rem; display:block; margin-bottom:0.5rem;"></i>No messages yet.</div>';
                msgCount = 0; lastId = 0; updateInfo();
            }
        }).catch(() => {});
}

// ── Poll for new messages ──
function poll() {
    fetch(`chat.php?ajax=messages&after=${lastId}`)
        .then(r => r.json())
        .then(msgs => {
            if (!msgs.length) return;
            const list = document.getElementById('msgList');
            const atBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 80;
            const empty = list.querySelector('.empty-state');
            if (empty) empty.remove();
            msgs.forEach(m => {
                lastId = Math.max(lastId, parseInt(m.id));
                msgCount++;
                const row = document.createElement('div');
                row.className = 'msg-row';
                row.id = 'msg-' + m.id;
                row.innerHTML =
                    `<span class="msg-name" style="color:${nameColor(m.display_name)};">${esc(m.display_name)}</span>` +
                    `<span class="msg-text">${esc(m.message)}</span>` +
                    `<span class="msg-time">${fmtDate(m.created_at)}</span>` +
                    `<button class="msg-del" title="Delete" onclick="deleteMsg(${m.id}, this)"><i class="bi bi-x-circle"></i></button>`;
                list.appendChild(row);
            });
            updateInfo();
            if (atBottom) scrollBottom();
        }).catch(() => {});
}
setInterval(poll, 3000);
</script>
</body>
</html>
