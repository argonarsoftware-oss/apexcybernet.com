<?php require_once __DIR__ . '/includes/db.php'; $api_base = rtrim(base_url('api'), '/'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apex Cybernet POS Terminal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

    :root {
        --bg:        #090d09;
        --surface:   #101510;
        --card:      #141d14;
        --border:    #1c2b1c;
        --green:     #22c55e;
        --green-d:   #16a34a;
        --green-dim: rgba(34,197,94,0.12);
        --red:       #ef4444;
        --red-dim:   rgba(239,68,68,0.1);
        --amber:     #f59e0b;
        --text:      #e4e4e7;
        --muted:     #6b7280;
        --accent:    #22c55e;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: var(--bg);
        color: var(--text);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        overflow-x: hidden;
    }

    /* ── Top bar ── */
    .pos-topbar {
        background: var(--surface);
        border-bottom: 1px solid var(--border);
        padding: 0.75rem 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        position: sticky;
        top: 0;
        z-index: 100;
    }

    .pos-logo {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.95rem;
        font-weight: 800;
        flex-shrink: 0;
    }

    .pos-logo-dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: var(--green);
        box-shadow: 0 0 7px var(--green);
        animation: blink 2.5s infinite;
    }

    @keyframes blink {
        0%, 100% { opacity: 1; }
        50%       { opacity: 0.35; }
    }

    .pos-logo-text { color: var(--green); }
    .pos-logo-sub  { color: var(--muted); font-weight: 400; font-size: 0.8rem; }

    .pos-topbar-right {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .pos-merchant-tag {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.8rem;
        color: var(--muted);
    }

    .pos-merchant-name {
        color: var(--text);
        font-weight: 700;
    }

    .pos-merchant-balance {
        background: var(--green-dim);
        border: 1px solid rgba(34,197,94,0.25);
        border-radius: 99px;
        padding: 0.18rem 0.65rem;
        font-size: 0.75rem;
        font-weight: 800;
        color: var(--green);
    }

    .pos-topbar-btn {
        background: transparent;
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--muted);
        padding: 0.3rem 0.65rem;
        font-size: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        font-family: inherit;
        display: flex;
        align-items: center;
        gap: 0.3rem;
        transition: all 0.15s;
        white-space: nowrap;
    }

    .pos-topbar-btn:hover { border-color: var(--green); color: var(--green); }
    .pos-topbar-btn.danger:hover { border-color: var(--red); color: var(--red); }

    /* ── Main layout ── */
    .pos-main {
        flex: 1;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding: 2rem 1rem 3rem;
    }

    .pos-panel {
        width: 100%;
        max-width: 430px;
    }

    /* ── Step indicator ── */
    .pos-steps {
        display: flex;
        gap: 0;
        margin-bottom: 1.5rem;
    }

    .pos-step {
        flex: 1;
        text-align: center;
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding-bottom: 0.45rem;
        border-bottom: 2px solid var(--border);
        color: var(--muted);
        transition: all 0.2s;
    }

    .pos-step.active { color: var(--green); border-bottom-color: var(--green); }
    .pos-step.done   { color: #374151; border-bottom-color: #22c55e44; }

    /* ── Card ── */
    .pos-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 18px;
        overflow: hidden;
    }

    .pos-card-head { padding: 1.25rem 1.5rem 0; }

    .pos-card-title {
        font-size: 1.05rem;
        font-weight: 800;
        margin-bottom: 0.2rem;
        display: flex;
        align-items: center;
        gap: 0.45rem;
    }

    .pos-card-sub { font-size: 0.78rem; color: var(--muted); }

    .pos-card-body { padding: 1.25rem 1.5rem 1.5rem; }

    /* ── Form fields ── */
    .pos-field { margin-bottom: 0.9rem; }

    .pos-field label {
        display: block;
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--muted);
        margin-bottom: 0.35rem;
    }

    .pos-field input {
        width: 100%;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 10px;
        color: var(--text);
        padding: 0.75rem 1rem;
        font-size: 0.88rem;
        font-family: inherit;
        outline: none;
        transition: border-color 0.2s;
    }

    .pos-field input:focus { border-color: var(--green); }
    .pos-field input::placeholder { color: #2a3f2a; }

    /* ── Buttons ── */
    .pos-btn {
        width: 100%;
        padding: 0.8rem;
        border-radius: 10px;
        border: none;
        font-size: 0.88rem;
        font-weight: 800;
        cursor: pointer;
        font-family: inherit;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        transition: all 0.18s;
        letter-spacing: 0.2px;
    }

    .pos-btn + .pos-btn { margin-top: 0.6rem; }

    .pos-btn.green { background: var(--green); color: #000; box-shadow: 0 4px 16px rgba(34,197,94,0.25); }
    .pos-btn.green:hover { background: var(--green-d); color: #fff; }
    .pos-btn.outline { background: transparent; border: 1px solid var(--border); color: var(--muted); }
    .pos-btn.outline:hover { border-color: var(--green); color: var(--green); }
    .pos-btn:disabled { opacity: 0.4; cursor: not-allowed; }

    /* ── Camera ── */
    .camera-wrap {
        position: relative;
        width: 100%;
        aspect-ratio: 1;
        background: #000;
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 1rem;
    }

    .camera-wrap video { width: 100%; height: 100%; object-fit: cover; }

    .scan-overlay {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: radial-gradient(ellipse 55% 55% at 50% 50%, transparent 50%, rgba(0,0,0,0.55) 100%);
        pointer-events: none;
    }

    .scan-frame {
        width: 60%;
        height: 60%;
        position: relative;
    }

    .scan-frame::before,
    .scan-frame::after,
    .scan-frame > span::before,
    .scan-frame > span::after {
        content: '';
        position: absolute;
        width: 22px;
        height: 22px;
        border-color: var(--green);
        border-style: solid;
    }

    .scan-frame::before { top:0;left:0; border-width:3px 0 0 3px; border-radius:3px 0 0 0; }
    .scan-frame::after  { top:0;right:0; border-width:3px 3px 0 0; border-radius:0 3px 0 0; }
    .scan-frame > span::before { bottom:0;left:0; border-width:0 0 3px 3px; border-radius:0 0 0 3px; }
    .scan-frame > span::after  { bottom:0;right:0; border-width:0 3px 3px 0; border-radius:0 0 3px 0; }

    .scan-line {
        position: absolute;
        left: 5%; right: 5%;
        height: 2px;
        background: linear-gradient(90deg, transparent, var(--green), transparent);
        animation: scanMove 2s ease-in-out infinite;
        top: 5%;
        box-shadow: 0 0 6px var(--green);
    }

    @keyframes scanMove { 0%,100% { top:8%; } 50% { top:88%; } }

    .scan-status {
        position: absolute;
        bottom: 0.75rem;
        left: 0;
        right: 0;
        text-align: center;
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--green);
        text-shadow: 0 0 8px rgba(34,197,94,0.5);
    }

    /* ── User preview ── */
    .user-preview {
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 0.9rem 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .user-avatar {
        width: 44px;
        height: 44px;
        border-radius: 11px;
        background: linear-gradient(135deg, #7c3aed, #4f46e5);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: 1rem;
        flex-shrink: 0;
        overflow: hidden;
    }

    .user-avatar img { width: 100%; height: 100%; object-fit: cover; }

    .user-name    { font-size: 0.95rem; font-weight: 800; }
    .user-balance { font-size: 0.78rem; color: var(--muted); margin-top: 0.1rem; }
    .user-balance strong { color: #a78bfa; }

    /* ── Amount input ── */
    .amount-wrap { position: relative; margin-bottom: 0.9rem; }

    .amount-wrap input {
        width: 100%;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 10px;
        color: var(--green);
        padding: 1rem 1rem 1rem 3.2rem;
        font-size: 1.8rem;
        font-weight: 900;
        font-family: inherit;
        outline: none;
        transition: border-color 0.2s;
        text-align: center;
        letter-spacing: 2px;
    }

    .amount-wrap input:focus { border-color: var(--green); box-shadow: 0 0 0 3px rgba(34,197,94,0.1); }
    .amount-wrap input::placeholder { color: #1a2b1a; font-size: 1.1rem; letter-spacing: 0; }

    .amount-unit {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--muted);
        pointer-events: none;
    }

    /* ── Quick amounts ── */
    .quick-amounts {
        display: flex;
        gap: 0.4rem;
        margin-bottom: 0.9rem;
        flex-wrap: wrap;
    }

    .quick-btn {
        flex: 1;
        min-width: 55px;
        padding: 0.45rem 0.25rem;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--muted);
        font-size: 0.78rem;
        font-weight: 700;
        cursor: pointer;
        font-family: inherit;
        transition: all 0.15s;
        text-align: center;
    }

    .quick-btn:hover { border-color: var(--green); color: var(--green); background: var(--green-dim); }

    /* ── Note field ── */
    .note-field {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 0 0.85rem;
        margin-bottom: 1rem;
        transition: border-color 0.2s;
    }

    .note-field:focus-within { border-color: #374151; }
    .note-field i { color: var(--muted); font-size: 0.85rem; flex-shrink: 0; }

    .note-field input {
        flex: 1;
        background: transparent;
        border: none;
        color: var(--text);
        padding: 0.6rem 0;
        font-size: 0.82rem;
        font-family: inherit;
        outline: none;
    }

    .note-field input::placeholder { color: #2a3a2a; }

    /* ── Receipt ── */
    .receipt {
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        text-align: center;
    }

    .receipt-check {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: var(--green-dim);
        border: 2px solid var(--green);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: var(--green);
        margin: 0 auto 1rem;
        animation: popIn 0.4s cubic-bezier(0.34,1.56,0.64,1);
    }

    @keyframes popIn {
        from { transform: scale(0); opacity: 0; }
        to   { transform: scale(1); opacity: 1; }
    }

    .receipt-amount {
        font-size: 2.5rem;
        font-weight: 900;
        color: var(--green);
        letter-spacing: -1px;
        margin-bottom: 0.15rem;
    }

    .receipt-unit { font-size: 0.82rem; color: var(--muted); margin-bottom: 1.1rem; }

    .receipt-row {
        display: flex;
        justify-content: space-between;
        font-size: 0.8rem;
        padding: 0.4rem 0;
        border-top: 1px solid var(--border);
    }

    .receipt-row .label { color: var(--muted); }
    .receipt-row .value { font-weight: 700; text-align: right; max-width: 60%; word-break: break-word; }
    .receipt-note-val   { color: var(--amber); }

    /* ── Error ── */
    .pos-error {
        background: var(--red-dim);
        border: 1px solid rgba(239,68,68,0.25);
        border-radius: 10px;
        padding: 0.7rem 1rem;
        color: #fca5a5;
        font-size: 0.82rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    /* ── Spinner ── */
    .pos-spinner {
        display: inline-block;
        width: 15px;
        height: 15px;
        border: 2px solid rgba(0,0,0,0.2);
        border-top-color: #000;
        border-radius: 50%;
        animation: spin 0.7s linear infinite;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── History drawer ── */
    .hist-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.55);
        z-index: 200;
        justify-content: flex-end;
    }

    .hist-overlay.open { display: flex; }

    .hist-drawer {
        width: 100%;
        max-width: 400px;
        background: var(--surface);
        border-left: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        animation: slideIn 0.28s cubic-bezier(0.25,0.46,0.45,0.94);
        overflow: hidden;
    }

    @keyframes slideIn {
        from { transform: translateX(100%); }
        to   { transform: translateX(0); }
    }

    .hist-head {
        padding: 1.1rem 1.25rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
    }

    .hist-title { font-size: 0.95rem; font-weight: 800; }

    .hist-today {
        font-size: 0.75rem;
        color: var(--muted);
        margin-top: 0.15rem;
    }

    .hist-today strong { color: var(--green); }

    .hist-close {
        background: transparent;
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--muted);
        width: 32px;
        height: 32px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        transition: all 0.15s;
    }

    .hist-close:hover { border-color: var(--red); color: var(--red); }

    .hist-body {
        flex: 1;
        overflow-y: auto;
        padding: 0.5rem 0;
    }

    .hist-empty {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--muted);
        font-size: 0.85rem;
    }

    .hist-empty i { font-size: 2rem; display: block; margin-bottom: 0.5rem; opacity: 0.25; }

    .hist-item {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        padding: 0.75rem 1.25rem;
        border-bottom: 1px solid var(--border);
        transition: background 0.1s;
    }

    .hist-item:hover { background: var(--card); }

    .hist-item-icon {
        width: 36px;
        height: 36px;
        border-radius: 9px;
        background: var(--green-dim);
        border: 1px solid rgba(34,197,94,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--green);
        font-size: 0.9rem;
        flex-shrink: 0;
    }

    .hist-item-icon.market { background: rgba(139,92,246,0.1); border-color: rgba(139,92,246,0.2); color: #a78bfa; }

    .hist-item-info { flex: 1; min-width: 0; }
    .hist-item-from { font-size: 0.83rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .hist-item-note { font-size: 0.72rem; color: var(--amber); margin-top: 0.05rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .hist-item-time { font-size: 0.68rem; color: var(--muted); margin-top: 0.15rem; }

    .hist-item-amount {
        font-size: 0.9rem;
        font-weight: 900;
        color: var(--green);
        white-space: nowrap;
        flex-shrink: 0;
    }

    .hist-refresh {
        padding: 0.85rem 1.25rem;
        border-top: 1px solid var(--border);
        flex-shrink: 0;
    }

    /* ── Dashboard ── */
    .dash-hero {
        background: linear-gradient(135deg, #0a1f0a 0%, #0d2b0d 50%, #090d09 100%);
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        position: relative;
        overflow: hidden;
    }

    .dash-hero::before {
        content: '';
        position: absolute;
        width: 250px; height: 250px;
        background: radial-gradient(circle, rgba(34,197,94,0.12) 0%, transparent 70%);
        right: -50px; top: -50px;
        pointer-events: none;
    }

    .dash-greeting {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: var(--green);
        margin-bottom: 0.25rem;
    }

    .dash-name {
        font-size: 1.5rem;
        font-weight: 900;
        color: var(--text);
        letter-spacing: -0.5px;
        margin-bottom: 0.75rem;
    }

    .dash-balance-row {
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }

    .dash-balance-val {
        font-size: 2rem;
        font-weight: 900;
        color: var(--green);
        letter-spacing: -1px;
    }

    .dash-balance-unit {
        font-size: 0.78rem;
        color: var(--muted);
        font-weight: 600;
    }

    .dash-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.6rem;
        margin-bottom: 1rem;
    }

    .dash-stat {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 0.9rem 1rem;
    }

    .dash-stat-val {
        font-size: 1.25rem;
        font-weight: 900;
        color: var(--green);
        letter-spacing: -0.5px;
    }

    .dash-stat-lbl {
        font-size: 0.68rem;
        color: var(--muted);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-top: 0.15rem;
    }

    .dash-txns {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 1rem;
    }

    .dash-txns-head {
        padding: 0.85rem 1rem;
        border-bottom: 1px solid var(--border);
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--muted);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .dash-txns-body { max-height: 220px; overflow-y: auto; }

    .dash-txn {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.65rem 1rem;
        border-bottom: 1px solid var(--border);
    }

    .dash-txn:last-child { border-bottom: none; }

    .dash-txn-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: var(--green-dim);
        border: 1px solid rgba(34,197,94,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--green);
        font-size: 0.78rem;
        flex-shrink: 0;
    }

    .dash-txn-from { font-size: 0.8rem; font-weight: 700; }
    .dash-txn-note { font-size: 0.68rem; color: var(--amber); margin-top: 0.05rem; }
    .dash-txn-time { font-size: 0.65rem; color: var(--muted); }
    .dash-txn-amt  { font-size: 0.88rem; font-weight: 900; color: var(--green); margin-left: auto; white-space: nowrap; flex-shrink: 0; }

    .dash-empty {
        text-align: center;
        padding: 2rem 1rem;
        color: var(--muted);
        font-size: 0.82rem;
    }

    .dash-empty i { font-size: 1.5rem; display: block; margin-bottom: 0.4rem; opacity: 0.2; }

    /* ── Hidden ── */
    .hidden { display: none !important; }
    </style>
</head>
<body>

<!-- Top bar -->
<div class="pos-topbar">
    <div class="pos-logo">
        <div class="pos-logo-dot"></div>
        <span class="pos-logo-text">Apex Cybernet</span>
        <span class="pos-logo-sub">&nbsp;POS</span>
    </div>

    <div class="pos-topbar-right">
        <div class="pos-merchant-tag hidden" id="merchantTag">
            <i class="bi bi-shop" style="color:var(--green);"></i>
            <span class="pos-merchant-name" id="merchantTagName"></span>
            <span class="pos-merchant-balance" id="merchantTagBalance"></span>
        </div>
        <button class="pos-topbar-btn hidden" id="histBtn" onclick="openHistory()">
            <i class="bi bi-clock-history"></i> History
        </button>
        <button class="pos-topbar-btn danger hidden" id="resetBtn" onclick="resetAll()">
            <i class="bi bi-x"></i> Change
        </button>
    </div>
</div>

<div class="pos-main">
    <div class="pos-panel">

        <!-- Step indicator (hidden on dashboard) -->
        <div class="pos-steps hidden" id="posSteps">
            <div class="pos-step active" id="sInd1">1. Login</div>
            <div class="pos-step"        id="sInd2">2. Scan</div>
            <div class="pos-step"        id="sInd3">3. Charge</div>
            <div class="pos-step"        id="sInd4">4. Done</div>
        </div>

        <!-- ════ DASHBOARD (shown after login) ════ -->
        <div id="stepDash" class="hidden">
            <!-- Hero: name + balance -->
            <div class="dash-hero">
                <div class="dash-greeting"><i class="bi bi-shop"></i> Merchant Terminal</div>
                <div class="dash-name" id="dashName"></div>
                <div class="dash-balance-row">
                    <div class="dash-balance-val" id="dashBalance">0</div>
                    <div class="dash-balance-unit">HC balance</div>
                </div>
            </div>

            <!-- Stats grid -->
            <div class="dash-stats">
                <div class="dash-stat">
                    <div class="dash-stat-val" id="dashToday">—</div>
                    <div class="dash-stat-lbl">Today</div>
                </div>
                <div class="dash-stat">
                    <div class="dash-stat-val" id="dashWeek">—</div>
                    <div class="dash-stat-lbl">This week</div>
                </div>
                <div class="dash-stat">
                    <div class="dash-stat-val" id="dashMonth">—</div>
                    <div class="dash-stat-lbl">This month</div>
                </div>
                <div class="dash-stat">
                    <div class="dash-stat-val" id="dashAllTime">—</div>
                    <div class="dash-stat-lbl">All time</div>
                </div>
            </div>

            <!-- Recent transactions -->
            <div class="dash-txns">
                <div class="dash-txns-head">
                    <span><i class="bi bi-clock-history"></i> Recent Payments</span>
                    <span id="dashTxnCount" style="color:var(--green);"></span>
                </div>
                <div class="dash-txns-body" id="dashTxnList">
                    <div class="dash-empty"><i class="bi bi-inbox"></i>No payments yet</div>
                </div>
            </div>

            <!-- Actions -->
            <button class="pos-btn green" onclick="openScan('collect')">
                <i class="bi bi-qr-code-scan"></i> Start Collecting
            </button>
            <button class="pos-btn outline" onclick="openScan('sell')" style="margin-top:0.6rem;">
                <i class="bi bi-arrow-up-circle"></i> Sell HC to Customer
            </button>
        </div>

        <!-- ════ STEP 1: Merchant Login ════ -->
        <div id="stepMerchant" class="pos-card">
            <div class="pos-card-head">
                <div class="pos-card-title"><i class="bi bi-shield-lock" style="color:var(--green);"></i> Merchant Login</div>
                <div class="pos-card-sub">Enter your Apex Cybernet credentials to open the terminal</div>
            </div>
            <div class="pos-card-body">
                <div id="merchantError" class="pos-error hidden"></div>
                <div class="pos-field">
                    <label>Username</label>
                    <input type="text" id="merchantInput" placeholder="Your display name" autocomplete="username" autocapitalize="none">
                </div>
                <div class="pos-field">
                    <label>Password</label>
                    <input type="password" id="merchantPass" placeholder="Your account password" autocomplete="current-password">
                </div>
                <button class="pos-btn green" id="loginBtn" onclick="setupMerchant()">
                    <i class="bi bi-terminal"></i> Open Terminal
                </button>
            </div>
        </div>

        <!-- ════ STEP 2: Scan QR ════ -->
        <div id="stepScan" class="pos-card hidden">
            <div class="pos-card-head">
                <div class="pos-card-title"><i class="bi bi-qr-code-scan" style="color:var(--green);"></i> Scan Customer QR</div>
                <div class="pos-card-sub">Point camera at the customer's Apex Cybernet Wallet QR code</div>
            </div>
            <div class="pos-card-body">
                <div id="scanError" class="pos-error hidden"></div>
                <div class="camera-wrap">
                    <video id="camVideo" autoplay playsinline muted></video>
                    <canvas id="camCanvas" style="display:none;"></canvas>
                    <div class="scan-overlay">
                        <div class="scan-frame">
                            <span></span>
                            <div class="scan-line"></div>
                        </div>
                    </div>
                    <div class="scan-status" id="scanStatus">Searching for QR…</div>
                </div>
                <button class="pos-btn outline" onclick="goBack()">
                    <i class="bi bi-arrow-left"></i> Dashboard
                </button>
            </div>
        </div>

        <!-- ════ STEP 3: Charge ════ -->
        <div id="stepCharge" class="pos-card hidden">
            <div class="pos-card-head">
                <div class="pos-card-title"><i class="bi bi-lightning-charge-fill" style="color:var(--amber);"></i> Charge Customer</div>
                <div class="pos-card-sub">Enter the amount to collect</div>
            </div>
            <div class="pos-card-body">
                <div id="chargeError" class="pos-error hidden"></div>

                <div class="user-preview">
                    <div class="user-avatar" id="userAvatar"></div>
                    <div>
                        <div class="user-name" id="userName"></div>
                        <div class="user-balance">Balance: <strong id="userBalance"></strong> HC</div>
                    </div>
                </div>

                <div class="quick-amounts">
                    <button class="quick-btn" onclick="setAmount(20)">20</button>
                    <button class="quick-btn" onclick="setAmount(50)">50</button>
                    <button class="quick-btn" onclick="setAmount(100)">100</button>
                    <button class="quick-btn" onclick="setAmount(250)">250</button>
                    <button class="quick-btn" onclick="setAmount(500)">500</button>
                    <button class="quick-btn" onclick="setAmount(1000)">1K</button>
                </div>

                <div class="amount-wrap">
                    <span class="amount-unit">HC</span>
                    <input type="number" id="chargeAmount" placeholder="0" min="1" max="999999" inputmode="numeric">
                </div>

                <div class="note-field">
                    <i class="bi bi-chat-square-text"></i>
                    <input type="text" id="chargeNote" placeholder="Reference / note (optional)" maxlength="60">
                </div>

                <button class="pos-btn green" id="chargeBtn" onclick="processCharge()">
                    <i class="bi bi-lightning-charge-fill"></i> Charge H-Coins
                </button>
                <button class="pos-btn outline" onclick="rescan()">
                    <i class="bi bi-arrow-clockwise"></i> Scan Different QR
                </button>
            </div>
        </div>

        <!-- ════ STEP 4: Receipt ════ -->
        <div id="stepReceipt" class="pos-card hidden">
            <div class="pos-card-body">
                <div class="receipt" id="receiptBox">
                    <div class="receipt-check"><i class="bi bi-check-lg"></i></div>
                    <div class="receipt-amount" id="receiptAmount"></div>
                    <div class="receipt-unit">H-Coins collected</div>
                    <div class="receipt-row">
                        <span class="label">Customer</span>
                        <span class="value" id="receiptUser"></span>
                    </div>
                    <div class="receipt-row">
                        <span class="label">Merchant</span>
                        <span class="value" id="receiptMerchant"></span>
                    </div>
                    <div class="receipt-row hidden" id="receiptNoteRow">
                        <span class="label">Note</span>
                        <span class="value receipt-note-val" id="receiptNote"></span>
                    </div>
                    <div class="receipt-row">
                        <span class="label">Customer remaining</span>
                        <span class="value" id="receiptUserBal"></span>
                    </div>
                    <div class="receipt-row">
                        <span class="label">Your balance</span>
                        <span class="value" style="color:var(--green);" id="receiptMerchantBal"></span>
                    </div>
                    <div class="receipt-row">
                        <span class="label">Time</span>
                        <span class="value" id="receiptTime"></span>
                    </div>
                </div>

                <button class="pos-btn green" onclick="nextCustomer()">
                    <i class="bi bi-qr-code-scan"></i> Next Customer
                </button>
                <button class="pos-btn outline" onclick="openHistory()">
                    <i class="bi bi-clock-history"></i> View History
                </button>
            </div>
        </div>

        <!-- ════ SELL: Confirm step ════ -->
        <div id="stepSellConfirm" class="pos-card hidden">
            <div class="pos-card-head">
                <div class="pos-card-title"><i class="bi bi-arrow-up-circle-fill" style="color:var(--amber);"></i> Sell HC to Customer</div>
                <div class="pos-card-sub">Collect cash, then confirm to transfer HC</div>
            </div>
            <div class="pos-card-body">
                <div id="sellError" class="pos-error hidden"></div>

                <div class="user-preview">
                    <div class="user-avatar" id="sellUserAvatar"></div>
                    <div>
                        <div class="user-name" id="sellUserName"></div>
                        <div class="user-balance" style="margin-top:0.1rem;font-size:0.78rem;color:var(--muted);">Buying <strong id="sellHcAmt" style="color:var(--green);"></strong> HC</div>
                    </div>
                </div>

                <div style="background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:1.1rem 1rem;text-align:center;margin-bottom:1rem;">
                    <div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);margin-bottom:0.25rem;">Collect cash from customer</div>
                    <div style="font-size:2rem;font-weight:900;color:var(--amber);">₱<span id="sellPesoAmt"></span></div>
                    <div style="font-size:0.72rem;color:var(--muted);margin-top:0.25rem;">then tap Confirm below</div>
                </div>

                <button class="pos-btn green" id="sellConfirmBtn" onclick="processSell()">
                    <i class="bi bi-check-circle-fill"></i> Cash Collected — Confirm Transfer
                </button>
                <button class="pos-btn outline" onclick="rescan()">
                    <i class="bi bi-arrow-clockwise"></i> Scan Different QR
                </button>
            </div>
        </div>

        <!-- ════ SELL: Receipt step ════ -->
        <div id="stepSellReceipt" class="pos-card hidden">
            <div class="pos-card-body">
                <div class="receipt" id="sellReceiptBox">
                    <div class="receipt-check" style="border-color:var(--amber);"><i class="bi bi-check-lg" style="color:var(--amber);"></i></div>
                    <div class="receipt-amount" id="sellReceiptAmount" style="color:var(--amber);"></div>
                    <div class="receipt-unit">HC sent to customer</div>
                    <div class="receipt-row">
                        <span class="label">Customer</span>
                        <span class="value" id="sellReceiptUser"></span>
                    </div>
                    <div class="receipt-row">
                        <span class="label">Merchant</span>
                        <span class="value" id="sellReceiptMerchant"></span>
                    </div>
                    <div class="receipt-row">
                        <span class="label">Customer balance</span>
                        <span class="value" id="sellReceiptUserBal"></span>
                    </div>
                    <div class="receipt-row">
                        <span class="label">Your balance</span>
                        <span class="value" style="color:var(--green);" id="sellReceiptMerchBal"></span>
                    </div>
                    <div class="receipt-row">
                        <span class="label">Time</span>
                        <span class="value" id="sellReceiptTime"></span>
                    </div>
                </div>

                <button class="pos-btn green" onclick="showDashboard()">
                    <i class="bi bi-house"></i> Back to Dashboard
                </button>
                <button class="pos-btn outline" onclick="openScan('sell')">
                    <i class="bi bi-arrow-up-circle"></i> Sell to Another Customer
                </button>
            </div>
        </div>

    </div>
</div>

<!-- History drawer -->
<div class="hist-overlay" id="histOverlay" onclick="if(event.target===this)closeHistory()">
    <div class="hist-drawer">
        <div class="hist-head">
            <div>
                <div class="hist-title"><i class="bi bi-clock-history" style="color:var(--green);"></i> Transaction History</div>
                <div class="hist-today">Today: <strong id="histTodayTotal">0</strong> HC received</div>
            </div>
            <button class="hist-close" onclick="closeHistory()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="hist-body" id="histBody">
            <div class="hist-empty"><i class="bi bi-inbox"></i>No transactions yet</div>
        </div>
        <div class="hist-refresh">
            <button class="pos-btn outline" onclick="loadHistory()" style="font-size:0.8rem; padding:0.55rem;">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script>
const API = <?= json_encode($api_base) ?>;

let merchantName    = '';
let merchantId      = 0;
let merchantBalance = 0;
let scannedToken    = '';
let scannedUser     = null;
let scanInterval    = null;
let camStream       = null;
let scanMode        = 'collect'; // 'collect' | 'sell'
let sellBuyData     = null;

// ── Enter key on login fields ──
document.getElementById('merchantInput').addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('merchantPass').focus(); });
document.getElementById('merchantPass').addEventListener('keydown', e => { if (e.key === 'Enter') setupMerchant(); });
document.getElementById('chargeAmount').addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('chargeNote').focus(); });
document.getElementById('chargeNote').addEventListener('keydown', e => { if (e.key === 'Enter') processCharge(); });

// ── Utilities ──
function showError(id, msg) {
    const el = document.getElementById(id);
    el.innerHTML = '<i class="bi bi-exclamation-circle"></i> ' + msg;
    el.classList.remove('hidden');
}

function hideError(id) { document.getElementById(id).classList.add('hidden'); }

const ALL_STEPS = ['stepMerchant','stepScan','stepCharge','stepReceipt','stepSellConfirm','stepSellReceipt'];

function hideAllSteps() {
    ALL_STEPS.forEach(id => document.getElementById(id).classList.add('hidden'));
    document.getElementById('stepDash').classList.add('hidden');
}

function setStep(n) {
    // n=0 → dashboard; n=1..4 → collect flow
    hideAllSteps();
    document.getElementById('stepDash').classList.toggle('hidden', n !== 0);
    document.getElementById('posSteps').classList.toggle('hidden', n === 0 || n === 1);
    ['stepMerchant','stepScan','stepCharge','stepReceipt'].forEach((id, i) => {
        document.getElementById(id).classList.toggle('hidden', i + 1 !== n);
    });
    [1,2,3,4].forEach(i => {
        const el = document.getElementById('sInd' + i);
        el.classList.remove('active','done');
        if (i === n) el.classList.add('active');
        else if (i < n) el.classList.add('done');
    });
}

function setSellStep(which) {
    // which: 'scan' | 'confirm' | 'receipt'
    hideAllSteps();
    document.getElementById('posSteps').classList.add('hidden');
    if (which === 'scan')    document.getElementById('stepScan').classList.remove('hidden');
    if (which === 'confirm') document.getElementById('stepSellConfirm').classList.remove('hidden');
    if (which === 'receipt') document.getElementById('stepSellReceipt').classList.remove('hidden');
}

function updateMerchantTag() {
    document.getElementById('merchantTag').classList.remove('hidden');
    document.getElementById('histBtn').classList.remove('hidden');
    document.getElementById('resetBtn').classList.remove('hidden');
    document.getElementById('merchantTagName').textContent = merchantName;
    document.getElementById('merchantTagBalance').textContent = Number(merchantBalance).toLocaleString() + ' HC';
}

// ── Step 1: Login ──
async function setupMerchant() {
    const name = document.getElementById('merchantInput').value.trim();
    const pass = document.getElementById('merchantPass').value;
    if (!name || !pass) { showError('merchantError', 'Enter username and password'); return; }

    hideError('merchantError');
    const btn = document.getElementById('loginBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="pos-spinner" style="border-top-color:#000;"></span> Verifying…';

    try {
        const res  = await fetch(API + '/merchant-lookup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ merchant: name, password: pass }),
        });
        const data = await res.json();

        if (data.error) {
            showError('merchantError', data.error);
        } else {
            merchantName    = data.display_name;
            merchantId      = data.merchant_id;
            merchantBalance = data.h_coins;
            updateMerchantTag();
            document.getElementById('merchantPass').value = ''; // clear password from memory
            showDashboard();
        }
    } catch (e) {
        showError('merchantError', 'Network error — try again');
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-terminal"></i> Open Terminal';
}

// ── Dashboard ──
async function showDashboard() {
    document.getElementById('dashName').textContent    = merchantName;
    document.getElementById('dashBalance').textContent = Number(merchantBalance).toLocaleString();
    setStep(0);
    loadDashStats();
}

async function loadDashStats() {
    if (!merchantId) return;
    try {
        const res  = await fetch(API + '/merchant-transactions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ merchant_id: merchantId }),
        });
        const data = await res.json();
        if (!data.ok) return;

        document.getElementById('dashToday').textContent   = '+' + Number(data.today_total).toLocaleString();
        document.getElementById('dashWeek').textContent    = '+' + Number(data.this_week).toLocaleString();
        document.getElementById('dashMonth').textContent   = '+' + Number(data.this_month).toLocaleString();
        document.getElementById('dashAllTime').textContent = '+' + Number(data.all_time).toLocaleString();
        document.getElementById('dashTxnCount').textContent = data.total_txns + ' total';

        const list = document.getElementById('dashTxnList');
        if (!data.transactions.length) {
            list.innerHTML = '<div class="dash-empty"><i class="bi bi-inbox"></i>No payments yet</div>';
        } else {
            list.innerHTML = data.transactions.slice(0, 10).map(t => {
                const timeStr = new Date(t.time.replace(' ','T')).toLocaleString([], { month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
                return `<div class="dash-txn">
                    <div class="dash-txn-icon"><i class="bi bi-qr-code-scan"></i></div>
                    <div style="flex:1;min-width:0;">
                        <div class="dash-txn-from">${escHtml(t.from || '?')}</div>
                        ${t.note ? '<div class="dash-txn-note">' + escHtml(t.note) + '</div>' : ''}
                        <div class="dash-txn-time">${timeStr}</div>
                    </div>
                    <div class="dash-txn-amt">+${Number(t.amount).toLocaleString()}</div>
                </div>`;
            }).join('');
        }
    } catch (e) { /* silent fail */ }
}

function openScan(mode) {
    scanMode = mode || 'collect';
    const sub = document.querySelector('#stepScan .pos-card-sub');
    if (sub) sub.textContent = scanMode === 'sell'
        ? 'Point camera at the customer\'s Buy QR code'
        : 'Point camera at the customer\'s Apex Cybernet Wallet QR code';
    document.getElementById('scanStatus').textContent = 'Searching for QR…';
    hideError('scanError');
    startCamera();
    if (scanMode === 'sell') {
        setSellStep('scan');
    } else {
        setStep(2);
    }
}

// ── Step 2: Camera + QR scan ──
async function startCamera() {
    try {
        camStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 720 }, height: { ideal: 720 } }
        });
        const video = document.getElementById('camVideo');
        video.srcObject = camStream;
        await video.play();
        // Start scanning immediately. scanFrame's readyState guard skips
        // frames until the camera has data — avoids a race where loadeddata
        // fires before we can attach a listener and the scan loop never starts.
        startScanLoop();
    } catch (e) {
        showError('scanError', 'Camera access denied. Allow camera permission and try again.');
    }
}

