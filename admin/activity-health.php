<?php
/**
 * activity-health.php — VPS System Health
 *
 * Queries /proc + PHP-native calls to show CPU, memory, disk, uptime,
 * load averages, and service status. Works on the Linode Ubuntu VPS;
 * gracefully shows "n/a" on Windows/XAMPP local dev.
 */
$active_site = 'health';
$page_file   = 'activity-health.php';
require_once __DIR__ . '/../includes/db.php';
$apexcybernet_pdo = $pdo;
require_once __DIR__ . '/omni/auth.php';

// ── AJAX: live refresh endpoint (returns JSON) ──
$is_ajax = isset($_GET['ajax']);

// ── Helpers ──
function hp_bytes(int $b, int $prec = 2): string {
    $u = ['B','KB','MB','GB','TB'];
    $i = 0;
    while ($b >= 1024 && $i < count($u) - 1) { $b /= 1024; $i++; }
    return number_format($b, $prec) . ' ' . $u[$i];
}

function hp_read_proc(string $path): ?string {
    if (!is_readable($path)) return null;
    $s = @file_get_contents($path);
    return $s === false ? null : $s;
}

function hp_cpu_sample(): ?array {
    $s = hp_read_proc('/proc/stat');
    if (!$s) return null;
    // First line: cpu  user nice system idle iowait irq softirq steal ...
    $line = strtok($s, "\n");
    $parts = preg_split('/\s+/', trim($line));
    if (count($parts) < 5 || $parts[0] !== 'cpu') return null;
    array_shift($parts);
    $vals = array_map('intval', $parts);
    $idle  = ($vals[3] ?? 0) + ($vals[4] ?? 0); // idle + iowait
    $total = array_sum($vals);
    return ['idle' => $idle, 'total' => $total];
}

function hp_cpu_percent(): ?float {
    $a = hp_cpu_sample(); if (!$a) return null;
    usleep(180000); // 180ms sample window
    $b = hp_cpu_sample(); if (!$b) return null;
    $dt = $b['total'] - $a['total'];
    $di = $b['idle']  - $a['idle'];
    if ($dt <= 0) return null;
    return round((1 - $di / $dt) * 100, 1);
}

function hp_meminfo(): array {
    $s = hp_read_proc('/proc/meminfo');
    $out = ['total'=>0,'free'=>0,'available'=>0,'buffers'=>0,'cached'=>0,'swap_total'=>0,'swap_free'=>0];
    if (!$s) return $out;
    foreach (explode("\n", $s) as $line) {
        if (preg_match('/^MemTotal:\s+(\d+)\s+kB/i',     $line, $m)) $out['total']      = (int)$m[1] * 1024;
        if (preg_match('/^MemFree:\s+(\d+)\s+kB/i',      $line, $m)) $out['free']       = (int)$m[1] * 1024;
        if (preg_match('/^MemAvailable:\s+(\d+)\s+kB/i', $line, $m)) $out['available']  = (int)$m[1] * 1024;
        if (preg_match('/^Buffers:\s+(\d+)\s+kB/i',      $line, $m)) $out['buffers']    = (int)$m[1] * 1024;
        if (preg_match('/^Cached:\s+(\d+)\s+kB/i',       $line, $m)) $out['cached']     = (int)$m[1] * 1024;
        if (preg_match('/^SwapTotal:\s+(\d+)\s+kB/i',    $line, $m)) $out['swap_total'] = (int)$m[1] * 1024;
        if (preg_match('/^SwapFree:\s+(\d+)\s+kB/i',     $line, $m)) $out['swap_free']  = (int)$m[1] * 1024;
    }
    return $out;
}