function stopCamera() {
    if (scanInterval) { clearInterval(scanInterval); scanInterval = null; }
    if (camStream) { camStream.getTracks().forEach(t => t.stop()); camStream = null; }
}

function startScanLoop() {
    if (scanInterval) clearInterval(scanInterval);
    scanInterval = setInterval(scanFrame, 80);
}

function scanFrame() {
    const video = document.getElementById('camVideo');
    if (video.readyState < video.HAVE_CURRENT_DATA) return;
    if (!video.videoWidth || !video.videoHeight) return;
    const canvas = document.getElementById('camCanvas');
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(video, 0, 0);
    const img  = ctx.getImageData(0, 0, canvas.width, canvas.height);
    // attemptBoth tries inverted colors as a fallback — helps with low-contrast
    // displays / dark-mode wallets where the QR is near-grey, not pure black.
    const code = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
    if (code && code.data) {
        document.getElementById('scanStatus').textContent = 'QR detected — verifying…';
        clearInterval(scanInterval); scanInterval = null;
        lookupToken(code.data);
    }
}

async function lookupToken(token) {
    // Route by token content — BUY: prefix = sell flow, regardless of scanMode
    if (token.startsWith('BUY:') || scanMode === 'sell') {
        scanMode = 'sell'; // keep consistent
        await lookupBuyQR(token);
        return;
    }
    hideError('scanError');
    try {
        const res  = await fetch(API + '/qr-lookup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token }),
        });
        const data = await res.json();
        if (data.error) {
            showError('scanError', data.error);
            document.getElementById('scanStatus').textContent = 'Searching for QR…';
            startScanLoop();
            return;
        }
        stopCamera();
        scannedToken = token;
        scannedUser  = data;
        showChargeStep(data);
    } catch (e) {
        showError('scanError', 'Network error — retrying…');
        document.getElementById('scanStatus').textContent = 'Searching for QR…';
        startScanLoop();
    }
}

// ── Sell HC: look up Buy QR ──
async function lookupBuyQR(token) {
    hideError('scanError');
    document.getElementById('scanStatus').textContent = 'Verifying QR…';
    try {
        const res  = await fetch(API + '/merchant-sell.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'lookup', token, merchant_id: merchantId }),
        });
        const data = await res.json();
        if (data.error) {
            showError('scanError', data.error);
            document.getElementById('scanStatus').textContent = 'Scanning again in 3s…';
            // Pause so merchant can read the error before the loop restarts
            setTimeout(function() {
                hideError('scanError');
                document.getElementById('scanStatus').textContent = 'Searching for QR…';
                startScanLoop();
            }, 3000);
            return;
        }
        stopCamera();
        sellBuyData = data;
        showSellConfirm(data);
    } catch (e) {
        showError('scanError', 'Network error — retrying…');
        document.getElementById('scanStatus').textContent = 'Searching for QR…';
        startScanLoop();
    }
}

function showSellConfirm(d) {
    const initials = (d.user_name || '?').substring(0, 2).toUpperCase();
    document.getElementById('sellUserAvatar').textContent = initials;
    document.getElementById('sellUserName').textContent   = d.user_name;
    document.getElementById('sellHcAmt').textContent      = Number(d.hcoins).toLocaleString();
    document.getElementById('sellPesoAmt').textContent    = Number(d.peso_amount).toFixed(2);
    hideError('sellError');
    setSellStep('confirm');
}