function hp_uptime(): ?array {
    $s = hp_read_proc('/proc/uptime');
    if (!$s) return null;
    $parts = explode(' ', trim($s));
    $secs = (int)floatval($parts[0] ?? 0);
    $days  = intdiv($secs, 86400);
    $hours = intdiv($secs % 86400, 3600);
    $mins  = intdiv($secs % 3600, 60);
    $pretty = ($days ? "{$days}d " : '') . ($hours ? "{$hours}h " : '') . "{$mins}m";
    return ['seconds' => $secs, 'pretty' => trim($pretty)];
}

function hp_disk(string $path = '/'): array {
    // Fallback for Windows dev
    $check = PHP_OS_FAMILY === 'Windows' ? 'C:\\' : $path;
    $total = @disk_total_space($check);
    $free  = @disk_free_space($check);
    if ($total === false || $free === false) return ['total'=>0,'used'=>0,'free'=>0,'percent'=>0];
    $used = $total - $free;
    return [
        'total'   => (int)$total,
        'used'    => (int)$used,
        'free'    => (int)$free,
        'percent' => $total > 0 ? round($used / $total * 100, 1) : 0,
    ];
}

function hp_load(): ?array {
    if (function_exists('sys_getloadavg')) {
        $l = sys_getloadavg();
        return ['1m' => $l[0] ?? null, '5m' => $l[1] ?? null, '15m' => $l[2] ?? null];
    }
    $s = hp_read_proc('/proc/loadavg');
    if (!$s) return null;
    $p = preg_split('/\s+/', trim($s));
    return ['1m' => (float)($p[0] ?? 0), '5m' => (float)($p[1] ?? 0), '15m' => (float)($p[2] ?? 0)];
}

function hp_cpu_cores(): int {
    $s = hp_read_proc('/proc/cpuinfo');
    if (!$s) return (int)(getenv('NUMBER_OF_PROCESSORS') ?: 1);
    preg_match_all('/^processor\s*:/mi', $s, $m);
    return count($m[0]) ?: 1;
}

function hp_port_open(string $host, int $port, float $timeout = 1.0): bool {
    $err_no = 0; $err_msg = '';
    $fp = @fsockopen($host, $port, $err_no, $err_msg, $timeout);
    if ($fp) { fclose($fp); return true; }
    return false;
}

function hp_os_release(): string {
    if (PHP_OS_FAMILY === 'Windows') return PHP_OS . ' ' . php_uname('r');
    $s = hp_read_proc('/etc/os-release');
    if ($s && preg_match('/^PRETTY_NAME="([^"]+)"/m', $s, $m)) return $m[1];
    return PHP_OS . ' ' . php_uname('r');
}

function hp_db_ping(PDO $pdo): array {
    $t = microtime(true);
    try {
        $pdo->query('SELECT 1')->fetch();
        return ['ok' => true, 'ms' => round((microtime(true) - $t) * 1000, 1)];
    } catch (Exception $e) {
        return ['ok' => false, 'ms' => null, 'error' => $e->getMessage()];
    }
}

// ── Collect metrics ──
$cpu_pct  = hp_cpu_percent();
$cpu_cores = hp_cpu_cores();
$mem      = hp_meminfo();
$disk     = hp_disk('/');
$load     = hp_load();
$uptime   = hp_uptime();
$os       = hp_os_release();
$db       = hp_db_ping($pdo);

$services = [
    ['name' => 'MySQL',              'host' => '127.0.0.1', 'port' => 3306, 'note' => 'Database'],
    ['name' => 'Apex Cybernet voice (SSE)','host' => '127.0.0.1', 'port' => 3001, 'note' => 'WebRTC signaling'],
    ['name' => 'coturn (TURN)',      'host' => '127.0.0.1', 'port' => 3478, 'note' => 'Voice relay'],
];
foreach ($services as &$s) {
    $s['open'] = hp_port_open($s['host'], $s['port'], 0.8);
}
unset($s);

if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode(compact('cpu_pct','cpu_cores','mem','disk','load','uptime','os','db','services'));
    exit;
}