async function processSell() {
    if (!sellBuyData) return;
    const btn = document.getElementById('sellConfirmBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="pos-spinner" style="border-top-color:#000;"></span> Processing…';
    hideError('sellError');

    try {
        const res  = await fetch(API + '/merchant-sell.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'confirm', token: sellBuyData.token, merchant_id: merchantId }),
        });
        const data = await res.json();

        if (!data.success) {
            showError('sellError', data.error || 'Transfer failed');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Cash Collected — Confirm Transfer';
            return;
        }

        merchantBalance = data.merchant_new_balance;
        updateMerchantTag();

        document.getElementById('sellReceiptAmount').textContent  = '-' + Number(data.hcoins).toLocaleString();
        document.getElementById('sellReceiptUser').textContent    = data.user;
        document.getElementById('sellReceiptMerchant').textContent = data.merchant;
        document.getElementById('sellReceiptUserBal').textContent = Number(data.user_new_balance).toLocaleString() + ' HC';
        document.getElementById('sellReceiptMerchBal').textContent = Number(data.merchant_new_balance).toLocaleString() + ' HC';
        document.getElementById('sellReceiptTime').textContent    = data.server_time || '';

        sellBuyData = null;
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Cash Collected — Confirm Transfer';
        setSellStep('receipt');
    } catch (e) {
        showError('sellError', 'Network error — try again');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Cash Collected — Confirm Transfer';
    }
}