// ── Sidebar stats (required by omni/sidebar.php) ──
$sidebar_stats = ['apexcybernet'=>['sessions'=>0,'live'=>0],'ocpd'=>['sessions'=>0,'live'=>0],'loan'=>['sessions'=>0,'live'=>0],'alrisha'=>['sessions'=>0,'live'=>0]];
try {
    $rs = $apexcybernet_pdo->query("SELECT CASE WHEN site IS NULL OR site='' THEN 'apexcybernet' ELSE site END s, COUNT(DISTINCT session_id) n FROM activity_logs WHERE created_at >= CURDATE() GROUP BY s")->fetchAll();
    foreach ($rs as $r) if (isset($sidebar_stats[$r['s']])) $sidebar_stats[$r['s']]['sessions'] = (int)$r['n'];
    $rs = $apexcybernet_pdo->query("SELECT CASE WHEN site IS NULL OR site='' THEN 'apexcybernet' ELSE site END s, COUNT(DISTINCT session_id) n FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) GROUP BY s")->fetchAll();
    foreach ($rs as $r) if (isset($sidebar_stats[$r['s']])) $sidebar_stats[$r['s']]['live'] = (int)$r['n'];
} catch (Exception $e) {}
$date_range = 'today';

// Helper: pick a color for a percent bar
function hp_color(float $pct): string {
    if ($pct >= 90) return '#ef4444';
    if ($pct >= 75) return '#fbbf24';
    if ($pct >= 50) return '#60a5fa';
    return '#34d399';
}
$cpu_color  = hp_color($cpu_pct ?? 0);
$mem_used   = $mem['total'] > 0 ? $mem['total'] - $mem['available'] : 0;
$mem_pct    = $mem['total'] > 0 ? round($mem_used / $mem['total'] * 100, 1) : 0;
$mem_color  = hp_color($mem_pct);
$disk_color = hp_color($disk['percent']);
$load_per_core = ($load && $cpu_cores > 0) ? round(($load['1m'] ?? 0) / $cpu_cores * 100, 1) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Health — Omniscient</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<?php require __DIR__ . '/omni/css.php'; ?>
<style>
.hp-wrap { max-width: 1100px; margin: 0 auto; padding: 1.5rem; }
.hp-hero { display: flex; align-items: baseline; gap: 0.75rem; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.08); }
.hp-hero h1 { font-size: 1.4rem; font-weight: 900; color: #e5e7eb; margin: 0; display: flex; align-items: center; gap: 0.5rem; letter-spacing: -0.5px; }
.hp-hero h1 i { color: #60a5fa; }
.hp-sub { font-size: 0.78rem; color: #6b7280; margin-left: auto; font-style: italic; }
.hp-refresh { display: inline-flex; align-items: center; gap: 0.35rem; background: rgba(96,165,250,0.1); border: 1px solid rgba(96,165,250,0.3); color: #93c5fd; padding: 0.3rem 0.7rem; border-radius: 6px; font-size: 0.7rem; font-weight: 700; cursor: pointer; font-family: inherit; margin-left: 0.5rem; }
.hp-refresh:hover { background: rgba(96,165,250,0.2); }
.hp-refresh .dot { width: 6px; height: 6px; border-radius: 50%; background: #60a5fa; animation: hpPulse 1.4s ease-in-out infinite; }
@keyframes hpPulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(1.3); } }

.hp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 0.85rem; margin-bottom: 1rem; }

.hp-card { background: #131318; border: 1px solid rgba(255,255,255,0.07); border-radius: 12px; padding: 1.1rem 1.25rem; }
.hp-card-head { display: flex; align-items: center; gap: 0.45rem; font-size: 0.68rem; font-weight: 800; color: #9ca3af; letter-spacing: 1.2px; text-transform: uppercase; margin-bottom: 0.5rem; }
.hp-card-head i { font-size: 0.9rem; }
.hp-val { font-size: 1.8rem; font-weight: 900; letter-spacing: -0.8px; line-height: 1; margin-bottom: 0.25rem; }
.hp-sub-val { font-size: 0.72rem; color: #9ca3af; line-height: 1.45; }
.hp-bar { margin-top: 0.7rem; height: 6px; background: rgba(255,255,255,0.05); border-radius: 3px; overflow: hidden; }
.hp-bar-fill { height: 100%; border-radius: 3px; transition: width 0.4s ease; }
.hp-bar-meta { display: flex; justify-content: space-between; margin-top: 0.35rem; font-size: 0.65rem; color: #6b7280; font-weight: 700; }

.hp-services { background: #131318; border: 1px solid rgba(255,255,255,0.07); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1rem; }
.hp-services-h { font-size: 0.68rem; font-weight: 800; color: #9ca3af; letter-spacing: 1.2px; text-transform: uppercase; margin-bottom: 0.55rem; display: flex; align-items: center; gap: 0.45rem; }
.hp-services-h i { color: #a78bfa; }
.hp-svc { display: flex; align-items: center; gap: 0.55rem; padding: 0.45rem 0.6rem; background: rgba(255,255,255,0.02); border-radius: 6px; border-left: 3px solid #374151; margin-bottom: 3px; }
.hp-svc.up   { border-left-color: #34d399; }
.hp-svc.down { border-left-color: #ef4444; }
.hp-svc-dot { width: 8px; height: 8px; border-radius: 50%; background: #374151; flex-shrink: 0; }
.hp-svc-dot.up   { background: #34d399; box-shadow: 0 0 6px rgba(52,211,153,0.5); }
.hp-svc-dot.down { background: #ef4444; }
.hp-svc-name { font-size: 0.78rem; font-weight: 700; color: #e5e7eb; }
.hp-svc-note { font-size: 0.66rem; color: #6b7280; margin-left: auto; }
.hp-svc-port { font-family: 'SF Mono', Consolas, monospace; font-size: 0.66rem; color: #9ca3af; background: rgba(255,255,255,0.04); padding: 0.1rem 0.4rem; border-radius: 4px; }

.hp-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.85rem; }
.hp-info { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 8px; padding: 0.65rem 0.85rem; }
.hp-info-t { font-size: 0.62rem; color: #6b7280; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase; margin-bottom: 0.2rem; }
.hp-info-v { font-size: 0.82rem; color: #e5e7eb; font-weight: 700; word-break: break-word; }

.hp-na { color: #6b7280; font-style: italic; font-size: 0.78rem; }
</style>
</head>
<body>
<div class="omni-layout">
<?php require __DIR__ . '/omni/sidebar.php'; ?>
<div class="omni-main">
<div class="hp-wrap">

    <div class="hp-hero">
        <h1><i class="bi bi-heart-pulse-fill"></i> System Health</h1>
        <span class="hp-sub">Live VPS metrics · polls every 10s</span>
        <button class="hp-refresh" onclick="hpRefresh()"><span class="dot"></span> Live</button>
    </div>

    <!-- ── Headline metrics ── -->
    <div class="hp-grid" id="hpGrid">
        <!-- CPU -->
        <div class="hp-card">
            <div class="hp-card-head"><i class="bi bi-cpu-fill"></i> CPU usage</div>
            <div class="hp-val" id="hpCpuVal" style="color:<?= $cpu_color ?>;"><?= $cpu_pct !== null ? $cpu_pct . '%' : '—' ?></div>
            <div class="hp-sub-val"><?= $cpu_cores ?> core<?= $cpu_cores > 1 ? 's' : '' ?>
                <?php if ($load): ?> · load <?= number_format($load['1m'], 2) ?>/<?= number_format($load['5m'], 2) ?>/<?= number_format($load['15m'], 2) ?><?php endif; ?>
            </div>
            <div class="hp-bar"><div class="hp-bar-fill" id="hpCpuBar" style="width:<?= $cpu_pct !== null ? $cpu_pct : 0 ?>%;background:<?= $cpu_color ?>;"></div></div>
        </div>
        <!-- Memory -->
        <div class="hp-card">
            <div class="hp-card-head"><i class="bi bi-memory"></i> Memory</div>
            <div class="hp-val" id="hpMemVal" style="color:<?= $mem_color ?>;"><?= $mem_pct ?>%</div>
            <div class="hp-sub-val" id="hpMemSub"><?= $mem['total'] ? hp_bytes($mem_used) . ' / ' . hp_bytes($mem['total']) : 'n/a' ?>
                <?php if ($mem['swap_total'] > 0): ?> · swap <?= hp_bytes($mem['swap_total'] - $mem['swap_free']) ?>/<?= hp_bytes($mem['swap_total']) ?><?php endif; ?>
            </div>
            <div class="hp-bar"><div class="hp-bar-fill" id="hpMemBar" style="width:<?= $mem_pct ?>%;background:<?= $mem_color ?>;"></div></div>
        </div>
        <!-- Disk -->
        <div class="hp-card">
            <div class="hp-card-head"><i class="bi bi-hdd-fill"></i> Storage</div>
            <div class="hp-val" id="hpDiskVal" style="color:<?= $disk_color ?>;"><?= $disk['percent'] ?>%</div>
            <div class="hp-sub-val" id="hpDiskSub"><?= hp_bytes($disk['used']) ?> / <?= hp_bytes($disk['total']) ?> · <?= hp_bytes($disk['free']) ?> free</div>
            <div class="hp-bar"><div class="hp-bar-fill" id="hpDiskBar" style="width:<?= $disk['percent'] ?>%;background:<?= $disk_color ?>;"></div></div>
        </div>
        <!-- Uptime -->
        <div class="hp-card">
            <div class="hp-card-head"><i class="bi bi-clock-history"></i> Uptime</div>
            <div class="hp-val" id="hpUptimeVal" style="color:#a78bfa;"><?= $uptime ? htmlspecialchars($uptime['pretty']) : '—' ?></div>
            <div class="hp-sub-val" id="hpUptimeSub"><?= $uptime ? 'since ' . date('M j, H:i', time() - $uptime['seconds']) : '<span class="hp-na">VPS uptime unavailable</span>' ?></div>
        </div>
    </div>

    <!-- ── Services ── -->
    <div class="hp-services">
        <div class="hp-services-h"><i class="bi bi-diagram-3-fill"></i> Service status</div>
        <div id="hpServices">
            <?php foreach ($services as $svc):
                $cls = $svc['open'] ? 'up' : 'down';
            ?>
            <div class="hp-svc <?= $cls ?>">
                <div class="hp-svc-dot <?= $cls ?>"></div>
                <div class="hp-svc-name"><?= htmlspecialchars($svc['name']) ?></div>
                <span class="hp-svc-port">:<?= (int)$svc['port'] ?></span>
                <span class="hp-svc-note"><?= htmlspecialchars($svc['note']) ?><?= $svc['open'] ? '' : ' · <span style="color:#f87171;">offline</span>' ?></span>
            </div>
            <?php endforeach; ?>
            <div class="hp-svc <?= $db['ok'] ? 'up' : 'down' ?>">
                <div class="hp-svc-dot <?= $db['ok'] ? 'up' : 'down' ?>"></div>
                <div class="hp-svc-name">Database ping</div>
                <span class="hp-svc-port"><?= $db['ok'] ? $db['ms'] . ' ms' : 'fail' ?></span>
                <span class="hp-svc-note"><?= $db['ok'] ? 'PDO roundtrip' : htmlspecialchars($db['error'] ?? '') ?></span>
            </div>
        </div>
    </div>

    <!-- ── Environment ── -->
    <div class="hp-services">
        <div class="hp-services-h"><i class="bi bi-info-circle-fill"></i> Environment</div>
        <div class="hp-info-grid">
            <div class="hp-info"><div class="hp-info-t">OS</div><div class="hp-info-v"><?= htmlspecialchars($os) ?></div></div>
            <div class="hp-info"><div class="hp-info-t">Hostname</div><div class="hp-info-v"><?= htmlspecialchars(gethostname() ?: 'n/a') ?></div></div>
            <div class="hp-info"><div class="hp-info-t">PHP</div><div class="hp-info-v"><?= PHP_VERSION ?> · <?= PHP_SAPI ?></div></div>
            <div class="hp-info"><div class="hp-info-t">Server software</div><div class="hp-info-v"><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'n/a') ?></div></div>
            <div class="hp-info"><div class="hp-info-t">memory_limit</div><div class="hp-info-v"><?= htmlspecialchars(ini_get('memory_limit') ?: 'n/a') ?></div></div>
            <div class="hp-info"><div class="hp-info-t">upload_max_filesize</div><div class="hp-info-v"><?= htmlspecialchars(ini_get('upload_max_filesize') ?: 'n/a') ?></div></div>
            <?php if ($load_per_core !== null): ?>
            <div class="hp-info"><div class="hp-info-t">Load per core (1m)</div><div class="hp-info-v" style="color:<?= hp_color($load_per_core) ?>;"><?= $load_per_core ?>%</div></div>
            <?php endif; ?>
            <div class="hp-info"><div class="hp-info-t">Server time</div><div class="hp-info-v"><?= date('Y-m-d H:i:s') ?></div></div>
        </div>
    </div>

</div><!-- /hp-wrap -->
</div><!-- /omni-main -->
</div><!-- /omni-layout -->

<script>
function hpColor(p) {
    if (p >= 90) return '#ef4444';
    if (p >= 75) return '#fbbf24';
    if (p >= 50) return '#60a5fa';
    return '#34d399';
}
function hpBytes(b) {
    if (!b) return '0 B';
    const u = ['B','KB','MB','GB','TB']; let i = 0;
    while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; }
    return b.toFixed(2) + ' ' + u[i];
}
function hpRender(d) {
    if (d.cpu_pct !== null) {
        const c = hpColor(d.cpu_pct);
        document.getElementById('hpCpuVal').textContent = d.cpu_pct + '%';
        document.getElementById('hpCpuVal').style.color = c;
        document.getElementById('hpCpuBar').style.width = d.cpu_pct + '%';
        document.getElementById('hpCpuBar').style.background = c;
    }
    if (d.mem && d.mem.total > 0) {
        const used = d.mem.total - d.mem.available;
        const pct = Math.round(used / d.mem.total * 1000) / 10;
        const c = hpColor(pct);
        document.getElementById('hpMemVal').textContent = pct + '%';
        document.getElementById('hpMemVal').style.color = c;
        document.getElementById('hpMemBar').style.width = pct + '%';
        document.getElementById('hpMemBar').style.background = c;
        document.getElementById('hpMemSub').textContent = hpBytes(used) + ' / ' + hpBytes(d.mem.total);
    }
    if (d.disk) {
        const c = hpColor(d.disk.percent);
        document.getElementById('hpDiskVal').textContent = d.disk.percent + '%';
        document.getElementById('hpDiskVal').style.color = c;
        document.getElementById('hpDiskBar').style.width = d.disk.percent + '%';
        document.getElementById('hpDiskBar').style.background = c;
        document.getElementById('hpDiskSub').textContent = hpBytes(d.disk.used) + ' / ' + hpBytes(d.disk.total) + ' · ' + hpBytes(d.disk.free) + ' free';
    }
    if (d.uptime) {
        document.getElementById('hpUptimeVal').textContent = d.uptime.pretty;
    }
}
async function hpRefresh() {
    try {
        const r = await fetch('<?= base_url('admin/activity-health.php') ?>?ajax=1', {cache: 'no-store'});
        const d = await r.json();
        hpRender(d);
    } catch (e) {}
}
setInterval(hpRefresh, 10000);
</script>
</body>
</html>