function showChargeStep(user) {
    const initials = (user.display_name || '?').substring(0, 2).toUpperCase();
    const avatarEl = document.getElementById('userAvatar');
    if (user.profile_picture) {
        avatarEl.innerHTML = '<img src="' + user.profile_picture + '" alt="">';
    } else {
        avatarEl.textContent = initials;
    }
    document.getElementById('userName').textContent    = user.display_name;
    document.getElementById('userBalance').textContent = Number(user.h_coins).toLocaleString();
    document.getElementById('chargeAmount').value      = '';
    document.getElementById('chargeNote').value        = '';
    hideError('chargeError');
    setStep(3);
    setTimeout(() => document.getElementById('chargeAmount').focus(), 200);
}

function setAmount(n) {
    document.getElementById('chargeAmount').value = n;
    document.getElementById('chargeAmount').focus();
}

function rescan() {
    scannedToken = '';
    scannedUser  = null;
    sellBuyData  = null;
    hideError('scanError');
    document.getElementById('scanStatus').textContent = 'Searching for QR…';
    startCamera();
    if (scanMode === 'sell') {
        setSellStep('scan');
    } else {
        setStep(2);
    }
}

function goBack() {
    stopCamera();
    showDashboard();
}

// ── Step 3: Process charge ──
async function processCharge() {
    const amount = parseInt(document.getElementById('chargeAmount').value, 10);
    const note   = document.getElementById('chargeNote').value.trim();
    hideError('chargeError');

    if (!amount || amount < 1) { showError('chargeError', 'Enter a valid amount'); return; }

    if (scannedUser && amount > scannedUser.h_coins) {
        showError('chargeError', 'Insufficient balance — customer has ' + Number(scannedUser.h_coins).toLocaleString() + ' HC');
        return;
    }

    const btn = document.getElementById('chargeBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="pos-spinner" style="border-top-color:#000;"></span> Processing…';

    try {
        const res  = await fetch(API + '/qr-charge.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: scannedToken, amount, merchant: merchantName, note }),
        });
        const data = await res.json();

        if (!data.success) {
            showError('chargeError', data.error || 'Charge failed');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-lightning-charge-fill"></i> Charge H-Coins';
            return;
        }

        merchantBalance = data.merchant_new_balance;
        updateMerchantTag();

        document.getElementById('receiptAmount').textContent      = '+' + Number(data.amount).toLocaleString();
        document.getElementById('receiptUser').textContent        = data.user;
        document.getElementById('receiptMerchant').textContent    = data.merchant;
        document.getElementById('receiptUserBal').textContent     = Number(data.user_new_balance).toLocaleString() + ' HC';
        document.getElementById('receiptMerchantBal').textContent = Number(data.merchant_new_balance).toLocaleString() + ' HC';
        document.getElementById('receiptTime').textContent        = data.server_time || '';

        if (data.note) {
            document.getElementById('receiptNote').textContent = data.note;
            document.getElementById('receiptNoteRow').classList.remove('hidden');
        } else {
            document.getElementById('receiptNoteRow').classList.add('hidden');
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-lightning-charge-fill"></i> Charge H-Coins';
        setStep(4);
    } catch (e) {
        showError('chargeError', 'Network error — try again');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-lightning-charge-fill"></i> Charge H-Coins';
    }
}

// ── Step 4: Next customer ──
function nextCustomer() {
    scannedToken = '';
    scannedUser  = null;
    openScan();
}

function resetAll() {
    if (!confirm('Close terminal and log out?')) return;
    stopCamera();
    merchantName = ''; merchantId = 0; merchantBalance = 0;
    scannedToken = ''; scannedUser = null; sellBuyData = null; scanMode = 'collect';
    document.getElementById('merchantInput').value = '';
    document.getElementById('merchantPass').value  = '';
    ['merchantTag','histBtn','resetBtn'].forEach(id => document.getElementById(id).classList.add('hidden'));
    closeHistory();
    setStep(1); // back to login
}

// ── History drawer ──
function openHistory() {
    document.getElementById('histOverlay').classList.add('open');
    loadHistory();
}

function closeHistory() {
    document.getElementById('histOverlay').classList.remove('open');
}

async function loadHistory() {
    if (!merchantId) return;
    const body = document.getElementById('histBody');
    body.innerHTML = '<div class="hist-empty" style="padding:2rem;"><i class="bi bi-arrow-clockwise" style="animation:spin 1s linear infinite;display:block;font-size:1.5rem;margin-bottom:0.5rem;opacity:0.4;"></i>Loading…</div>';

    try {
        const res  = await fetch(API + '/merchant-transactions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ merchant_id: merchantId }),
        });
        const data = await res.json();
        if (data.error) { body.innerHTML = '<div class="hist-empty"><i class="bi bi-exclamation-circle"></i>' + data.error + '</div>'; return; }

        document.getElementById('histTodayTotal').textContent = Number(data.today_total).toLocaleString();

        if (!data.transactions.length) {
            body.innerHTML = '<div class="hist-empty"><i class="bi bi-inbox"></i>No transactions yet</div>';
            return;
        }

        body.innerHTML = data.transactions.map(t => {
            const isMarket = t.reason === 'marketplace_sale';
            const timeStr  = new Date(t.time.replace(' ', 'T')).toLocaleString([], { month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
            const icon     = isMarket ? '<i class="bi bi-bag"></i>' : '<i class="bi bi-qr-code-scan"></i>';
            const iconCls  = isMarket ? 'hist-item-icon market' : 'hist-item-icon';
            const fromLabel = isMarket ? 'Market sale' : ('from ' + escHtml(t.from));
            return `
            <div class="hist-item">
                <div class="${iconCls}">${icon}</div>
                <div class="hist-item-info">
                    <div class="hist-item-from">${escHtml(t.from || (isMarket ? 'Marketplace' : '?'))}</div>
                    ${t.note ? '<div class="hist-item-note">' + escHtml(t.note) + '</div>' : ''}
                    <div class="hist-item-time">${timeStr}</div>
                </div>
                <div class="hist-item-amount">+${Number(t.amount).toLocaleString()}</div>
            </div>`;
        }).join('');
    } catch (e) {
        body.innerHTML = '<div class="hist-empty"><i class="bi bi-wifi-off"></i>Network error</div>';
    }
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Keyboard shortcut: Escape closes history
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeHistory(); });
</script>
</body>
</html>
