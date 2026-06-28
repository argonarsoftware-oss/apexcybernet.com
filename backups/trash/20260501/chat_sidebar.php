<?php
// ── chat_sidebar.php ──
// Messenger-style docked chat: right sidebar lists users; clicking a user
// opens a separate chat popup window docked to the bottom-right of the page.
// Include once in footer.php. Requires session + base_url().
// For guests, still shows the player directory — clicking a player prompts login.

$cs_is_guest = empty($_SESSION['account_id']);
$cs_me       = (int)($_SESSION['account_id'] ?? 0);
?>

<style>
:root {
    --cs-width: 300px;
    --cs-peek:  88px;   /* width of sidebar still visible when collapsed */
    --cs-head-h: 54px;
    --cs-chat-w: 340px;
    --cs-chat-h: 440px;
}

/* Push page content so the sidebar doesn't cover it on desktop */
@media (min-width: 1024px) {
    body.cs-has-side { padding-right: var(--cs-peek); transition: padding-right 0.25s ease-out; }
    body.cs-has-side.cs-enabled { padding-right: var(--cs-width); }
}

/* ── Sidebar ── */
.cs-side {
    position: fixed; top: 0; right: 0; bottom: 0; width: var(--cs-width);
    background: var(--bg-card); border-left: 1px solid var(--border);
    z-index: 1000; display: flex; flex-direction: column;
    box-shadow: -4px 0 16px rgba(0,0,0,0.3);
    transition: transform 0.25s ease-out;
}
.cs-side.cs-collapsed { transform: translateX(calc(100% - var(--cs-peek))); }
@media (max-width: 1023px) { .cs-side.cs-collapsed { transform: translateX(100%); } }

/* ── Toggle tab on the left edge of sidebar (alrisha-style) ── */
.cs-toggle {
    position: absolute; left: -30px; top: 50%; transform: translateY(-50%);
    width: 30px; height: 66px;
    background: var(--bg-card); border: 1px solid var(--border); border-right: none;
    border-radius: 10px 0 0 10px;
    display: none; align-items: center; justify-content: center;
    color: var(--accent-light); cursor: pointer;
    font-size: 0.95rem; padding: 0;
    box-shadow: -3px 0 10px rgba(0,0,0,0.25);
    transition: background 0.15s, color 0.15s;
    z-index: 1;
}
@media (min-width: 1024px) { .cs-toggle { display: flex; } }
.cs-toggle:hover { background: rgba(124,58,237,0.1); color: var(--text); }
.cs-toggle .cs-tab-badge {
    position: absolute; top: -5px; left: -5px; min-width: 18px; height: 18px;
    background: #ef4444; color: #fff; font-size: 0.6rem; font-weight: 800;
    padding: 0 4px; border-radius: 99px; display: flex; align-items: center; justify-content: center;
    border: 2px solid var(--bg); line-height: 1;
}
.cs-toggle .cs-tab-badge.hidden { display: none; }
.cs-side.cs-collapsed .cs-toggle i { transform: rotate(180deg); }
.cs-toggle i { transition: transform 0.25s; }

.cs-head {
    height: var(--cs-head-h); flex-shrink: 0;
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0 0.85rem; border-bottom: 1px solid var(--border);
    background: linear-gradient(135deg, rgba(124,58,237,0.08) 0%, transparent 100%);
}
.cs-head-title { flex: 1; font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; }
.cs-head-title i { color: var(--accent-light); }
.cs-head-count { font-size: 0.7rem; font-weight: 600; color: var(--text-muted); }

/* ── Message toast (top-right slide-in) ── */
.cs-toast-stack { position: fixed; top: 70px; right: 16px; z-index: 2500; display: flex; flex-direction: column; gap: 0.5rem; max-width: 320px; pointer-events: none; }
.cs-toast {
    background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px;
    padding: 0.7rem 0.85rem; display: flex; align-items: center; gap: 0.6rem;
    box-shadow: 0 8px 24px rgba(0,0,0,0.5);
    cursor: pointer; pointer-events: auto;
    animation: csToastIn 0.25s ease-out;
    border-left: 3px solid var(--accent);
    text-decoration: none; color: var(--text);
}
.cs-toast.fadeout { animation: csToastOut 0.25s ease-in forwards; }
@keyframes csToastIn  { from { opacity: 0; transform: translateX(40px); } to { opacity: 1; transform: translateX(0); } }
@keyframes csToastOut { from { opacity: 1; transform: translateX(0); }   to { opacity: 0; transform: translateX(40px); } }
.cs-toast-av { width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0; background: #2a2d34; display: flex; align-items: center; justify-content: center; color: #9ca3af; overflow: hidden; }
.cs-toast-av.team { border-radius: 9px; }
.cs-toast-av img { width: 100%; height: 100%; object-fit: cover; }
.cs-toast-av i { font-size: 1.3rem; }
.cs-toast-body { flex: 1; min-width: 0; }
.cs-toast-name { font-size: 0.82rem; font-weight: 800; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cs-toast-msg  { font-size: 0.75rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px; }
.cs-toast-close { background: transparent; border: none; color: var(--text-muted); font-size: 0.85rem; cursor: pointer; padding: 4px; flex-shrink: 0; }
.cs-toast-close:hover { color: var(--text); }

/* ── Notification permission banner ── */
.cs-notif-prompt {
    margin: 0.55rem 0.75rem;
    background: linear-gradient(135deg, rgba(124,58,237,0.12), rgba(76,29,149,0.04));
    border: 1px solid rgba(124,58,237,0.3); border-radius: 10px;
    padding: 0.65rem 0.85rem; font-size: 0.78rem; color: var(--text);
    display: none;
}
.cs-notif-prompt.on { display: flex; align-items: center; gap: 0.6rem; }
.cs-notif-prompt i { color: var(--accent-light); font-size: 1.1rem; flex-shrink: 0; }
.cs-notif-prompt-actions { display: flex; gap: 0.4rem; flex-shrink: 0; }
.cs-notif-prompt button { padding: 0.35rem 0.7rem; border-radius: 6px; font-size: 0.72rem; font-weight: 700; cursor: pointer; border: 1px solid var(--border); background: var(--accent); color: #fff; font-family: inherit; }
.cs-notif-prompt button.ghost { background: transparent; color: var(--text-muted); }

/* ── Tips panel ── */
.cs-tips-btn { background: transparent !important; border: none !important; color: var(--text-muted); width: 28px; height: 28px; }
.cs-tips-btn:hover { color: var(--accent-light); background: rgba(124,58,237,0.08) !important; }
.cs-tips-btn i { font-size: 0.95rem; }
.cs-tips {
    position: absolute; top: calc(var(--cs-head-h) - 4px); right: 0.75rem; left: 0.75rem;
    background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px;
    padding: 0.9rem 1rem 0.85rem; z-index: 5;
    box-shadow: 0 12px 32px rgba(0,0,0,0.5);
    display: none; animation: tipsIn 0.18s ease-out;
}
.cs-tips.on { display: block; }
@keyframes tipsIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
.cs-tips-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.65rem; font-size: 0.82rem; color: var(--accent-light); }
.cs-tip { display: flex; gap: 0.55rem; align-items: flex-start; padding: 0.4rem 0; font-size: 0.75rem; color: var(--text); line-height: 1.4; border-top: 1px dashed rgba(255,255,255,0.05); }
.cs-tip:first-of-type { border-top: none; padding-top: 0.1rem; }
.cs-tip i { font-size: 0.85rem; flex-shrink: 0; margin-top: 2px; width: 14px; text-align: center; }
.cs-tip strong { color: var(--text); }

/* ── Mobile launcher ── */
.cs-launcher {
    position: fixed; bottom: 20px; right: 20px; z-index: 1001;
    width: 52px; height: 52px; border-radius: 50%;
    background: linear-gradient(135deg, #7c3aed, #4c1d95);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.35rem; cursor: pointer; border: none;
    box-shadow: 0 6px 24px rgba(124,58,237,0.5);
}
.cs-launcher .cs-badge {
    position: absolute; top: -4px; right: -4px; min-width: 20px; height: 20px;
    background: #ef4444; color: #fff; font-size: 0.65rem; font-weight: 800;
    padding: 0 5px; border-radius: 99px; display: flex; align-items: center; justify-content: center;
    border: 2px solid var(--bg); line-height: 1;
}
.cs-launcher .cs-badge.hidden { display: none; }
@media (min-width: 1024px) { .cs-launcher { display: none; } }
.cs-side:not(.cs-collapsed) ~ .cs-launcher { display: none; }
@media (max-width: 1023px) {
    .cs-side { transform: translateX(100%); width: min(var(--cs-width), 100vw); }
    .cs-side.cs-open { transform: translateX(0); box-shadow: -8px 0 32px rgba(0,0,0,0.6); }
}

/* ── Search ── */
.cs-search-wrap { padding: 0.55rem 0.75rem; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.cs-search { width: 100%; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 10px; color: var(--text); padding: 0.5rem 0.85rem 0.5rem 2.1rem; font-size: 0.85rem; font-family: inherit; outline: none; background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%236b7280'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: 0.65rem center; background-size: 14px; }
.cs-search:focus { border-color: var(--accent); }

/* ── Users list ── */
.cs-list { flex: 1; overflow-y: auto; }
.cs-list::-webkit-scrollbar { width: 6px; }
.cs-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }

.cs-user { display: flex; align-items: center; gap: 0.7rem; padding: 0.6rem 0.85rem; cursor: pointer; transition: background 0.12s; border-left: 3px solid transparent; }
.cs-user:hover { background: rgba(255,255,255,0.03); }
.cs-user.active { background: rgba(124,58,237,0.1); border-left-color: var(--accent); }

.cs-av-wrap { position: relative; flex-shrink: 0; }
.cs-av { width: 40px; height: 40px; border-radius: 50%; background: #2a2d34; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: #9ca3af; overflow: hidden; }
.cs-av.team { border-radius: 10px; }
.cs-av img { width: 100%; height: 100%; object-fit: cover; }
.cs-av i.bi-person-fill { font-size: 1.6rem; line-height: 1; margin-bottom: -8px; }
.cs-online-dot {
    position: absolute; right: -2px; bottom: -2px;
    width: 12px; height: 12px; border-radius: 50%;
    background: #22c55e; border: 2px solid var(--bg-card);
    box-shadow: 0 0 0 1px rgba(34,197,94,0.35);
}
.cs-private-badge {
    position: absolute; right: -3px; bottom: -3px;
    width: 16px; height: 16px; border-radius: 50%;
    background: #fbbf24; color: #1e0a3a;
    border: 2px solid var(--bg-card);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.55rem;
    box-shadow: 0 0 0 1px rgba(251,191,36,0.3);
}
.cs-private-badge i { line-height: 1; }

.cs-user-body { flex: 1; min-width: 0; }
.cs-user-name { font-size: 0.85rem; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 0.25rem; white-space: nowrap; overflow: hidden; }
.cs-user-name span.nm { overflow: hidden; text-overflow: ellipsis; }
.cs-user-name i.verified { color: #60a5fa; font-size: 0.75rem; flex-shrink: 0; }
.cs-flair {
    display: inline-flex; align-items: center; gap: 0.22rem;
    font-size: 0.6rem; font-weight: 800; padding: 0.1rem 0.4rem;
    border-radius: 4px; line-height: 1.4; flex-shrink: 0;
    max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.cs-flair i { font-size: 0.58rem; }
.cs-flair.team { background: rgba(124,58,237,0.15); color: var(--accent-light); border: 1px solid rgba(124,58,237,0.3); }
.cs-flair.solo { background: rgba(96,165,250,0.15); color: #93c5fd; border: 1px solid rgba(96,165,250,0.3); }
.cs-flair.admin { background: rgba(251,191,36,0.15); color: #fbbf24; border: 1px solid rgba(251,191,36,0.3); }
.cs-user-msg { font-size: 0.72rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
.cs-user-meta { text-align: right; flex-shrink: 0; }
.cs-user-time { font-size: 0.65rem; color: var(--text-muted); }
.cs-unread { display: inline-block; min-width: 18px; height: 18px; padding: 0 5px; background: var(--accent); color: #fff; border-radius: 99px; font-size: 0.62rem; font-weight: 800; line-height: 18px; text-align: center; margin-top: 3px; }

.cs-empty { text-align: center; padding: 2.5rem 1rem; color: var(--text-muted); font-size: 0.82rem; }
.cs-empty i { font-size: 2rem; display: block; margin-bottom: 0.5rem; color: rgba(139,92,246,0.4); }

/* ── Chat popups (docked bottom; stack to the left of the sidebar on desktop) ── */
.cs-boxes { position: fixed; bottom: 0; right: var(--cs-width); z-index: 1002; display: flex; flex-direction: row-reverse; gap: 0.65rem; padding: 0 0.75rem; pointer-events: none; }
.cs-side.cs-collapsed ~ .cs-boxes { right: 0.25rem; }

.cs-box {
    width: var(--cs-chat-w); height: var(--cs-chat-h); max-height: calc(100vh - 40px);
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: 12px 12px 0 0; border-bottom: none;
    display: flex; flex-direction: column; overflow: hidden;
    box-shadow: 0 -8px 28px rgba(0,0,0,0.5);
    pointer-events: auto; transform-origin: bottom;
    animation: csBoxIn 0.2s ease-out;
}
.cs-box.mini { height: auto; }
.cs-box.mini .cs-box-body, .cs-box.mini .cs-box-form { display: none; }
@keyframes csBoxIn { from { opacity: 0; transform: translateY(20px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }

/* ── Mobile: chatbox stays as a docked popup like desktop, just narrower ── */
@media (max-width: 1023px) {
    .cs-boxes {
        position: fixed; bottom: 0; right: 0.5rem;
        flex-direction: row-reverse; gap: 0.4rem; padding: 0;
        pointer-events: none; z-index: 1003;
    }
    .cs-boxes:empty { display: none; }
    .cs-box {
        width: min(var(--cs-chat-w), calc(100vw - 16px));
        height: var(--cs-chat-h); max-height: calc(100vh - 80px);
        border-radius: 12px 12px 0 0; border: 1px solid var(--border); border-bottom: none;
        pointer-events: auto;
        animation: csBoxInMobile 0.22s ease-out;
    }
    /* Only one visible at a time on small screens (no horizontal stacking) */
    .cs-box + .cs-box { display: none; }
    .cs-box-name .nm { max-width: 50vw; }
}
@keyframes csBoxInMobile { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

.cs-box-head {
    height: 44px; flex-shrink: 0; display: flex; align-items: center; gap: 0.5rem;
    padding: 0 0.55rem 0 0.7rem; border-bottom: 1px solid var(--border); cursor: pointer;
    background: linear-gradient(135deg, rgba(124,58,237,0.1) 0%, rgba(76,29,149,0.04) 100%);
}
.cs-box-av-wrap { position: relative; flex-shrink: 0; }
.cs-box-av { width: 28px; height: 28px; border-radius: 50%; background: #2a2d34; display: flex; align-items: center; justify-content: center; font-size: 0.78rem; color: #9ca3af; overflow: hidden; }
.cs-box-av.team { border-radius: 7px; }
.cs-box-av img { width: 100%; height: 100%; object-fit: cover; }
.cs-box-av i.bi-person-fill { font-size: 1.15rem; line-height: 1; margin-bottom: -6px; }
.cs-box-online-dot {
    position: absolute; right: -1px; bottom: -1px;
    width: 9px; height: 9px; border-radius: 50%;
    background: #22c55e; border: 2px solid var(--bg-card);
}
.cs-box-private-badge {
    position: absolute; right: -3px; bottom: -3px;
    width: 14px; height: 14px; border-radius: 50%;
    background: #fbbf24; color: #1e0a3a;
    border: 2px solid var(--bg-card);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.5rem;
    box-shadow: 0 0 0 1px rgba(251,191,36,0.3);
}

/* ── Voice call panel (group only) ── */
.cs-voice {
    display: none;
    background: linear-gradient(135deg, rgba(124,58,237,0.18), rgba(34,197,94,0.08));
    border-bottom: 1px solid rgba(124,58,237,0.3);
    padding: 0.55rem 0.7rem;
    flex-shrink: 0; position: relative; z-index: 5;
    width: 100%; box-sizing: border-box;
    order: 2;  /* after head, before body — guarantees position in flex column */
}
.cs-voice.on { display: block !important; animation: csEmojiIn 0.18s ease-out; }
.cs-voice-head {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.72rem; font-weight: 800; color: #a78bfa;
    text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.45rem;
}
.cs-voice-head i.rec { color: #ef4444; animation: csVoicePulse 1.3s ease-in-out infinite; }
.cs-voice-head .cs-voice-time { margin-left: auto; color: var(--text-muted); font-weight: 600; letter-spacing: 0; text-transform: none; }
@keyframes csVoicePulse { 0%,100%{opacity:1;} 50%{opacity:0.3;} }

.cs-voice-members { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.5rem; }
.cs-voice-member {
    display: flex; align-items: center; gap: 0.35rem;
    background: rgba(255,255,255,0.04); border: 1px solid var(--border);
    border-radius: 99px; padding: 0.15rem 0.55rem 0.15rem 0.2rem;
    font-size: 0.72rem; transition: border-color 0.15s, background 0.15s;
}
.cs-voice-member.speaking { border-color: #22c55e; background: rgba(34,197,94,0.12); box-shadow: 0 0 0 2px rgba(34,197,94,0.2); }
.cs-voice-member.muted .cs-voice-mav { filter: grayscale(1); opacity: 0.6; }
.cs-voice-mav { width: 20px; height: 20px; border-radius: 50%; background: #2a2d34; color: #9ca3af; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; overflow: hidden; }
.cs-voice-mav img { width: 100%; height: 100%; object-fit: cover; }
.cs-voice-member-name { max-width: 90px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text); }
.cs-voice-member-mic { font-size: 0.72rem; color: #f87171; }
.cs-voice-member.speaking .cs-voice-member-mic { color: #22c55e; }

.cs-voice-controls { display: flex; gap: 0.35rem; }
.cs-voice-btn {
    flex: 1; padding: 0.45rem; border: 1px solid var(--border); border-radius: 8px;
    background: rgba(255,255,255,0.03); color: var(--text); font-size: 0.78rem; font-weight: 700;
    cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem;
    transition: all 0.15s;
}
.cs-voice-btn:hover { border-color: var(--accent); color: var(--accent-light); }
.cs-voice-btn.on { background: rgba(239,68,68,0.12); border-color: rgba(239,68,68,0.35); color: #f87171; }
.cs-voice-btn.leave { background: rgba(239,68,68,0.15); border-color: rgba(239,68,68,0.4); color: #f87171; }
.cs-voice-btn.leave:hover { background: rgba(239,68,68,0.25); }

/* Audio host: visible while debugging so you can confirm media is arriving.
   Once stable, switch back to off-screen. */
.cs-voice-audio-host {
    padding: 0.4rem 0.7rem;
    background: rgba(255,255,255,0.02);
    border-top: 1px dashed rgba(255,255,255,0.08);
}
.cs-voice-audio-host:empty { display: none; }
.cs-voice-audio-host audio {
    width: 100%; height: 32px; margin-bottom: 4px;
}

/* ── Incoming call ringing modal ── */
.cs-ring {
    position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
    z-index: 3000; min-width: 320px; max-width: 90vw;
    background: var(--bg-card); border: 1px solid rgba(34,197,94,0.35); border-radius: 16px;
    padding: 1rem 1.2rem; display: none;
    box-shadow: 0 16px 40px rgba(0,0,0,0.55), 0 0 0 4px rgba(34,197,94,0.08);
    animation: csRingIn 0.25s ease-out;
}
.cs-ring.on { display: block; }
@keyframes csRingIn { from { opacity:0; transform: translate(-50%, 24px) scale(0.95);} to {opacity:1; transform: translate(-50%, 0) scale(1);} }
.cs-ring-head { display: flex; align-items: center; gap: 0.85rem; margin-bottom: 0.85rem; }
.cs-ring-av {
    width: 52px; height: 52px; border-radius: 50%; background: #2a2d34;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden;
    color: #9ca3af; font-size: 1.5rem;
    box-shadow: 0 0 0 0 rgba(34,197,94,0.4);
    animation: csRingPulse 1.4s ease-in-out infinite;
}
@keyframes csRingPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0.4); }
    50%      { box-shadow: 0 0 0 12px rgba(34,197,94,0); }
}
.cs-ring-av img { width: 100%; height: 100%; object-fit: cover; }
.cs-ring-info { flex: 1; min-width: 0; }
.cs-ring-name { font-size: 1rem; font-weight: 800; color: var(--text); }
.cs-ring-sub  { font-size: 0.78rem; color: var(--text-muted); margin-top: 1px; display: flex; align-items: center; gap: 0.35rem; }
.cs-ring-sub i { color: #22c55e; animation: csRingPulse 1.4s ease-in-out infinite; }
.cs-ring-actions { display: flex; gap: 0.55rem; }
.cs-ring-btn {
    flex: 1; padding: 0.7rem 0.85rem; border-radius: 10px;
    font-size: 0.88rem; font-weight: 800; cursor: pointer; font-family: inherit;
    border: none; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem;
    transition: transform 0.12s, filter 0.12s;
}
.cs-ring-btn:hover { transform: translateY(-1px); filter: brightness(1.1); }
.cs-ring-btn.accept  { background: linear-gradient(135deg,#22c55e,#15803d); color: #fff; }
.cs-ring-btn.decline { background: linear-gradient(135deg,#ef4444,#b91c1c); color: #fff; }
.cs-ring-btn.listen  { background: rgba(96,165,250,0.15); color: #93c5fd; border: 1px solid rgba(96,165,250,0.3); }

/* ── Caller "ringing" state inside chatbox ── */
.cs-calling {
    background: linear-gradient(135deg, rgba(96,165,250,0.18), rgba(34,197,94,0.04));
    border-bottom: 1px solid rgba(96,165,250,0.3);
    padding: 0.7rem 0.85rem; display: none; flex-shrink: 0;
}
.cs-calling.on { display: flex; align-items: center; gap: 0.65rem; animation: csEmojiIn 0.18s ease-out; }
.cs-calling-spinner { color: #60a5fa; animation: csCallSpin 1.2s linear infinite; }
@keyframes csCallSpin { from { transform: rotate(0); } to { transform: rotate(360deg); } }
.cs-calling-text { flex: 1; font-size: 0.82rem; color: var(--text); }
.cs-calling-text .name { font-weight: 800; color: #93c5fd; }
.cs-calling-time { font-size: 0.72rem; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.cs-calling-cancel {
    background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3);
    border-radius: 8px; padding: 0.4rem 0.75rem; font-size: 0.75rem; font-weight: 800;
    cursor: pointer; font-family: inherit;
}
.cs-calling-cancel:hover { background: rgba(239,68,68,0.25); }

/* Quick voice button beside settings */
.cs-voice-quick { color: #34d399 !important; }
.cs-voice-quick:hover { background: rgba(52,211,153,0.12) !important; }
.cs-voice-quick.live { color: #ef4444 !important; animation: csQuickPulse 1.4s ease-in-out infinite; }
.cs-voice-quick.live:hover { background: rgba(239,68,68,0.12) !important; }
@keyframes csQuickPulse {
    0%, 100% { filter: drop-shadow(0 0 0 rgba(239,68,68,0)); }
    50%      { filter: drop-shadow(0 0 4px rgba(239,68,68,0.7)); }
}

/* ── Settings menu (gear dropdown in chatbox header) ── */
.cs-settings { position: relative; }
.cs-settings-menu {
    position: absolute; top: calc(100% + 4px); right: 0;
    background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px;
    padding: 0.35rem; min-width: 200px; z-index: 20;
    box-shadow: 0 10px 32px rgba(0,0,0,0.55);
    display: none;
    animation: csEmojiIn 0.15s ease-out;
}
.cs-settings-menu.on { display: block; }
.cs-settings-menu button, .cs-settings-menu a {
    display: flex; align-items: center; gap: 0.55rem; width: 100%;
    padding: 0.55rem 0.85rem; background: transparent; border: none;
    color: var(--text); font-size: 0.82rem; font-family: inherit; cursor: pointer;
    text-align: left; border-radius: 7px; text-decoration: none; box-sizing: border-box;
}
.cs-settings-menu button:hover, .cs-settings-menu a:hover { background: rgba(124,58,237,0.12); color: var(--accent-light); }
.cs-settings-menu button.danger { color: #f87171; }
.cs-settings-menu button.danger:hover { background: rgba(239,68,68,0.1); }
.cs-settings-menu i { width: 16px; font-size: 0.92rem; text-align: center; }
.cs-settings-divider { height: 1px; background: var(--border); margin: 0.3rem 0.4rem; }
.cs-settings-label { font-size: 0.58rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-muted); padding: 0.45rem 0.85rem 0.2rem; }
.cs-settings-item-sub { font-size: 0.65rem; color: var(--text-muted); font-weight: 500; margin-left: auto; }

/* Group members panel (slides down from header) */
.cs-members {
    position: absolute; top: 44px; left: 0; right: 0; bottom: 0; z-index: 18;
    background: var(--bg-card); display: none; flex-direction: column;
    animation: csEmojiIn 0.18s ease-out;
}
.cs-members.on { display: flex; }
.cs-members-head { display: flex; align-items: center; gap: 0.5rem; padding: 0.7rem 0.85rem; border-bottom: 1px solid var(--border); }
.cs-members-title { flex: 1; font-size: 0.85rem; font-weight: 800; }
.cs-members-list { flex: 1; overflow-y: auto; padding: 0.3rem 0.4rem; }
.cs-member-row { display: flex; align-items: center; gap: 0.55rem; padding: 0.45rem 0.55rem; border-radius: 7px; }
.cs-member-row:hover { background: rgba(255,255,255,0.03); }
.cs-member-name { flex: 1; font-size: 0.82rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cs-member-you { font-size: 0.64rem; background: rgba(124,58,237,0.15); color: var(--accent-light); padding: 0.1rem 0.45rem; border-radius: 4px; font-weight: 800; }
.cs-member-admin { font-size: 0.6rem; background: rgba(251,191,36,0.12); color: #fbbf24; padding: 0.1rem 0.4rem; border-radius: 4px; font-weight: 800; }
.cs-member-online { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; flex-shrink: 0; }
.cs-box-who { flex: 1; min-width: 0; }
.cs-box-name { font-size: 0.82rem; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 0.25rem; }
.cs-box-name .nm { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 130px; }
.cs-box-name i.verified { color: #60a5fa; font-size: 0.72rem; flex-shrink: 0; }
.cs-box-sub { font-size: 0.65rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cs-box-btn { background: transparent; border: none; color: var(--text-muted); font-size: 0.9rem; cursor: pointer; padding: 0; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; border-radius: 5px; transition: background 0.15s, color 0.15s; }
.cs-box-btn:hover { color: var(--text); background: rgba(255,255,255,0.06); }

.cs-box-body { flex: 1; min-height: 0; overflow-y: auto; padding: 0.65rem 0.75rem; display: flex; flex-direction: column; gap: 0.3rem; }
.cs-box-body::-webkit-scrollbar { width: 5px; }
.cs-box-body::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }

.cs-msg-row { display: flex; align-items: center; gap: 0.35rem; max-width: 85%; position: relative; }
.cs-msg-row.mine  { align-self: flex-end; flex-direction: row-reverse; }
.cs-msg-row.theirs { align-self: flex-start; }
.cs-msg { padding: 0.45rem 0.75rem; border-radius: 14px; font-size: 0.84rem; line-height: 1.4; word-wrap: break-word; white-space: pre-wrap; position: relative; cursor: pointer; transition: transform 0.12s; }
.cs-msg.mine { background: var(--accent); color: #fff; border-bottom-right-radius: 4px; }
.cs-msg.theirs { background: rgba(255,255,255,0.06); color: var(--text); border-bottom-left-radius: 4px; }
.cs-msg-row:hover .cs-msg-quick { opacity: 1; }
.cs-msg-quick { opacity: 0; transition: opacity 0.15s; display: flex; gap: 2px; flex-shrink: 0; }
.cs-msg-quick button {
    width: 26px; height: 26px; border-radius: 50%; background: var(--bg-card); border: 1px solid var(--border);
    color: var(--text-muted); font-size: 0.75rem; cursor: pointer;
    display: flex; align-items: center; justify-content: center; transition: color 0.12s, background 0.12s;
}
.cs-msg-quick button:hover { color: var(--accent-light); background: rgba(124,58,237,0.12); }

/* Reply quote preview shown above the message bubble */
.cs-reply-quote {
    display: flex; align-items: stretch; gap: 0.5rem;
    background: rgba(255,255,255,0.04);
    border-left: 3px solid var(--accent);
    border-radius: 8px; padding: 0.35rem 0.6rem;
    font-size: 0.72rem; margin-bottom: 3px;
    max-width: 85%; align-self: flex-start; cursor: pointer;
}
.cs-reply-quote.mine { align-self: flex-end; }
.cs-reply-quote-body { flex: 1; min-width: 0; }
.cs-reply-quote-author { font-weight: 800; color: var(--accent-light); font-size: 0.66rem; }
.cs-reply-quote-text { color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Composer reply preview */
.cs-compose-reply {
    display: none; align-items: center; gap: 0.5rem;
    padding: 0.45rem 0.7rem; border-top: 1px solid var(--border);
    background: rgba(124,58,237,0.05); font-size: 0.72rem;
}
.cs-compose-reply.on { display: flex; }
.cs-compose-reply-body { flex: 1; min-width: 0; }
.cs-compose-reply-label { color: var(--accent-light); font-weight: 800; font-size: 0.64rem; text-transform: uppercase; letter-spacing: 1px; }
.cs-compose-reply-text { color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cs-compose-reply-close { background: transparent; border: none; color: var(--text-muted); font-size: 0.9rem; cursor: pointer; padding: 2px 6px; border-radius: 4px; }
.cs-compose-reply-close:hover { color: var(--text); background: rgba(255,255,255,0.05); }

/* Context menu (right-click) */
.cs-ctx {
    position: fixed; z-index: 2100; display: none;
    background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px;
    padding: 0.3rem; min-width: 170px;
    box-shadow: 0 10px 32px rgba(0,0,0,0.55);
    animation: csEmojiIn 0.14s ease-out;
}
.cs-ctx.on { display: block; }
.cs-ctx button {
    display: flex; align-items: center; gap: 0.55rem; width: 100%;
    padding: 0.5rem 0.75rem; background: transparent; border: none;
    color: var(--text); font-size: 0.82rem; font-family: inherit; cursor: pointer;
    text-align: left; border-radius: 7px;
}
.cs-ctx button:hover { background: rgba(124,58,237,0.12); color: var(--accent-light); }
.cs-ctx button.danger { color: #f87171; }
.cs-ctx button.danger:hover { background: rgba(239,68,68,0.1); }
.cs-ctx button i { width: 14px; font-size: 0.9rem; }

/* Highlight flash when jumping to a replied-to message */
.cs-msg.cs-highlight { animation: csHighlight 1.6s ease-out; }
@keyframes csHighlight {
    0%   { background-color: rgba(251,191,36,0.35); }
    100% { }
}
.cs-msg-time { font-size: 0.58rem; color: var(--text-muted); margin: 0 0.35rem 0.3rem; }
.cs-msg-time.mine { align-self: flex-end; }
.cs-msg-time.theirs { align-self: flex-start; }
.cs-day-sep { text-align: center; font-size: 0.62rem; color: var(--text-muted); padding: 0.4rem 0 0.15rem; font-weight: 700; }

.cs-box-form { display: flex; gap: 0.4rem; padding: 0.5rem 0.6rem; border-top: 1px solid var(--border); flex-shrink: 0; background: var(--bg-card); position: relative; }

/* ── Emoji picker ── */
.cs-emoji-btn { background: transparent; border: none; color: var(--text-muted); font-size: 1.2rem; cursor: pointer; padding: 0 0.3rem; display: flex; align-items: center; justify-content: center; align-self: flex-end; width: 32px; height: 32px; border-radius: 50%; transition: background 0.15s, color 0.15s; flex-shrink: 0; }
.cs-emoji-btn:hover { background: rgba(255,255,255,0.06); color: var(--accent-light); }
.cs-emoji-panel {
    position: absolute; bottom: calc(100% + 4px); right: 0.6rem;
    width: 240px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px;
    padding: 0.5rem; display: none; z-index: 10;
    box-shadow: 0 8px 28px rgba(0,0,0,0.6); grid-template-columns: repeat(8, 1fr); gap: 2px;
    max-height: 210px; overflow-y: auto;
}
.cs-emoji-panel.on { display: grid; animation: csEmojiIn 0.15s ease-out; }
@keyframes csEmojiIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
.cs-emoji-btn-item { background: transparent; border: none; font-size: 1.15rem; cursor: pointer; padding: 4px; border-radius: 6px; line-height: 1; transition: background 0.12s; }
.cs-emoji-btn-item:hover { background: rgba(124,58,237,0.15); }
.cs-emoji-cat { grid-column: 1 / -1; font-size: 0.58rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); padding: 0.3rem 0.35rem 0.15rem; }
.cs-box-input { flex: 1; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 18px; color: var(--text); padding: 0.45rem 0.85rem; font-size: 0.84rem; font-family: inherit; outline: none; resize: none; max-height: 90px; line-height: 1.3; }
.cs-box-input:focus { border-color: var(--accent); background: rgba(124,58,237,0.06); }
.cs-box-send { background: var(--accent); color: #fff; border: none; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; cursor: pointer; flex-shrink: 0; align-self: flex-end; }
.cs-box-send:disabled { background: rgba(124,58,237,0.3); cursor: not-allowed; }

/* ── Image attach ── */
.cs-attach-btn { background: transparent; border: none; color: var(--text-muted); font-size: 1.05rem; cursor: pointer; padding: 0 0.3rem; display: flex; align-items: center; justify-content: center; align-self: flex-end; width: 32px; height: 32px; border-radius: 50%; transition: background 0.15s, color 0.15s; flex-shrink: 0; }
.cs-attach-btn:hover { background: rgba(52,211,153,0.12); color: #34d399; }
.cs-attach-preview {
    display: none; position: relative; margin: 0.4rem 0.6rem 0;
    border-radius: 10px; overflow: hidden;
    border: 1px solid var(--border); background: rgba(255,255,255,0.03);
}
.cs-attach-preview.on { display: block; }
.cs-attach-preview img { width: 100%; max-height: 160px; object-fit: cover; display: block; }
.cs-attach-preview button {
    position: absolute; top: 0.4rem; right: 0.4rem;
    width: 24px; height: 24px; border-radius: 50%;
    background: rgba(0,0,0,0.75); color: #fff; border: none;
    font-size: 0.85rem; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}
.cs-attach-preview button:hover { background: rgba(239,68,68,0.9); }

/* ── Image bubble ── */
.cs-msg-img {
    display: block; max-width: 100%; max-height: 240px;
    border-radius: 10px; cursor: zoom-in;
    object-fit: cover; background: rgba(0,0,0,0.15);
}
.cs-msg-img-wrap { padding: 3px; border-radius: 14px; overflow: hidden; max-width: 85%; }
.cs-msg-img-wrap.mine { align-self: flex-end; }
.cs-msg-img-wrap.theirs { align-self: flex-start; }

/* ── Lightbox for images ── */
.cs-lightbox {
    position: fixed; inset: 0; z-index: 3000;
    background: rgba(0,0,0,0.88); display: none;
    align-items: center; justify-content: center;
    padding: 1rem; animation: csFade 0.18s ease-out;
}
.cs-lightbox.on { display: flex; }
.cs-lightbox img { max-width: 95%; max-height: 92vh; border-radius: 8px; box-shadow: 0 10px 40px rgba(0,0,0,0.6); }
.cs-lightbox-close {
    position: absolute; top: 1rem; right: 1rem;
    width: 40px; height: 40px; border-radius: 50%;
    background: rgba(255,255,255,0.08); color: #fff; border: none;
    font-size: 1.1rem; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}
.cs-lightbox-close:hover { background: rgba(255,255,255,0.18); }
@keyframes csFade { from { opacity: 0; } to { opacity: 1; } }

/* ── Links in messages ── */
.cs-msg a { color: #fff; text-decoration: underline; font-weight: 600; word-break: break-all; }
.cs-msg.theirs a { color: var(--accent-light); }

/* ── Seen receipt ── */
.cs-seen { align-self: flex-end; font-size: 0.6rem; color: var(--text-muted); padding: 0 0.5rem 0.35rem; display: flex; align-items: center; gap: 0.2rem; }
.cs-seen i { font-size: 0.7rem; color: #60a5fa; }

/* ── Typing indicator ── */
.cs-typing {
    align-self: flex-start; display: none; align-items: center; gap: 0.35rem;
    background: rgba(255,255,255,0.06); border-radius: 14px;
    padding: 0.4rem 0.75rem; margin-bottom: 0.35rem; max-width: 80%;
}
.cs-typing.on { display: inline-flex; animation: csTypingIn 0.2s ease-out; }
@keyframes csTypingIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
.cs-typing-dots { display: inline-flex; gap: 3px; }
.cs-typing-dots span {
    width: 6px; height: 6px; border-radius: 50%; background: var(--accent-light);
    animation: csDotBounce 1.3s ease-in-out infinite;
}
.cs-typing-dots span:nth-child(2) { animation-delay: 0.18s; }
.cs-typing-dots span:nth-child(3) { animation-delay: 0.36s; }
@keyframes csDotBounce {
    0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
    30%           { transform: translateY(-6px); opacity: 1; }
}

/* ── Scroll-to-bottom pill ── */
.cs-scroll-btn {
    position: absolute; right: 12px; bottom: 70px; z-index: 3;
    background: var(--accent); color: #fff; border: none;
    width: 34px; height: 34px; border-radius: 50%;
    display: none; align-items: center; justify-content: center;
    cursor: pointer; font-size: 1rem;
    box-shadow: 0 4px 14px rgba(124,58,237,0.45);
    transition: transform 0.15s;
}
.cs-scroll-btn.on { display: flex; animation: csEmojiIn 0.18s ease-out; }
.cs-scroll-btn:hover { transform: translateY(-2px); }
.cs-scroll-btn-badge {
    position: absolute; top: -4px; right: -4px; min-width: 18px; height: 18px;
    background: #ef4444; color: #fff; font-size: 0.6rem; font-weight: 800;
    padding: 0 4px; border-radius: 99px; display: flex; align-items: center; justify-content: center;
    border: 2px solid var(--bg-card);
}

/* ── Sound toggle in header ── */
.cs-box-sound { color: var(--text-muted); }
.cs-box-sound.muted i::before { content: "\f2e0"; /* bi-volume-mute-fill */ }

/* ── New messages divider ── */
.cs-new-divider {
    display: flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0;
    font-size: 0.6rem; font-weight: 800; color: #ef4444; text-transform: uppercase; letter-spacing: 1.5px;
}
.cs-new-divider::before, .cs-new-divider::after { content: ''; flex: 1; height: 1px; background: rgba(239,68,68,0.25); }

/* ── Emoji effects overlay ── */
.cs-fx { position: absolute; inset: 0; pointer-events: none; overflow: hidden; z-index: 5; }
.cs-fx-particle {
    position: absolute; font-size: 1.8rem; line-height: 1;
    animation-fill-mode: forwards; animation-timing-function: ease-out;
    will-change: transform, opacity;
}
@keyframes csFxRise {
    0%   { transform: translate(0, 0) scale(0.6) rotate(0deg);   opacity: 0; }
    15%  { transform: translate(var(--dx1, 0px), -40px) scale(1) rotate(var(--r1, 0deg));  opacity: 1; }
    100% { transform: translate(var(--dx2, 0px), -280px) scale(1.3) rotate(var(--r2, 0deg)); opacity: 0; }
}
@keyframes csFxFall {
    0%   { transform: translate(0, -20px) rotate(0deg);    opacity: 0; }
    10%  { opacity: 1; }
    100% { transform: translate(var(--dx, 0px), 420px) rotate(var(--r, 360deg)); opacity: 0; }
}
@keyframes csFxBurst {
    0%   { transform: scale(0) rotate(0deg);   opacity: 0; }
    20%  { transform: scale(1.4) rotate(10deg); opacity: 1; }
    60%  { transform: scale(1) rotate(-5deg);   opacity: 1; }
    100% { transform: translate(var(--dx, 0px), var(--dy, 0px)) scale(0.3) rotate(20deg); opacity: 0; }
}
@keyframes csFxBounce {
    0%   { transform: translate(0, 60px) scale(0.3);    opacity: 0; }
    50%  { transform: translate(0, -40px) scale(1.3);   opacity: 1; }
    80%  { transform: translate(0, 0) scale(1);          opacity: 1; }
    100% { transform: translate(0, -8px) scale(1);       opacity: 0; }
}

/* Glow pulse ring on the box when a big effect hits */
.cs-box.cs-fx-flash { animation: csFxFlash 0.8s ease-out; }
@keyframes csFxFlash {
    0%   { box-shadow: 0 -8px 28px rgba(0,0,0,0.5); }
    30%  { box-shadow: 0 0 0 3px var(--fx-color, #f87171), 0 -8px 36px var(--fx-color, #f87171); }
    100% { box-shadow: 0 -8px 28px rgba(0,0,0,0.5); }
}

/* ── Guest login modal ── */
.cs-modal {
    position: fixed; inset: 0; z-index: 2000;
    background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);
    display: none; align-items: center; justify-content: center;
    padding: 1.5rem; animation: csFade 0.18s ease-out;
}
.cs-modal.on { display: flex; }
@keyframes csFade { from { opacity: 0; } to { opacity: 1; } }
.cs-modal-card {
    position: relative; max-width: 380px; width: 100%;
    background: var(--bg-card); border: 1px solid var(--border); border-radius: 18px;
    padding: 2rem 1.75rem 1.75rem; text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.7);
    animation: csSlide 0.22s ease-out;
}
@keyframes csSlide { from { opacity: 0; transform: translateY(16px) scale(0.96); } to { opacity: 1; transform: translateY(0) scale(1); } }
.cs-modal-close { position: absolute; top: 0.85rem; right: 0.85rem; background: transparent; border: none; color: var(--text-muted); font-size: 1.1rem; cursor: pointer; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: background 0.15s, color 0.15s; }
.cs-modal-close:hover { background: rgba(255,255,255,0.06); color: var(--text); }
.cs-modal-icon {
    width: 64px; height: 64px; margin: 0 auto 1rem; border-radius: 50%;
    background: linear-gradient(135deg, #7c3aed, #4c1d95);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.7rem; color: #fff;
    box-shadow: 0 8px 28px rgba(124,58,237,0.45);
}
.cs-modal-title { font-size: 1.15rem; font-weight: 900; color: var(--text); margin-bottom: 0.45rem; }
.cs-modal-sub { font-size: 0.88rem; color: var(--text-muted); line-height: 1.55; margin-bottom: 1.35rem; }
.cs-modal-actions { display: flex; flex-direction: column; gap: 0.55rem; }
.cs-modal-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
    padding: 0.7rem 1rem; border-radius: 10px;
    font-size: 0.88rem; font-weight: 800; text-decoration: none;
    transition: all 0.15s; cursor: pointer; font-family: inherit; border: 1px solid transparent;
}
.cs-modal-btn.primary { background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #fff; box-shadow: 0 4px 14px rgba(124,58,237,0.4); }
.cs-modal-btn.primary:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(124,58,237,0.55); }
.cs-modal-btn.ghost { background: transparent; color: var(--text); border-color: var(--border); }
.cs-modal-btn.ghost:hover { border-color: var(--accent); color: var(--accent-light); }
</style>

<!-- ── Sidebar (users list only) ── -->
<aside class="cs-side cs-collapsed" id="csSide">
    <button type="button" class="cs-toggle" id="csToggle" onclick="csToggleSide()" title="Toggle players">
        <i class="bi bi-chevron-left"></i>
        <span class="cs-tab-badge hidden" id="csTabBadge">0</span>
    </button>
    <div class="cs-head">
        <div class="cs-head-title"><i class="bi bi-people-fill"></i> Apex Cybernet Players</div>
        <span class="cs-head-count" id="csCount"></span>
        <button class="cs-head-btn cs-tips-btn" title="Chat tips" onclick="csToggleTips(event)"><i class="bi bi-question-circle"></i></button>
    </div>
    <div class="cs-tips" id="csTips">
        <div class="cs-tips-head">
            <strong>Chat tips</strong>
            <button onclick="csToggleTips()" style="background:transparent;border:none;color:var(--text-muted);cursor:pointer;padding:0;font-size:0.85rem;"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="cs-tip"><i class="bi bi-lock-fill" style="color:#fbbf24;"></i><div><strong>Groups are private</strong> — only members of a group can see it in their sidebar. Use the group icon next to search to create one.</div></div>
        <div class="cs-tip"><i class="bi bi-circle-fill" style="color:#22c55e;"></i><div><strong>Green dot</strong> next to an avatar means that player is active in the last 3 minutes.</div></div>
        <div class="cs-tip"><i class="bi bi-magic" style="color:#a78bfa;"></i><div>Send <span style="font-size:0.95rem;">🎉 ❤️ 🔥 ✨ 🏆</span> to trigger <strong>visual effects</strong> in the chat.</div></div>
        <div class="cs-tip"><i class="bi bi-reply-fill" style="color:#60a5fa;"></i><div><strong>Right-click</strong> a message (or <strong>long-press</strong> on mobile) to reply, copy, react, or unsend your own.</div></div>
        <div class="cs-tip"><i class="bi bi-clock-history" style="color:#34d399;"></i><div>Open chats and unsent drafts <strong>persist across pages</strong> — you won't lose what you're typing when you navigate.</div></div>
        <div class="cs-tip"><i class="bi bi-chevron-double-right" style="color:var(--text-muted);"></i><div>Click the <strong>arrow tab</strong> on the sidebar edge to collapse or expand the player list.</div></div>
    </div>
    <div class="cs-search-wrap" style="display:flex;gap:0.4rem;align-items:center;">
        <input type="text" class="cs-search" id="csSearch" placeholder="Search players…" oninput="csFilter()" style="flex:1;">
        <button type="button" class="cs-head-btn" title="Create group" onclick="csOpenNewGroup()" style="width:34px;height:34px;background:rgba(124,58,237,0.15);color:var(--accent-light);border:1px solid rgba(124,58,237,0.3);border-radius:8px;flex-shrink:0;"><i class="bi bi-people-fill"></i></button>
    </div>
    <div class="cs-notif-prompt" id="csNotifPrompt">
        <i class="bi bi-bell-fill"></i>
        <div style="flex:1;">Get message notifications</div>
        <div class="cs-notif-prompt-actions">
            <button onclick="csEnableNotifs()">Enable</button>
            <button class="ghost" onclick="csDismissNotifs()">×</button>
        </div>
    </div>
    <div class="cs-list" id="csList">
        <div class="cs-empty"><i class="bi bi-hourglass-split"></i>Loading…</div>
    </div>
</aside>

<!-- ── Mobile launcher ── -->
<button type="button" class="cs-launcher" onclick="csOpen()" aria-label="Open chat">
    <i class="bi bi-chat-dots-fill"></i>
    <span class="cs-badge hidden" id="csBadge">0</span>
</button>

<!-- ── Chat popup stack (bottom-right, left of sidebar) ── -->
<div class="cs-boxes" id="csBoxes"></div>

<!-- ── New Group modal ── -->
<div class="cs-modal" id="csNewGroup" onclick="csCloseNewGroup(event)">
    <div class="cs-modal-card" style="max-width:440px;" onclick="event.stopPropagation()">
        <button class="cs-modal-close" onclick="csCloseNewGroup()"><i class="bi bi-x-lg"></i></button>
        <div class="cs-modal-icon"><i class="bi bi-people-fill"></i></div>
        <div class="cs-modal-title">Create a group chat</div>
        <div class="cs-modal-sub">Pick a name and add at least one other player.</div>
        <div style="display:flex;align-items:flex-start;gap:0.5rem;background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.25);border-radius:8px;padding:0.55rem 0.75rem;margin-bottom:0.85rem;font-size:0.72rem;color:#fde68a;text-align:left;">
            <i class="bi bi-lock-fill" style="margin-top:2px;"></i>
            <div><strong>Private group.</strong> Only the members you add can see this group in their sidebar. Nobody else on Apex Cybernet will know it exists.</div>
        </div>
        <input type="text" id="csNgName" maxlength="80" placeholder="Group name…"
            style="width:100%;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:0.6rem 0.85rem;font-size:0.9rem;font-family:inherit;outline:none;margin-bottom:0.75rem;">
        <input type="text" id="csNgSearch" placeholder="Search players to add…"
            style="width:100%;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:0.55rem 0.85rem;font-size:0.85rem;font-family:inherit;outline:none;margin-bottom:0.5rem;"
            oninput="csNgFilter()">
        <div id="csNgList" style="max-height:260px;overflow-y:auto;border:1px solid var(--border);border-radius:10px;padding:0.3rem;text-align:left;"></div>
        <div style="display:flex;gap:0.5rem;margin-top:1rem;">
            <button type="button" class="cs-modal-btn ghost" onclick="csCloseNewGroup()" style="flex:1;">Cancel</button>
            <button type="button" class="cs-modal-btn primary" onclick="csCreateGroup()" style="flex:1;" id="csNgCreateBtn"><i class="bi bi-check-lg"></i> Create</button>
        </div>
    </div>
</div>

<!-- ── Incoming voice call modal (one global instance) ── -->
<div class="cs-ring" id="csRing" data-invite="" data-caller="" data-room="">
    <div class="cs-ring-head">
        <div class="cs-ring-av" id="csRingAv"><i class="bi bi-person-fill"></i></div>
        <div class="cs-ring-info">
            <div class="cs-ring-name" id="csRingName">Someone</div>
            <div class="cs-ring-sub"><i class="bi bi-telephone-inbound-fill"></i> Incoming voice call…</div>
        </div>
    </div>
    <div class="cs-ring-actions">
        <button class="cs-ring-btn decline" onclick="csRingRespond('decline')"><i class="bi bi-telephone-x-fill"></i> Decline</button>
        <button class="cs-ring-btn listen"  onclick="csRingRespond('listen')"><i class="bi bi-headphones"></i> Listen</button>
        <button class="cs-ring-btn accept"  onclick="csRingRespond('accept')"><i class="bi bi-telephone-fill"></i> Accept</button>
    </div>
</div>

<!-- ── Toast stack for incoming messages ── -->
<div class="cs-toast-stack" id="csToasts"></div>

<!-- ── Image lightbox ── -->
<div class="cs-lightbox" id="csLightbox" onclick="csHideLightbox()">
    <button class="cs-lightbox-close" onclick="csHideLightbox()"><i class="bi bi-x-lg"></i></button>
    <img id="csLightboxImg" src="" alt="">
</div>

<!-- ── Shared message context menu (right-click / long-press) ── -->
<div class="cs-ctx" id="csCtx" data-msg="" data-peer="" data-mine="0">
    <button type="button" onclick="csCtxAction('reply')"><i class="bi bi-reply-fill"></i> Reply</button>
    <button type="button" onclick="csCtxAction('copy')"><i class="bi bi-clipboard"></i> Copy text</button>
    <button type="button" onclick="csCtxAction('react-heart')"><i class="bi bi-heart-fill" style="color:#f472b6;"></i> React ❤️</button>
    <button type="button" onclick="csCtxAction('react-laugh')"><i class="bi bi-emoji-laughing" style="color:#fbbf24;"></i> React 😂</button>
    <button type="button" onclick="csCtxAction('react-fire')"><i class="bi bi-fire" style="color:#f97316;"></i> React 🔥</button>
    <button type="button" class="danger" id="csCtxUnsend" onclick="csCtxAction('unsend')"><i class="bi bi-trash-fill"></i> Unsend</button>
</div>

<!-- ── Guest login modal ── -->
<div class="cs-modal" id="csModal" onclick="csCloseModal(event)">
    <div class="cs-modal-card" onclick="event.stopPropagation()">
        <button class="cs-modal-close" onclick="csCloseModal()"><i class="bi bi-x-lg"></i></button>
        <div class="cs-modal-icon"><i class="bi bi-chat-heart-fill"></i></div>
        <div class="cs-modal-title" id="csModalTitle">Join the conversation</div>
        <div class="cs-modal-sub" id="csModalSub">Log in or create an account to chat with Apex Cybernet players.</div>
        <div class="cs-modal-actions">
            <a href="<?= base_url('login.php') ?>" class="cs-modal-btn primary"><i class="bi bi-box-arrow-in-right"></i> Log In</a>
            <a href="<?= base_url('register.php') ?>" class="cs-modal-btn ghost"><i class="bi bi-person-plus-fill"></i> Create Account</a>
        </div>
    </div>
</div>

<script>
(function() {
    var API_USERS    = '<?= base_url('api/chat-users.php') ?>';
    var API_MESSAGES = '<?= base_url('api/chat-messages.php') ?>';
    var BASE         = '<?= rtrim(base_url(), '/') ?>';
    var POLL_USERS_MS = 15000;
    var POLL_BOX_MS   = 3000;
    var ME       = <?= $cs_me ?>;
    var IS_GUEST = <?= $cs_is_guest ? 'true' : 'false' ?>;
    var LOGIN_URL    = '<?= base_url('login.php') ?>';
    var REGISTER_URL = '<?= base_url('register.php') ?>';
    var MAX_OPEN_BOXES = 3;

    var userTimer = null;
    var usersCache  = [];
    var groupsCache = [];
    // openBoxes keyed by numeric id: positive = user peer id, negative = -(group id)
    var openBoxes   = {};
    function gKey(gid)     { return -Math.abs(parseInt(gid)); }
    function isGroupId(k)  { return parseInt(k) < 0; }
    function realGid(k)    { return Math.abs(parseInt(k)); }

    /* ── Persistence across navigation ── */
    var LS_BOXES = 'cs-open-boxes';
    function getPersistedBoxes() {
        try { return JSON.parse(localStorage.getItem(LS_BOXES) || '[]') || []; }
        catch (e) { return []; }
    }
    function savePersistedBoxes() {
        var arr = Object.keys(openBoxes).map(function(id) {
            return { id: parseInt(id), mini: !!openBoxes[id].mini };
        });
        try { localStorage.setItem(LS_BOXES, JSON.stringify(arr)); } catch (e) {}
    }
    function draftKey(peerId) { return 'cs-draft-' + peerId; }
    function loadDraft(peerId) { try { return localStorage.getItem(draftKey(peerId)) || ''; } catch (e) { return ''; } }
    function saveDraft(peerId, text) {
        try {
            if (text && text.trim() !== '') localStorage.setItem(draftKey(peerId), text);
            else localStorage.removeItem(draftKey(peerId));
        } catch (e) {}
    }

    function isDesktop() { return window.innerWidth >= 1024; }
    // Always mark body so it reserves peek space for the sidebar
    document.body.classList.add('cs-has-side');

    /* ── Sidebar open/close ── */
    window.csOpen = function() {
        var side = document.getElementById('csSide');
        side.classList.remove('cs-collapsed');
        side.classList.add('cs-open');
        if (isDesktop()) document.body.classList.add('cs-enabled');
        try { localStorage.setItem('cs-collapsed', '0'); } catch (e) {}
        csStart();
    };
    window.csCollapse = function() {
        var side = document.getElementById('csSide');
        side.classList.add('cs-collapsed');
        side.classList.remove('cs-open');
        document.body.classList.remove('cs-enabled');
        try { localStorage.setItem('cs-collapsed', '1'); } catch (e) {}
    };
    window.csToggleSide = function() {
        var side = document.getElementById('csSide');
        if (side.classList.contains('cs-collapsed')) csOpen();
        else csCollapse();
    };

    window.csToggleTips = function(ev) {
        if (ev) ev.stopPropagation();
        document.getElementById('csTips').classList.toggle('on');
    };
    document.addEventListener('click', function(e) {
        var tips = document.getElementById('csTips');
        if (tips && tips.classList.contains('on') && !e.target.closest('.cs-tips') && !e.target.closest('.cs-tips-btn')) {
            tips.classList.remove('on');
        }
    });

    function csStart() {
        csLoadUsers();
        clearInterval(userTimer);
        userTimer = setInterval(csLoadUsers, POLL_USERS_MS);
    }

    /* ── Helpers ── */
    function fmtTime(dt) {
        if (!dt) return '';
        var d = new Date(dt.replace(' ', 'T'));
        var diff = (Date.now() - d.getTime()) / 1000;
        if (diff < 60) return 'now';
        if (diff < 3600) return Math.floor(diff/60) + 'm';
        if (diff < 86400) return Math.floor(diff/3600) + 'h';
        if (diff < 604800) return Math.floor(diff/86400) + 'd';
        return d.toLocaleDateString([], {month:'short',day:'numeric'});
    }
    function fmtClock(dt) {
        if (!dt) return '';
        var d = new Date(dt.replace(' ', 'T'));
        return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    }
    function fmtDay(dt) {
        var d = new Date(dt.replace(' ', 'T'));
        var today = new Date();
        var yest  = new Date(); yest.setDate(yest.getDate() - 1);
        if (d.toDateString() === today.toDateString()) return 'Today';
        if (d.toDateString() === yest.toDateString())  return 'Yesterday';
        return d.toLocaleDateString([], {month:'long',day:'numeric',year:'numeric'});
    }
    function esc(s) { return String(s||'').replace(/[&<>"]/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }

    // Auto-link URLs in messages
    function linkify(text) {
        var html = esc(text);
        var urlRe = /(https?:\/\/[^\s<]+[^\s<.,!?;:)])/g;
        return html.replace(urlRe, function(u) {
            var safe = u.replace(/"/g, '%22');
            return '<a href="' + safe + '" target="_blank" rel="noopener noreferrer">' + u + '</a>';
        });
    }
    function initials(n) { return (n||'?').trim().slice(0,2).toUpperCase(); }
    function avImg(u) {
        return u.profile_picture
            ? '<img src="' + BASE + '/' + esc(u.profile_picture) + '">'
            : '<i class="bi bi-person-fill"></i>';
    }
    function flairFor(u) {
        var isTeam = u.ref_type === 'team';
        if (u.is_verified == 1 && !u.flair_team) {
            return '<span class="cs-flair admin"><i class="bi bi-shield-fill-check"></i>Official</span>';
        }
        if (u.flair_team) {
            return '<span class="cs-flair ' + (isTeam?'team':'solo') + '" title="' + esc(u.flair_team) + '"><i class="bi bi-' + (isTeam?'people-fill':'person-fill') + '"></i>' + esc(u.flair_team) + '</span>';
        }
        return '';
    }

    /* ── Users list ── */
    /* ── Notifications (Facebook-style) ── */
    var prevUnreads = {};   // id (signed: pos=user, neg=group) → previous unread count
    var notifsInited = false;
    var origTitle = document.title;

    function csNotifsSeed() {
        // Seed from initial load so we don't notify for messages that arrived before page open
        usersCache.forEach(function(u){ prevUnreads[u.id] = parseInt(u.unread || 0); });
        groupsCache.forEach(function(g){ prevUnreads[gKey(g.id)] = parseInt(g.unread || 0); });
        notifsInited = true;
    }

    function csCheckNewMessages(totalUnread) {
        if (!notifsInited) { csNotifsSeed(); csUpdateTabTitle(totalUnread); return; }
        // Diff per user
        usersCache.forEach(function(u) {
            var prev = prevUnreads[u.id] || 0;
            var curr = parseInt(u.unread || 0);
            if (curr > prev) csNotifyMessage({
                id: u.id, isGroup: false, name: u.display_name, avatar: u.profile_picture, ref_type: u.ref_type,
                message: u.last_image && !u.last_message ? '📷 Photo' : (u.last_message || ''),
            });
            prevUnreads[u.id] = curr;
        });
        // Diff per group
        groupsCache.forEach(function(g) {
            var k = gKey(g.id);
            var prev = prevUnreads[k] || 0;
            var curr = parseInt(g.unread || 0);
            if (curr > prev) csNotifyMessage({
                id: g.id, isGroup: true, name: g.name, avatar: null, ref_type: 'team',
                message: g.last_message || ''
            });
            prevUnreads[k] = curr;
        });
        csUpdateTabTitle(totalUnread);
    }

    function csNotifyMessage(p) {
        var key = p.isGroup ? gKey(p.id) : p.id;
        var boxOpen = !!openBoxes[key] && !openBoxes[key].mini;
        var tabFocused = !document.hidden && document.hasFocus();

        // Skip notification if user is actively viewing this exact chat
        var skipAll = boxOpen && tabFocused;

        // Always: ping sound (unless box is open + focused, in which case we don't double-ping — csPollBox already pings)
        if (!skipAll) csPlayPing();

        // Toast popup (skip if box is open and focused)
        if (!skipAll) csShowMsgToast(p);

        // OS-level notification (only when tab is hidden or not focused, requires permission)
        if (!tabFocused && Notification && Notification.permission === 'granted') {
            try {
                var body = p.message || '(empty)';
                if (body.length > 100) body = body.slice(0, 100) + '…';
                var n = new Notification(p.name + (p.isGroup ? ' (group)' : ''), {
                    body: body,
                    icon: p.avatar ? (BASE + '/' + p.avatar) : (BASE + '/images/apexcybernet-logo.svg'),
                    tag:  'apexcybernet-chat-' + key,
                    silent: false
                });
                n.onclick = function() {
                    window.focus();
                    if (p.isGroup) csOpenGroupBox(p.id);
                    else           csOpenBox(p.id);
                    n.close();
                };
                setTimeout(function(){ try { n.close(); } catch (e) {} }, 6000);
            } catch (e) { console.warn('[notif] failed', e); }
        }
    }

    function csShowMsgToast(p) {
        var key = p.isGroup ? gKey(p.id) : p.id;
        var stack = document.getElementById('csToasts'); if (!stack) return;
        // Replace existing toast for same chat (don't stack same-sender toasts)
        var existing = document.getElementById('csToast-' + key);
        if (existing) existing.remove();

        var t = document.createElement('div');
        t.id = 'csToast-' + key;
        t.className = 'cs-toast';
        var avHtml = p.avatar ? '<img src="' + BASE + '/' + esc(p.avatar) + '">' : '<i class="bi bi-' + (p.isGroup ? 'people-fill' : 'person-fill') + '"></i>';
        t.innerHTML = ''
            + '<div class="cs-toast-av' + (p.ref_type==='team'||p.isGroup ? ' team' : '') + '">' + avHtml + '</div>'
            + '<div class="cs-toast-body">'
            +   '<div class="cs-toast-name">' + esc(p.name) + (p.isGroup ? ' <span style="color:var(--accent-light);font-size:0.65rem;">· group</span>' : '') + '</div>'
            +   '<div class="cs-toast-msg">' + esc(p.message || '(empty)') + '</div>'
            + '</div>'
            + '<button class="cs-toast-close" onclick="event.stopPropagation();this.parentElement.remove();return false;"><i class="bi bi-x-lg"></i></button>';
        t.onclick = function() {
            if (p.isGroup) csOpenGroupBox(p.id);
            else           csOpenBox(p.id);
            t.remove();
        };
        stack.appendChild(t);
        setTimeout(function() {
            t.classList.add('fadeout');
            setTimeout(function(){ try { t.remove(); } catch (e) {} }, 250);
        }, 5500);
    }

    function csUpdateTabTitle(unread) {
        if (unread > 0) document.title = '(' + (unread > 99 ? '99+' : unread) + ') ' + origTitle;
        else            document.title = origTitle;
    }

    /* ── Notification permission UX ── */
    function csUpdateNotifPrompt() {
        var bar = document.getElementById('csNotifPrompt');
        if (!bar) return;
        if (IS_GUEST) { bar.classList.remove('on'); return; }
        if (!('Notification' in window)) return;
        var dismissed = false;
        try { dismissed = localStorage.getItem('cs-notif-dismissed') === '1'; } catch (e) {}
        if (Notification.permission === 'default' && !dismissed) bar.classList.add('on');
        else bar.classList.remove('on');
    }
    window.csEnableNotifs = function() {
        if (!('Notification' in window)) return;
        Notification.requestPermission().then(function(p) {
            csUpdateNotifPrompt();
            if (p === 'granted') {
                try {
                    var n = new Notification('Notifications enabled', { body: 'You\'ll get message alerts here.', icon: BASE + '/images/apexcybernet-logo.svg', silent: true });
                    setTimeout(function(){ try { n.close(); } catch(e){} }, 2500);
                } catch (e) {}
            }
        });
    };
    window.csDismissNotifs = function() {
        try { localStorage.setItem('cs-notif-dismissed', '1'); } catch (e) {}
        document.getElementById('csNotifPrompt').classList.remove('on');
    };

    var boxesRestored = false;
    function csLoadUsers() {
        fetch(API_USERS, { credentials: 'include' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.error) return;
                usersCache  = d.users  || [];
                groupsCache = d.groups || [];
                csRenderUsers();
                csUpdateBadge(d.total_unread || 0);
                csCheckNewMessages(d.total_unread || 0);
                csUpdateNotifPrompt();
                // Restore persisted boxes on desktop once we have user + group metadata
                if (!boxesRestored && !IS_GUEST && isDesktop()) {
                    boxesRestored = true;
                    getPersistedBoxes().forEach(function(item) {
                        var pid = parseInt(item.id || item);
                        if (!pid || openBoxes[pid]) return;
                        if (isGroupId(pid)) csOpenGroupBox(realGid(pid));
                        else                csOpenBox(pid);
                        if (item.mini && openBoxes[pid]) csToggleMini(pid);
                    });
                }
            })
            .catch(function() {});
    }

    function csUpdateBadge(n) {
        var txt = n > 99 ? '99+' : String(n);
        ['csBadge', 'csTabBadge'].forEach(function(id) {
            var b = document.getElementById(id);
            if (!b) return;
            if (n > 0) { b.classList.remove('hidden'); b.textContent = txt; }
            else       { b.classList.add('hidden'); }
        });
    }

    window.csFilter = function() {
        csRenderUsers(document.getElementById('csSearch').value.trim().toLowerCase());
    };

    function csRenderUsers(q) {
        var list = document.getElementById('csList');
        var rows = usersCache.filter(function(u) {
            if (!q) return true;
            return (u.display_name||'').toLowerCase().indexOf(q) !== -1
                || (u.flair_team||'').toLowerCase().indexOf(q) !== -1;
        });
        var groupRows = groupsCache.filter(function(g) {
            if (!q) return true;
            return (g.name||'').toLowerCase().indexOf(q) !== -1;
        });
        var countEl = document.getElementById('csCount');
        if (countEl) {
            var online = usersCache.filter(function(u) { return u.is_online == 1; }).length;
            countEl.innerHTML = rows.length + ' player' + (rows.length !== 1 ? 's' : '')
                + (groupRows.length > 0 ? ' · ' + groupRows.length + ' group' + (groupRows.length!==1?'s':'') : '')
                + (online > 0 ? ' · <span style="color:#22c55e;">' + online + ' online</span>' : '');
        }
        if (!rows.length && !groupRows.length) {
            list.innerHTML = '<div class="cs-empty"><i class="bi bi-person-slash"></i>No players found</div>';
            return;
        }

        // Render groups at the top
        var groupsHtml = groupRows.map(function(g) {
            var initials = (g.name || 'G').trim().slice(0,2).toUpperCase();
            var msg = g.last_message ? ((parseInt(g.last_sender)===ME?'You: ':'') + esc(g.last_message)) : '<em style="opacity:0.6;">' + g.member_count + ' members</em>';
            var time = g.last_time ? fmtTime(g.last_time) : '';
            var unread = g.unread > 0 ? '<span class="cs-unread">' + (g.unread > 99 ? '99+' : g.unread) + '</span>' : '';
            var active = openBoxes[gKey(g.id)] ? ' active' : '';
            var badge = '<span class="cs-private-badge" title="Private group — only members can see this"><i class="bi bi-lock-fill"></i></span>';
            var nameTag = ' <i class="bi bi-people-fill" style="color:var(--accent-light);font-size:0.7rem;" title="Group"></i>';
            return '<div class="cs-user' + active + '" onclick="csOpenGroupBox(' + g.id + ')">'
                + '<div class="cs-av-wrap">'
                +   '<div class="cs-av team" style="background:linear-gradient(135deg,#7c3aed,#ec4899);color:#fff;font-weight:900;font-size:0.85rem;">' + esc(initials) + '</div>'
                +   badge
                + '</div>'
                + '<div class="cs-user-body">'
                + '<div class="cs-user-name"><span class="nm">' + esc(g.name) + '</span>' + nameTag + '</div>'
                + '<div class="cs-user-msg">' + msg + '</div>'
                + '</div>'
                + '<div class="cs-user-meta"><div class="cs-user-time">' + time + '</div>' + unread + '</div>'
                + '</div>';
        }).join('');

        function renderUserRow(u) {
            var isTeam = u.ref_type === 'team';
            var verified = u.is_verified == 1 ? ' <i class="bi bi-patch-check-fill verified" title="Verified"></i>' : '';
            var flair = flairFor(u);
            var msg;
            if (u.last_image && !u.last_message) msg = '<i class="bi bi-image"></i> Photo';
            else if (u.last_message)             msg = esc(u.last_message);
            else                                 msg = '<em style="opacity:0.6;">Say hi 👋</em>';
            var time = u.last_time ? fmtTime(u.last_time) : '';
            var unread = u.unread > 0 ? '<span class="cs-unread">' + (u.unread > 99 ? '99+' : u.unread) + '</span>' : '';
            var active = openBoxes[u.id] ? ' active' : '';
            var dot = u.is_online == 1 ? '<span class="cs-online-dot" title="Online"></span>' : '';
            return '<div class="cs-user' + active + '" onclick="csOpenBox(' + u.id + ')">'
                + '<div class="cs-av-wrap"><div class="cs-av' + (isTeam?' team':'') + '">' + avImg(u) + '</div>' + dot + '</div>'
                + '<div class="cs-user-body">'
                + '<div class="cs-user-name"><span class="nm">' + esc(u.display_name) + '</span>' + verified + '</div>'
                + (flair ? '<div class="cs-user-msg" style="margin-bottom:2px;">' + flair + '</div>' : '')
                + '<div class="cs-user-msg">' + msg + '</div>'
                + '</div>'
                + '<div class="cs-user-meta"><div class="cs-user-time">' + time + '</div>' + unread + '</div>'
                + '</div>';
        }

        // Pin Apex Cybernet + HIDE OUT above groups
        var pinnedNames = ['Apex Cybernet', 'HIDE OUT'];
        var pinnedRows  = [];
        var regularRows = [];
        rows.forEach(function(u) {
            var idx = pinnedNames.indexOf(u.display_name);
            if (idx !== -1) pinnedRows[idx] = u;
            else            regularRows.push(u);
        });
        pinnedRows = pinnedRows.filter(Boolean); // drop gaps if one is missing
        var pinnedHtml  = pinnedRows.map(renderUserRow).join('');
        var regularHtml = regularRows.map(renderUserRow).join('');

        list.innerHTML = pinnedHtml + groupsHtml + regularHtml;
    }

    /* ── Chat boxes ── */
    window.csOpenBox = function(peerId) {
        peerId = parseInt(peerId);
        if (IS_GUEST) {
            var u = usersCache.find(function(x) { return parseInt(x.id) === peerId; });
            csShowLoginModal(u);
            return;
        }
        if (openBoxes[peerId]) {
            var state = openBoxes[peerId];
            if (state.mini) csToggleMini(peerId);
            state.el.querySelector('.cs-box-input').focus();
            return;
        }

        // Mobile: close any other open box (only one box at a time on small screens), keep sidebar state as-is
        if (!isDesktop()) {
            Object.keys(openBoxes).forEach(function(id) { csCloseBox(parseInt(id)); });
        } else {
            // Desktop: enforce max open boxes
            var ids = Object.keys(openBoxes);
            if (ids.length >= MAX_OPEN_BOXES) {
                csCloseBox(parseInt(ids[0]));
            }
        }

        var u = usersCache.find(function(x) { return parseInt(x.id) === peerId; }) || { display_name: '…' };
        var isTeam = u.ref_type === 'team';
        var verified = u.is_verified == 1 ? ' <i class="bi bi-patch-check-fill verified"></i>' : '';
        var sub = u.is_online == 1
            ? '<span style="display:inline-flex;align-items:center;gap:0.25rem;color:#22c55e;"><span style="width:7px;height:7px;border-radius:50%;background:#22c55e;display:inline-block;"></span>Active now</span>'
            : (u.flair_team ? esc(u.flair_team) : '');
        var dot = u.is_online == 1 ? '<span class="cs-box-online-dot"></span>' : '';

        var box = document.createElement('div');
        box.className = 'cs-box';
        box.id = 'csBox-' + peerId;
        box.innerHTML = ''
            + '<div class="cs-box-head">'
            +   '<div class="cs-box-av-wrap"><div class="cs-box-av' + (isTeam?' team':'') + '">' + avImg(u) + '</div>' + dot + '</div>'
            +   '<div class="cs-box-who">'
            +     '<div class="cs-box-name"><span class="nm">' + esc(u.display_name) + '</span>' + verified + '</div>'
            +     (sub ? '<div class="cs-box-sub" id="csBoxSub-' + peerId + '">' + sub + '</div>' : '<div class="cs-box-sub" id="csBoxSub-' + peerId + '"></div>')
            +   '</div>'
            +   '<button class="cs-box-btn cs-voice-quick" id="csVoiceQuick-' + peerId + '" onclick="event.stopPropagation();csVoiceQuickClick(' + peerId + ')" title="Voice call"><i class="bi bi-mic-fill"></i></button>'
            +   '<div class="cs-settings">'
            +     '<button class="cs-box-btn" onclick="event.stopPropagation();csToggleSettings(' + peerId + ')" title="Settings"><i class="bi bi-gear-fill"></i></button>'
            +     '<div class="cs-settings-menu" id="csSettings-' + peerId + '"></div>'
            +   '</div>'
            +   '<button class="cs-box-btn" onclick="event.stopPropagation();csToggleMini(' + peerId + ')" title="Minimize"><i class="bi bi-dash-lg"></i></button>'
            +   '<button class="cs-box-btn" onclick="event.stopPropagation();csCloseBox(' + peerId + ')" title="Close"><i class="bi bi-x-lg"></i></button>'
            + '</div>'
            + '<div class="cs-calling" id="csCalling-' + peerId + '">'
            +   '<i class="bi bi-arrow-clockwise cs-calling-spinner"></i>'
            +   '<div class="cs-calling-text">Calling <span class="name" id="csCallingName-' + peerId + '">…</span></div>'
            +   '<div class="cs-calling-time" id="csCallingTime-' + peerId + '">0s</div>'
            +   '<button type="button" class="cs-calling-cancel" onclick="csVoiceCancel(' + peerId + ')">Cancel</button>'
            + '</div>'
            + '<div class="cs-voice" id="csVoice-' + peerId + '">'
            +   '<div class="cs-voice-head"><i class="bi bi-record-circle-fill rec"></i> Voice call <span class="cs-voice-time" id="csVoiceTime-' + peerId + '">00:00</span></div>'
            +   '<div class="cs-voice-members" id="csVoiceMembers-' + peerId + '"></div>'
            +   '<div class="cs-voice-controls">'
            +     '<button type="button" class="cs-voice-btn" id="csVoiceMic-' + peerId + '" onclick="csVoiceMute(' + peerId + ')"><i class="bi bi-mic-fill"></i> Mute</button>'
            +     '<button type="button" class="cs-voice-btn leave" onclick="csVoiceLeave(' + peerId + ')"><i class="bi bi-telephone-x-fill"></i> Leave</button>'
            +   '</div>'
            +   '<div class="cs-voice-audio-host" id="csVoiceAudio-' + peerId + '"></div>'
            + '</div>'
            + '<div class="cs-box-body" id="csBoxBody-' + peerId + '" style="position:relative;"><div class="cs-empty"><i class="bi bi-hourglass-split"></i>Loading…</div></div>'
            + '<button type="button" class="cs-scroll-btn" id="csScroll-' + peerId + '" onclick="csScrollBottom(' + peerId + ')"><i class="bi bi-chevron-down"></i><span class="cs-scroll-btn-badge" id="csScrollBadge-' + peerId + '" style="display:none;">0</span></button>'
            + '<div class="cs-compose-reply" id="csReplyPrev-' + peerId + '">'
            +   '<div class="cs-compose-reply-body">'
            +     '<div class="cs-compose-reply-label">Replying to <span class="csRpAuthor"></span></div>'
            +     '<div class="cs-compose-reply-text"></div>'
            +   '</div>'
            +   '<button type="button" class="cs-compose-reply-close" onclick="csCancelReply(' + peerId + ')"><i class="bi bi-x-lg"></i></button>'
            + '</div>'
            + '<div class="cs-attach-preview" id="csAttachPrev-' + peerId + '"><img src=""><button type="button" onclick="csClearAttach(' + peerId + ')"><i class="bi bi-x-lg"></i></button></div>'
            + '<form class="cs-box-form" onsubmit="return csBoxSend(event, ' + peerId + ')">'
            +   '<div class="cs-emoji-panel" id="csEmojiPanel-' + peerId + '"></div>'
            +   '<label class="cs-attach-btn" title="Attach photo"><i class="bi bi-image"></i><input type="file" accept="image/*" style="display:none;" onchange="csPickAttach(this, ' + peerId + ')"></label>'
            +   '<textarea class="cs-box-input" rows="1" placeholder="Write a message…" oninput="csBoxAutosize(this, ' + peerId + ')" onkeydown="csBoxKey(event, ' + peerId + ')"></textarea>'
            +   '<button type="button" class="cs-emoji-btn" onclick="csToggleEmoji(event, ' + peerId + ')" title="Emoji">😊</button>'
            +   '<button type="submit" class="cs-box-send" disabled><i class="bi bi-send-fill"></i></button>'
            + '</form>';
        document.getElementById('csBoxes').appendChild(box);

        var state = { el: box, lastMsgId: 0, timer: null, mini: false, draftTimer: null };
        openBoxes[peerId] = state;

        // Restore any saved draft for this peer
        var draft = loadDraft(peerId);
        if (draft) {
            var inp = box.querySelector('.cs-box-input');
            inp.value = draft;
            csBoxAutosize(inp, peerId);
        }

        csPollBox(peerId, true);
        state.timer = setInterval(function() { csPollBox(peerId, false); }, POLL_BOX_MS);

        csRenderUsers(document.getElementById('csSearch').value.trim().toLowerCase());
        savePersistedBoxes();
        setTimeout(function() { box.querySelector('.cs-box-input').focus(); }, 60);
    };

    /* ── Group chat box ── */
    window.csOpenGroupBox = function(groupId) {
        groupId = parseInt(groupId);
        var key = gKey(groupId);
        if (IS_GUEST) return;
        if (openBoxes[key]) {
            var s = openBoxes[key];
            if (s.mini) csToggleMini(key);
            s.el.querySelector('.cs-box-input').focus();
            return;
        }
        if (!isDesktop()) {
            Object.keys(openBoxes).forEach(function(id) { csCloseBox(parseInt(id)); });
        } else {
            var ids = Object.keys(openBoxes);
            if (ids.length >= MAX_OPEN_BOXES) csCloseBox(parseInt(ids[0]));
        }

        var g = groupsCache.find(function(x){ return parseInt(x.id) === groupId; }) || { name: 'Group', member_count: 0 };
        var initials = (g.name || 'G').trim().slice(0,2).toUpperCase();

        var box = document.createElement('div');
        box.className = 'cs-box';
        box.id = 'csBox-' + key;
        box.innerHTML = ''
            + '<div class="cs-box-head">'
            +   '<div class="cs-box-av-wrap">'
            +     '<div class="cs-box-av team" style="background:linear-gradient(135deg,#7c3aed,#ec4899);color:#fff;font-weight:900;font-size:0.72rem;">' + esc(initials) + '</div>'
            +     '<span class="cs-box-private-badge" title="Private group"><i class="bi bi-lock-fill"></i></span>'
            +   '</div>'
            +   '<div class="cs-box-who">'
            +     '<div class="cs-box-name"><span class="nm">' + esc(g.name) + '</span>'
            +       ' <i class="bi bi-people-fill" style="color:var(--accent-light);font-size:0.7rem;"></i>'
            +     '</div>'
            +     '<div class="cs-box-sub" id="csBoxSub-' + key + '">'
            +       '<i class="bi bi-lock-fill" style="color:#fbbf24;font-size:0.62rem;"></i> Private · ' + g.member_count + ' members'
            +     '</div>'
            +   '</div>'
            +   '<button class="cs-box-btn cs-voice-quick" id="csVoiceQuick-' + key + '" onclick="event.stopPropagation();csVoiceQuickClick(' + key + ')" title="Voice call"><i class="bi bi-mic-fill"></i></button>'
            +   '<div class="cs-settings">'
            +     '<button class="cs-box-btn" onclick="event.stopPropagation();csToggleSettings(' + key + ')" title="Settings"><i class="bi bi-gear-fill"></i></button>'
            +     '<div class="cs-settings-menu" id="csSettings-' + key + '"></div>'
            +   '</div>'
            +   '<button class="cs-box-btn" onclick="event.stopPropagation();csToggleMini(' + key + ')" title="Minimize"><i class="bi bi-dash-lg"></i></button>'
            +   '<button class="cs-box-btn" onclick="event.stopPropagation();csCloseBox(' + key + ')" title="Close"><i class="bi bi-x-lg"></i></button>'
            + '</div>'
            + '<div class="cs-members" id="csMembers-' + key + '"></div>'
            + '<div class="cs-voice" id="csVoice-' + key + '">'
            +   '<div class="cs-voice-head"><i class="bi bi-record-circle-fill rec"></i> Voice call <span class="cs-voice-time" id="csVoiceTime-' + key + '">00:00</span></div>'
            +   '<div class="cs-voice-members" id="csVoiceMembers-' + key + '"></div>'
            +   '<div class="cs-voice-controls">'
            +     '<button type="button" class="cs-voice-btn" id="csVoiceMic-' + key + '" onclick="csVoiceMute(' + key + ')"><i class="bi bi-mic-fill"></i> Mute</button>'
            +     '<button type="button" class="cs-voice-btn leave" onclick="csVoiceLeave(' + key + ')"><i class="bi bi-telephone-x-fill"></i> Leave</button>'
            +   '</div>'
            +   '<div class="cs-voice-audio-host" id="csVoiceAudio-' + key + '"></div>'
            + '</div>'
            + '<div class="cs-box-body" id="csBoxBody-' + key + '" style="position:relative;"><div class="cs-empty"><i class="bi bi-hourglass-split"></i>Loading…</div></div>'
            + '<button type="button" class="cs-scroll-btn" id="csScroll-' + key + '" onclick="csScrollBottom(' + key + ')"><i class="bi bi-chevron-down"></i><span class="cs-scroll-btn-badge" id="csScrollBadge-' + key + '" style="display:none;">0</span></button>'
            + '<div class="cs-compose-reply" id="csReplyPrev-' + key + '">'
            +   '<div class="cs-compose-reply-body">'
            +     '<div class="cs-compose-reply-label">Replying to <span class="csRpAuthor"></span></div>'
            +     '<div class="cs-compose-reply-text"></div>'
            +   '</div>'
            +   '<button type="button" class="cs-compose-reply-close" onclick="csCancelReply(' + key + ')"><i class="bi bi-x-lg"></i></button>'
            + '</div>'
            + '<div class="cs-attach-preview" id="csAttachPrev-' + key + '"><img src=""><button type="button" onclick="csClearAttach(' + key + ')"><i class="bi bi-x-lg"></i></button></div>'
            + '<form class="cs-box-form" onsubmit="return csBoxSend(event, ' + key + ')">'
            +   '<div class="cs-emoji-panel" id="csEmojiPanel-' + key + '"></div>'
            +   '<label class="cs-attach-btn" title="Attach photo"><i class="bi bi-image"></i><input type="file" accept="image/*" style="display:none;" onchange="csPickAttach(this, ' + key + ')"></label>'
            +   '<textarea class="cs-box-input" rows="1" placeholder="Message group…" oninput="csBoxAutosize(this, ' + key + ')" onkeydown="csBoxKey(event, ' + key + ')"></textarea>'
            +   '<button type="button" class="cs-emoji-btn" onclick="csToggleEmoji(event, ' + key + ')" title="Emoji">😊</button>'
            +   '<button type="submit" class="cs-box-send" disabled><i class="bi bi-send-fill"></i></button>'
            + '</form>';
        document.getElementById('csBoxes').appendChild(box);

        var state = { el: box, lastMsgId: 0, timer: null, mini: false, draftTimer: null, polling: false };
        openBoxes[key] = state;

        var draft = loadDraft(key);
        if (draft) {
            var inp = box.querySelector('.cs-box-input');
            inp.value = draft;
            csBoxAutosize(inp, key);
        }

        csPollBox(key, true);
        state.timer = setInterval(function() { csPollBox(key, false); }, POLL_BOX_MS);

        csRenderUsers(document.getElementById('csSearch').value.trim().toLowerCase());
        savePersistedBoxes();
        setTimeout(function() { box.querySelector('.cs-box-input').focus(); }, 60);
    };

    window.csLeaveGroup = function(groupId) {
        if (!confirm('Leave this group? You will no longer see its messages.')) return;
        var fd = new FormData();
        fd.append('action', 'leave');
        fd.append('group_id', groupId);
        fetch('<?= base_url('api/chat-groups.php') ?>', { method: 'POST', body: fd, credentials: 'include' })
            .then(function(r){ return r.json(); })
            .then(function(){
                csCloseBox(gKey(groupId));
                csLoadUsers();
            });
    };

    window.csDeleteGroup = function(groupId) {
        if (!confirm('Delete this group for everyone? All messages will be permanently removed. This cannot be undone.')) return;
        var fd = new FormData();
        fd.append('action', 'delete');
        fd.append('group_id', groupId);
        fetch('<?= base_url('api/chat-groups.php') ?>', { method: 'POST', body: fd, credentials: 'include' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d && d.error) { alert('Could not delete: ' + d.error); return; }
                csCloseBox(gKey(groupId));
                csLoadUsers();
            });
    };

    /* ── New Group modal ── */
    var ngSelected = {}; // account_id -> true

    window.csOpenNewGroup = function() {
        if (IS_GUEST) { csShowLoginModal({ display_name: 'Create a group' }); return; }
        ngSelected = {};
        document.getElementById('csNgName').value = '';
        document.getElementById('csNgSearch').value = '';
        csNgRender();
        document.getElementById('csNewGroup').classList.add('on');
        setTimeout(function(){ document.getElementById('csNgName').focus(); }, 50);
    };
    window.csCloseNewGroup = function(e) {
        document.getElementById('csNewGroup').classList.remove('on');
    };
    window.csNgFilter = function() { csNgRender(); };

    function csNgRender() {
        var q = (document.getElementById('csNgSearch').value || '').trim().toLowerCase();
        var list = document.getElementById('csNgList');
        var rows = usersCache.filter(function(u) {
            if (!q) return true;
            return (u.display_name||'').toLowerCase().indexOf(q) !== -1;
        });
        if (!rows.length) {
            list.innerHTML = '<div class="cs-empty" style="padding:1.5rem 1rem;"><i class="bi bi-person-slash"></i>No players</div>';
            return;
        }
        list.innerHTML = rows.map(function(u) {
            var sel = ngSelected[u.id] ? ' active' : '';
            return '<label class="cs-user' + sel + '" style="cursor:pointer;padding:0.5rem 0.6rem;border-radius:6px;">'
                + '<input type="checkbox" ' + (ngSelected[u.id]?'checked':'') + ' onchange="csNgToggle(' + u.id + ',this.checked)" style="margin-right:0.6rem;accent-color:var(--accent);">'
                + '<div class="cs-av-wrap" style="margin-right:0.5rem;"><div class="cs-av' + (u.ref_type==='team'?' team':'') + '">' + avImg(u) + '</div></div>'
                + '<div class="cs-user-body"><div class="cs-user-name"><span class="nm">' + esc(u.display_name) + '</span></div>'
                + (u.flair_team ? '<div class="cs-user-msg" style="font-size:0.68rem;">' + esc(u.flair_team) + '</div>' : '')
                + '</div>'
                + '</label>';
        }).join('');
    }
    window.csNgToggle = function(uid, checked) {
        if (checked) ngSelected[uid] = true; else delete ngSelected[uid];
    };

    window.csCreateGroup = function() {
        var name = document.getElementById('csNgName').value.trim();
        if (!name) { alert('Please enter a group name'); return; }
        var members = Object.keys(ngSelected).map(Number);
        if (!members.length) { alert('Please select at least one member'); return; }
        var btn = document.getElementById('csNgCreateBtn');
        btn.disabled = true; btn.textContent = 'Creating…';
        var fd = new FormData();
        fd.append('action', 'create');
        fd.append('name', name);
        fd.append('members', JSON.stringify(members));
        fetch('<?= base_url('api/chat-groups.php') ?>', { method: 'POST', body: fd, credentials: 'include' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Create';
                if (d.error) { alert('Failed: ' + d.error); return; }
                csCloseNewGroup();
                csLoadUsers();
                setTimeout(function(){ csOpenGroupBox(d.group_id); }, 300);
            })
            .catch(function(){
                btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Create';
                alert('Network error');
            });
    };

    window.csCloseBox = function(peerId) {
        peerId = parseInt(peerId);
        var state = openBoxes[peerId];
        if (!state) return;
        clearInterval(state.timer);
        if (state.draftTimer) clearTimeout(state.draftTimer);
        state.el.remove();
        delete openBoxes[peerId];
        saveDraft(peerId, ''); // clear draft on explicit close
        savePersistedBoxes();
        csRenderUsers(document.getElementById('csSearch').value.trim().toLowerCase());
    };

    window.csToggleMini = function(peerId) {
        peerId = parseInt(peerId);
        var state = openBoxes[peerId];
        if (!state) return;
        state.mini = !state.mini;
        state.el.classList.toggle('mini', state.mini);
        savePersistedBoxes();
        if (!state.mini) {
            var inp = state.el.querySelector('.cs-box-input');
            if (inp) inp.focus();
        }
    };

    /* Robust head-click → minimize toggle.
       Uses event delegation on the boxes container so it works for any box
       (DM, group) and survives DOM rebuilds. Filters out clicks on buttons / interactive elements. */
    document.addEventListener('click', function(e) {
        var head = e.target.closest('.cs-box-head');
        if (!head) return;
        // Don't toggle if user clicked an interactive child
        if (e.target.closest('button, a, input, textarea, .cs-settings-menu, .cs-settings')) return;
        var box = head.closest('.cs-box');
        if (!box) return;
        // Box id is "csBox-<key>"
        var id = box.id || '';
        var m = id.match(/^csBox-(-?\d+)$/);
        if (!m) return;
        csToggleMini(parseInt(m[1]));
    });

    function csPollBox(peerId, replace) {
        var state = openBoxes[peerId];
        if (!state) return;
        // Serialize polls per box: drop if one is already in flight (race guard)
        if (state.polling && !replace) return;
        state.polling = true;
        var isGroup = isGroupId(peerId);
        var queryKey = isGroup ? 'group_id=' + realGid(peerId) : 'peer_id=' + peerId;
        var url = API_MESSAGES + '?' + queryKey + (state.lastMsgId ? '&since_id=' + state.lastMsgId : '');
        fetch(url, { credentials: 'include' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                state.polling = false;
                if (d.error) return;
                var msgs = d.messages || [];
                var container = state.el.querySelector('.cs-box-body');
                if (replace) container.innerHTML = '';
                var wasAtBottom = Math.abs(container.scrollHeight - container.scrollTop - container.clientHeight) < 30;

                var lastDay = container.querySelector('.cs-day-sep:last-of-type');
                var lastDayText = lastDay ? lastDay.textContent : '';

                var fxQueue = [];
                msgs.forEach(function(m) {
                    // DOM-level dedup: if a bubble with this id already exists, skip
                    if (container.querySelector('.cs-msg[data-msg-id="' + m.id + '"]')) return;
                    var day = fmtDay(m.created_at);
                    if (day !== lastDayText) {
                        var sep = document.createElement('div');
                        sep.className = 'cs-day-sep';
                        sep.textContent = day;
                        container.appendChild(sep);
                        lastDayText = day;
                    }
                    var mine = parseInt(m.sender_id) === ME;

                    // Parse reply marker embedded as "↩️[#123]::content"
                    var replyMatch = m.content.match(/^↩️\[#(\d+)\]::([\s\S]*)$/);
                    var replyToId = null, realContent = m.content;
                    if (replyMatch) { replyToId = parseInt(replyMatch[1]); realContent = replyMatch[2]; }

                    // Reply quote (if any)
                    if (replyToId) {
                        var quoted = container.querySelector('.cs-msg[data-msg-id="' + replyToId + '"]');
                        var qText  = quoted ? quoted.textContent : '…';
                        var qAuthor = (quoted && quoted.classList.contains('mine')) ? 'You' : 'Them';
                        var qDiv = document.createElement('div');
                        qDiv.className = 'cs-reply-quote' + (mine ? ' mine' : '');
                        qDiv.innerHTML = '<div class="cs-reply-quote-body">'
                            + '<div class="cs-reply-quote-author">↩ Replying to ' + esc(qAuthor) + '</div>'
                            + '<div class="cs-reply-quote-text">' + esc(qText.slice(0, 80)) + '</div>'
                            + '</div>';
                        qDiv.onclick = function() {
                            var target = container.querySelector('.cs-msg[data-msg-id="' + replyToId + '"]');
                            if (!target) return;
                            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            target.classList.remove('cs-highlight');
                            void target.offsetWidth;
                            target.classList.add('cs-highlight');
                        };
                        container.appendChild(qDiv);
                    }

                    var row = document.createElement('div');
                    row.className = 'cs-msg-row ' + (mine ? 'mine' : 'theirs');

                    // For groups: show sender name above message (for "theirs" only)
                    if (isGroupId(peerId) && !mine && m.sender_name) {
                        var prevSender = container.querySelector('.cs-msg-row.theirs:last-child .cs-msg')?.dataset?.sender;
                        if (prevSender != m.sender_id) {
                            var sn = document.createElement('div');
                            sn.style.cssText = 'font-size:0.62rem;font-weight:800;color:var(--accent-light);padding:0.35rem 0.4rem 0;';
                            sn.textContent = m.sender_name;
                            container.appendChild(sn);
                        }
                    }

                    var bubble = document.createElement('div');
                    bubble.className = 'cs-msg ' + (mine ? 'mine' : 'theirs');
                    // If message has an image, render it; otherwise just text
                    var hasImg = !!m.image_url;
                    if (hasImg) {
                        var img = document.createElement('img');
                        img.className = 'cs-msg-img';
                        img.src = BASE + '/' + m.image_url;
                        img.alt = 'image';
                        img.onclick = function() { csShowLightbox(this.src); };
                        bubble.appendChild(img);
                        if (realContent) {
                            var cap = document.createElement('div');
                            cap.style.marginTop = '0.4rem';
                            cap.innerHTML = linkify(realContent);
                            bubble.appendChild(cap);
                        } else {
                            // image-only — make bubble tighter and transparent
                            bubble.style.background = 'transparent';
                            bubble.style.padding = '0';
                        }
                    } else {
                        bubble.innerHTML = linkify(realContent);
                    }
                    bubble.dataset.msgId = m.id;
                    bubble.dataset.sender = m.sender_id;
                    bubble.dataset.peer   = peerId;
                    bubble.dataset.mine   = mine ? '1' : '0';
                    bubble.dataset.quote  = realContent || (hasImg ? '[image]' : '');
                    bubble.oncontextmenu = function(ev) { ev.preventDefault(); csShowCtx(ev, this); };
                    bubble.addEventListener('touchstart', csTouchStart, { passive: true });
                    bubble.addEventListener('touchend',   csTouchEnd,   { passive: true });

                    var quick = document.createElement('div');
                    quick.className = 'cs-msg-quick';
                    quick.innerHTML = '<button type="button" onclick="csStartReply(' + peerId + ',' + m.id + ')" title="Reply"><i class="bi bi-reply-fill"></i></button>';

                    row.appendChild(bubble);
                    row.appendChild(quick);
                    container.appendChild(row);

                    var t = document.createElement('div');
                    t.className = 'cs-msg-time ' + (mine ? 'mine' : 'theirs');
                    t.textContent = fmtClock(m.created_at);
                    container.appendChild(t);
                    if (parseInt(m.id) > state.lastMsgId) state.lastMsgId = parseInt(m.id);

                    if (!replace) {
                        var rule = detectFxRule(realContent);
                        if (rule) fxQueue.push(rule);
                    }
                });

                // Play at most one effect per poll tick (avoid stacking)
                if (fxQueue.length) {
                    playFx(state.el, fxQueue[0]);
                }

                // Render / update "Seen" receipt on last MY message
                var oldSeen = container.querySelector('.cs-seen');
                if (oldSeen) oldSeen.remove();
                if (d.last_seen_by_peer) {
                    var mySel = container.querySelectorAll('.cs-msg.mine');
                    var lastMine = mySel[mySel.length - 1];
                    if (lastMine && parseInt(lastMine.dataset.msgId) <= parseInt(d.last_seen_by_peer)) {
                        var seenEl = document.createElement('div');
                        seenEl.className = 'cs-seen';
                        seenEl.innerHTML = '<i class="bi bi-check2-all"></i> Seen';
                        container.appendChild(seenEl);
                    }
                }

                // Render / update typing indicator
                var oldTyp = container.querySelector('.cs-typing');
                if (oldTyp) oldTyp.remove();
                if (d.peer_typing) {
                    var typ = document.createElement('div');
                    typ.className = 'cs-typing on';
                    typ.innerHTML = '<span class="cs-typing-dots"><span></span><span></span><span></span></span>';
                    container.appendChild(typ);
                }

                // Scroll / unread count for scroll-to-bottom button
                if (replace || wasAtBottom) {
                    container.scrollTop = container.scrollHeight;
                    state.unreadInBox = 0;
                    var sb = document.getElementById('csScroll-' + peerId);
                    if (sb) sb.classList.remove('on');
                } else if (msgs.length) {
                    // Count only messages from peer while user wasn't at bottom
                    var newFromPeer = msgs.filter(function(m){ return parseInt(m.sender_id) !== ME; }).length;
                    state.unreadInBox = (state.unreadInBox || 0) + newFromPeer;
                    if (newFromPeer > 0) {
                        var sb2 = document.getElementById('csScroll-' + peerId);
                        var badge = document.getElementById('csScrollBadge-' + peerId);
                        if (sb2) sb2.classList.add('on');
                        if (badge) {
                            badge.textContent = state.unreadInBox > 99 ? '99+' : state.unreadInBox;
                            badge.style.display = 'flex';
                        }
                        if (state.soundOn !== false) csPlayPing();
                    }
                }
                if (msgs.length && !replace) csLoadUsers();
            })
            .catch(function() { state.polling = false; });
    }

    /* ── Scroll-to-bottom ── */
    window.csScrollBottom = function(peerId) {
        var state = openBoxes[peerId]; if (!state) return;
        var container = state.el.querySelector('.cs-box-body');
        container.scrollTop = container.scrollHeight;
        state.unreadInBox = 0;
        var sb = document.getElementById('csScroll-' + peerId);
        if (sb) sb.classList.remove('on');
    };

    /* ── Sound toggle + ping ── */
    var audioCtx = null;
    function csPlayPing() {
        try {
            audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            var o = audioCtx.createOscillator();
            var g = audioCtx.createGain();
            o.type = 'sine';
            o.frequency.setValueAtTime(780, audioCtx.currentTime);
            o.frequency.exponentialRampToValueAtTime(540, audioCtx.currentTime + 0.15);
            g.gain.setValueAtTime(0.0001, audioCtx.currentTime);
            g.gain.exponentialRampToValueAtTime(0.08, audioCtx.currentTime + 0.01);
            g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.25);
            o.connect(g); g.connect(audioCtx.destination);
            o.start(); o.stop(audioCtx.currentTime + 0.25);
        } catch (e) {}
    }

    window.csToggleSound = function(peerId) {
        var state = openBoxes[peerId]; if (!state) return;
        state.soundOn = state.soundOn === false ? true : false;
        try { localStorage.setItem('cs-sound-' + peerId, state.soundOn === false ? '0' : '1'); } catch (e) {}
        csRenderSettingsMenu(peerId);
    };

    /* ── Settings menu ── */
    window.csToggleSettings = function(peerId) {
        // Close other open menus
        document.querySelectorAll('.cs-settings-menu.on').forEach(function(m) {
            if (m.id !== 'csSettings-' + peerId) m.classList.remove('on');
        });
        var menu = document.getElementById('csSettings-' + peerId);
        if (!menu) return;
        if (!menu.classList.contains('on')) {
            csRenderSettingsMenu(peerId);
            menu.classList.add('on');
        } else {
            menu.classList.remove('on');
        }
    };

    function csRenderSettingsMenu(peerId) {
        var state = openBoxes[peerId]; if (!state) return;
        var menu = document.getElementById('csSettings-' + peerId);
        if (!menu) return;
        var isGroup = isGroupId(peerId);
        var soundOff = state.soundOn === false;
        var html = '';

        if (isGroup) {
            var g = groupsCache.find(function(x){ return parseInt(x.id) === realGid(peerId); }) || {};
            var voiceLive = voiceCalls[peerId] && voiceCalls[peerId].active;
            html += '<div class="cs-settings-label">Group</div>';
            html += '<button onclick="csShowMembers(' + peerId + ')"><i class="bi bi-people-fill"></i> View members <span class="cs-settings-item-sub">' + (g.member_count || '?') + '</span></button>';
            html += '<button onclick="csAddMemberPrompt(' + peerId + ')"><i class="bi bi-person-plus-fill"></i> Add member</button>';
            if (voiceLive) {
                html += '<button class="danger" onclick="csVoiceLeave(' + peerId + ')"><i class="bi bi-telephone-x-fill"></i> Leave voice call</button>';
            } else {
                html += '<button onclick="csVoiceJoin(' + peerId + ')"><i class="bi bi-mic-fill" style="color:#22c55e;"></i> Start voice call</button>';
                html += '<button onclick="csVoiceJoinListenOnly(' + peerId + ')"><i class="bi bi-headphones" style="color:#60a5fa;"></i> Join as listener <span class="cs-settings-item-sub">no mic</span></button>';
            }
        } else {
            var u = usersCache.find(function(x){ return parseInt(x.id) === peerId; }) || {};
            var dmVoiceLive = voiceCalls[peerId] && voiceCalls[peerId].active;
            html += '<div class="cs-settings-label">Player</div>';
            html += '<a href="<?= rtrim(base_url(), '/') ?>/profile.php?id=' + peerId + '"><i class="bi bi-person-circle"></i> View profile</a>';
            if (u.flair_team) html += '<button onclick="csCopyText(\'' + esc(u.flair_team).replace(/'/g, "\\'") + '\')"><i class="bi bi-clipboard"></i> Copy team name</button>';
            if (dmVoiceLive) {
                html += '<button class="danger" onclick="csVoiceLeave(' + peerId + ')"><i class="bi bi-telephone-x-fill"></i> Leave voice call</button>';
            } else if (outgoingInvites[peerId]) {
                html += '<button class="danger" onclick="csVoiceCancel(' + peerId + ')"><i class="bi bi-telephone-x-fill"></i> Cancel call</button>';
            } else {
                html += '<button onclick="csVoiceInvite(' + peerId + ')"><i class="bi bi-telephone-fill" style="color:#22c55e;"></i> Voice call</button>';
            }
        }

        html += '<div class="cs-settings-divider"></div>';
        html += '<div class="cs-settings-label">Preferences</div>';
        html += '<button onclick="csToggleSound(' + peerId + ')"><i class="bi bi-' + (soundOff?'volume-mute-fill':'volume-up-fill') + '"></i> ' + (soundOff?'Unmute sounds':'Mute sounds') + '</button>';
        html += '<button onclick="csClearBoxView(' + peerId + ')"><i class="bi bi-eraser-fill"></i> Clear view <span class="cs-settings-item-sub">local only</span></button>';

        if (isGroup) {
            html += '<div class="cs-settings-divider"></div>';
            html += '<button class="danger" onclick="csLeaveGroup(' + realGid(peerId) + ')"><i class="bi bi-box-arrow-right"></i> Leave group</button>';
            var gInfo = groupsCache.find(function(x){ return parseInt(x.id) === realGid(peerId); });
            if (gInfo && parseInt(gInfo.created_by) === ME) {
                html += '<button class="danger" onclick="csDeleteGroup(' + realGid(peerId) + ')"><i class="bi bi-trash-fill"></i> Delete group <span class="cs-settings-item-sub">creator only</span></button>';
            }
        }
        menu.innerHTML = html;
    }

    window.csCopyText = function(text) {
        try {
            if (navigator.clipboard) navigator.clipboard.writeText(text);
            else { var ta=document.createElement('textarea'); ta.value=text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove(); }
        } catch (e) {}
    };

    window.csClearBoxView = function(peerId) {
        var state = openBoxes[peerId]; if (!state) return;
        if (!confirm('Clear messages from this view? (Only hides them locally — messages stay on the server.)')) return;
        state.el.querySelector('.cs-box-body').innerHTML = '';
        state.lastMsgId = 0;
        csPollBox(peerId, true);
        document.getElementById('csSettings-' + peerId).classList.remove('on');
    };

    /* ── Group members panel ── */
    window.csShowMembers = function(peerId) {
        if (!isGroupId(peerId)) return;
        document.getElementById('csSettings-' + peerId).classList.remove('on');
        var gid = realGid(peerId);
        var panel = document.getElementById('csMembers-' + peerId);
        if (!panel) return;
        panel.innerHTML = '<div class="cs-members-head"><button class="cs-box-btn" onclick="csHideMembers(' + peerId + ')"><i class="bi bi-arrow-left"></i></button><div class="cs-members-title">Members</div></div>'
                       + '<div class="cs-members-list"><div class="cs-empty"><i class="bi bi-hourglass-split"></i>Loading…</div></div>';
        panel.classList.add('on');
        fetch('<?= base_url('api/chat-groups.php') ?>?action=members&group_id=' + gid, { credentials: 'include' })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.error) { panel.querySelector('.cs-members-list').innerHTML = '<div class="cs-empty">Error loading members</div>'; return; }
                var list = panel.querySelector('.cs-members-list');
                var creatorId = d.group ? parseInt(d.group.created_by) : 0;
                list.innerHTML = (d.members || []).map(function(m) {
                    var mine = parseInt(m.id) === ME;
                    var youTag  = mine ? '<span class="cs-member-you">You</span>' : '';
                    var admTag  = parseInt(m.id) === creatorId ? '<span class="cs-member-admin"><i class="bi bi-shield-fill-check"></i> Admin</span>' : '';
                    var online  = m.is_online == 1 ? '<span class="cs-member-online" title="Online"></span>' : '';
                    var avHtml  = m.profile_picture
                        ? '<img src="' + BASE + '/' + esc(m.profile_picture) + '" style="width:100%;height:100%;object-fit:cover;">'
                        : '<i class="bi bi-person-fill"></i>';
                    return '<div class="cs-member-row">'
                        + '<div class="cs-av-wrap"><div class="cs-av' + (m.ref_type==='team'?' team':'') + '" style="width:32px;height:32px;">' + avHtml + '</div>'
                        + (m.is_online == 1 ? '<span class="cs-online-dot" style="width:10px;height:10px;"></span>' : '') + '</div>'
                        + '<span class="cs-member-name">' + esc(m.display_name) + '</span>'
                        + admTag + youTag
                        + '</div>';
                }).join('');
            })
            .catch(function() {});
    };

    window.csHideMembers = function(peerId) {
        var panel = document.getElementById('csMembers-' + peerId);
        if (panel) panel.classList.remove('on');
    };

    window.csAddMemberPrompt = function(peerId) {
        if (!isGroupId(peerId)) return;
        document.getElementById('csSettings-' + peerId).classList.remove('on');
        // Simple prompt — pick from player list
        var name = prompt('Player username to add to this group:');
        if (!name) return;
        var u = usersCache.find(function(x){ return (x.display_name||'').toLowerCase() === name.trim().toLowerCase(); });
        if (!u) { alert('No player found with that name.'); return; }
        var fd = new FormData();
        fd.append('action', 'add');
        fd.append('group_id', realGid(peerId));
        fd.append('account_id', u.id);
        fetch('<?= base_url('api/chat-groups.php') ?>', { method: 'POST', body: fd, credentials: 'include' })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.error) { alert('Failed: ' + d.error); return; }
                csLoadUsers();
                alert(u.display_name + ' added to the group.');
            });
    };

    // Close settings menu on outside click
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.cs-settings')) {
            document.querySelectorAll('.cs-settings-menu.on').forEach(function(m) { m.classList.remove('on'); });
        }
    });

    /* ── Reply + context menu ── */
    var replyState = {}; // peerId -> { msgId, text, author }

    window.csStartReply = function(peerId, msgId) {
        peerId = parseInt(peerId);
        var state = openBoxes[peerId]; if (!state) return;
        var bubble = state.el.querySelector('.cs-msg[data-msg-id="' + msgId + '"]');
        if (!bubble) return;
        var text = bubble.dataset.quote || bubble.textContent;
        var author = bubble.dataset.mine === '1' ? 'yourself' : (usersCache.find(function(u){return parseInt(u.id)===peerId;}) || {}).display_name || 'them';
        replyState[peerId] = { msgId: msgId, text: text, author: author };
        var prev = document.getElementById('csReplyPrev-' + peerId);
        if (prev) {
            prev.querySelector('.csRpAuthor').textContent = author;
            prev.querySelector('.cs-compose-reply-text').textContent = text;
            prev.classList.add('on');
        }
        state.el.querySelector('.cs-box-input').focus();
    };

    window.csCancelReply = function(peerId) {
        peerId = parseInt(peerId);
        delete replyState[peerId];
        var prev = document.getElementById('csReplyPrev-' + peerId);
        if (prev) prev.classList.remove('on');
    };

    window.csShowCtx = function(ev, bubble) {
        var ctx = document.getElementById('csCtx');
        if (!ctx) return;
        ctx.dataset.msg  = bubble.dataset.msgId;
        ctx.dataset.peer = bubble.dataset.peer;
        ctx.dataset.mine = bubble.dataset.mine || '0';
        document.getElementById('csCtxUnsend').style.display = (bubble.dataset.mine === '1') ? 'flex' : 'none';

        // Position within viewport bounds
        ctx.classList.add('on');
        var w = ctx.offsetWidth  || 180;
        var h = ctx.offsetHeight || 240;
        var x = ev.clientX, y = ev.clientY;
        if (x + w > window.innerWidth - 8)  x = window.innerWidth  - w - 8;
        if (y + h > window.innerHeight - 8) y = window.innerHeight - h - 8;
        ctx.style.left = Math.max(8, x) + 'px';
        ctx.style.top  = Math.max(8, y) + 'px';
    };

    window.csHideCtx = function() {
        var ctx = document.getElementById('csCtx');
        if (ctx) ctx.classList.remove('on');
    };

    window.csCtxAction = function(action) {
        var ctx = document.getElementById('csCtx');
        if (!ctx) return;
        var msgId = parseInt(ctx.dataset.msg);
        var peer  = parseInt(ctx.dataset.peer);
        csHideCtx();
        var state = openBoxes[peer]; if (!state) return;
        var bubble = state.el.querySelector('.cs-msg[data-msg-id="' + msgId + '"]');
        if (!bubble) return;

        if (action === 'reply') {
            csStartReply(peer, msgId);
        } else if (action === 'copy') {
            var txt = bubble.dataset.quote || bubble.textContent;
            try {
                if (navigator.clipboard) navigator.clipboard.writeText(txt);
                else { var ta=document.createElement('textarea'); ta.value=txt; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove(); }
            } catch (e) {}
        } else if (action === 'react-heart' || action === 'react-laugh' || action === 'react-fire') {
            var emoji = { 'react-heart':'❤️', 'react-laugh':'😂', 'react-fire':'🔥' }[action];
            // Send as a quick reply referencing the message
            replyState[peer] = { msgId: msgId, text: bubble.dataset.quote || bubble.textContent, author: bubble.dataset.mine === '1' ? 'yourself' : 'them' };
            csQuickSend(peer, emoji);
        } else if (action === 'unsend') {
            if (!confirm('Unsend this message?')) return;
            csUnsendMessage(peer, msgId, bubble);
        }
    };

    window.csQuickSend = function(peerId, text) {
        var state = openBoxes[peerId]; if (!state) return;
        var input = state.el.querySelector('.cs-box-input');
        input.value = text;
        csBoxAutosize(input, peerId);
        // Reuse the normal send path (honors replyState)
        csBoxSend({ preventDefault: function(){} }, peerId);
    };

    window.csUnsendMessage = function(peerId, msgId, bubble) {
        var fd = new FormData();
        if (isGroupId(peerId)) fd.append('group_id', realGid(peerId));
        else                    fd.append('peer_id',  peerId);
        fd.append('unsend_id', msgId);
        fetch(API_MESSAGES, { method: 'POST', body: fd, credentials: 'include' })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d && d.ok) {
                    // Remove bubble + its time + reply quote above
                    var row = bubble.closest('.cs-msg-row');
                    var nextTime = row ? row.nextElementSibling : null;
                    var prevEl   = row ? row.previousElementSibling : null;
                    if (prevEl && prevEl.classList && prevEl.classList.contains('cs-reply-quote')) prevEl.remove();
                    if (row) row.remove();
                    if (nextTime && nextTime.classList && nextTime.classList.contains('cs-msg-time')) nextTime.remove();
                } else if (d && d.error) {
                    alert('Could not unsend: ' + d.error);
                }
            }).catch(function() {});
    };

    // Touch long-press for mobile context menu
    var touchHold = null;
    function csTouchStart(e) {
        var bubble = e.currentTarget;
        touchHold = setTimeout(function() {
            var tch = e.touches && e.touches[0];
            var fakeEv = { clientX: tch ? tch.clientX : 40, clientY: tch ? tch.clientY : 80, preventDefault: function(){} };
            csShowCtx(fakeEv, bubble);
        }, 500);
    }
    function csTouchEnd() {
        if (touchHold) { clearTimeout(touchHold); touchHold = null; }
    }

    // Hide context menu on outside click / scroll / escape
    document.addEventListener('click', function(e) { if (!e.target.closest('.cs-ctx')) csHideCtx(); });
    document.addEventListener('scroll', csHideCtx, true);
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') csHideCtx(); });

    /* ── Emoji effects ──
       When a message contains any of these emojis, trigger a visual effect.
       Effects only fire for messages arriving while the box is open (not on first-load history). */
    var FX_RULES = [
        // anim: rise (floats upward), fall (rains down), burst (explodes outward), bounce (big single bounce)
        { match: ['❤️','💗','💖','💘','💝','💓','💕','💞','🥰','😍'], anim: 'rise',   count: 14, color: '#f472b6', particles: ['❤️','💖','💘','💕'] },
        { match: ['🎉','🎊'],                                            anim: 'fall',   count: 28, color: '#fbbf24', particles: ['🎉','🎊','✨','🎀'] },
        { match: ['🔥'],                                                  anim: 'rise',   count: 12, color: '#f97316', particles: ['🔥','💥'] },
        { match: ['✨','🌟','⭐','💫'],                                    anim: 'burst',  count: 18, color: '#fde047', particles: ['✨','⭐','🌟','💫'] },
        { match: ['👍'],                                                  anim: 'bounce', count: 1,  color: '#60a5fa', particles: ['👍'] },
        { match: ['👏'],                                                  anim: 'burst',  count: 12, color: '#60a5fa', particles: ['👏','✨'] },
        { match: ['💀','☠️'],                                             anim: 'rise',   count: 10, color: '#9ca3af', particles: ['💀','🦴'] },
        { match: ['🏆','🥇'],                                             anim: 'burst',  count: 16, color: '#fbbf24', particles: ['🏆','✨','👑','⭐'] },
        { match: ['🎮','🕹️','⚔️'],                                        anim: 'rise',   count: 10, color: '#a78bfa', particles: ['🎮','⚔️','🛡️','✨'] },
        { match: ['🤣','😂'],                                             anim: 'burst',  count: 14, color: '#fbbf24', particles: ['😂','🤣','💦'] },
        { match: ['💩'],                                                  anim: 'fall',   count: 16, color: '#8b5e3c', particles: ['💩'] },
        { match: ['💥'],                                                  anim: 'burst',  count: 20, color: '#f87171', particles: ['💥','⚡','🔥'] }
    ];

    function detectFxRule(text) {
        if (!text) return null;
        for (var i = 0; i < FX_RULES.length; i++) {
            var r = FX_RULES[i];
            for (var j = 0; j < r.match.length; j++) {
                if (text.indexOf(r.match[j]) !== -1) return r;
            }
        }
        return null;
    }

    function playFx(boxEl, rule) {
        if (!boxEl || !rule) return;
        var body = boxEl.querySelector('.cs-box-body');
        if (!body) return;

        // Flash the box outline with the effect color
        boxEl.style.setProperty('--fx-color', rule.color);
        boxEl.classList.remove('cs-fx-flash');
        void boxEl.offsetWidth;
        boxEl.classList.add('cs-fx-flash');

        // Particle overlay lives inside the messages area (so it scrolls with messages, not composer)
        var overlay = document.createElement('div');
        overlay.className = 'cs-fx';
        body.appendChild(overlay);

        var W = body.clientWidth || 300;
        var H = body.clientHeight || 300;

        for (var i = 0; i < rule.count; i++) {
            var ch = rule.particles[Math.floor(Math.random() * rule.particles.length)];
            var p = document.createElement('span');
            p.className = 'cs-fx-particle';
            p.textContent = ch;

            var delay = Math.random() * 400;
            var dur   = 900 + Math.random() * 900;

            if (rule.anim === 'rise') {
                var startX = Math.random() * W;
                p.style.left = startX + 'px';
                p.style.bottom = '10px';
                p.style.setProperty('--dx1', ((Math.random() - 0.5) * 40) + 'px');
                p.style.setProperty('--dx2', ((Math.random() - 0.5) * 120) + 'px');
                p.style.setProperty('--r1', ((Math.random() - 0.5) * 40) + 'deg');
                p.style.setProperty('--r2', ((Math.random() - 0.5) * 180) + 'deg');
                p.style.fontSize = (1.2 + Math.random() * 1.2) + 'rem';
                p.style.animation = 'csFxRise ' + dur + 'ms ease-out ' + delay + 'ms forwards';
            } else if (rule.anim === 'fall') {
                var startX2 = Math.random() * W;
                p.style.left = startX2 + 'px';
                p.style.top = '-20px';
                p.style.setProperty('--dx', ((Math.random() - 0.5) * 60) + 'px');
                p.style.setProperty('--r', (Math.random() * 720 - 360) + 'deg');
                p.style.fontSize = (1 + Math.random() * 1) + 'rem';
                p.style.animation = 'csFxFall ' + dur + 'ms linear ' + delay + 'ms forwards';
            } else if (rule.anim === 'burst') {
                var angle = Math.random() * Math.PI * 2;
                var dist  = 80 + Math.random() * 120;
                p.style.left = (W/2 - 15) + 'px';
                p.style.top  = (H/2 - 15) + 'px';
                p.style.setProperty('--dx', Math.cos(angle) * dist + 'px');
                p.style.setProperty('--dy', Math.sin(angle) * dist + 'px');
                p.style.fontSize = (1.3 + Math.random() * 0.8) + 'rem';
                p.style.animation = 'csFxBurst ' + dur + 'ms ease-out ' + delay + 'ms forwards';
            } else if (rule.anim === 'bounce') {
                p.style.left = (W/2 - 30) + 'px';
                p.style.bottom = '10px';
                p.style.fontSize = '3.5rem';
                p.style.animation = 'csFxBounce 1500ms cubic-bezier(.33,1.5,.6,1) forwards';
                p.style.filter = 'drop-shadow(0 4px 12px rgba(96,165,250,0.6))';
            }
            overlay.appendChild(p);
        }

        setTimeout(function() { if (overlay.parentNode) overlay.parentNode.removeChild(overlay); }, 2600);
    }

    /* ── Emoji picker ── */
    var EMOJI_CATS = [
        { name: 'Smileys', list: '😀 😃 😄 😁 😆 😅 😂 🤣 😊 😇 🙂 🙃 😉 😌 😍 🥰 😘 😗 😙 😚 😋 😛 😝 😜 🤪 🤨 🧐 🤓 😎 🤩 🥳 😏 😒 😞 😔 😟 😕 🙁 ☹️ 😣 😖 😫 😩 🥺 😢 😭 😤 😠 😡 🤬 🤯 😳 🥵 🥶 😱 😨 😰 😥 😓 🤗 🤔 🤭 🤫 🤥 😶 😐 😑 😬 🙄 😯 😦 😧 😮 😲 🥱 😴 🤤 😪 😵 🤐 🥴 🤢 🤮 🤧 😷 🤒 🤕'.split(' ') },
        { name: 'Gestures', list: '👍 👎 👌 ✌️ 🤞 🤟 🤘 🤙 👈 👉 👆 🖕 👇 ☝️ 👋 🤚 🖐️ ✋ 🖖 👏 🙌 👐 🤲 🤝 🙏 ✍️ 💪 🦾 🦵 🦶 👂 🦻 👃 🧠 🦷 🦴 👀 👁️'.split(' ') },
        { name: 'Hearts', list: '❤️ 🧡 💛 💚 💙 💜 🖤 🤍 🤎 💔 ❣️ 💕 💞 💓 💗 💖 💘 💝 💟 ♥️'.split(' ') },
        { name: 'Gaming', list: '🎮 🕹️ 🎯 🎲 🎰 🎳 🏆 🥇 🥈 🥉 🏅 🎖️ 🏵️ ⚔️ 🛡️ 🔥 💯 ⚡ 💥 ✨ 💫 🌟 ⭐ 🎉 🎊 🏁 🚩 💻 🖥️ ⌨️ 🖱️'.split(' ') },
        { name: 'Misc',    list: '👀 💀 ☠️ 👻 👽 🤖 💩 🤡 🎃 🙈 🙉 🙊 🐵 🐶 🐱 🦁 🐯 🦊 🐻 🐼 🐨 🦄 🦖 🐲 🐉 🍕 🍔 🍟 🍣 🍩 🍺 🍷 ☕ 🎵 🎶 📞 💬 💭 ⏰ 💡 💰 💎'.split(' ') }
    ];

    window.csToggleEmoji = function(ev, peerId) {
        if (ev) ev.stopPropagation();
        var panel = document.getElementById('csEmojiPanel-' + peerId);
        if (!panel) return;
        // Close any other open panels
        document.querySelectorAll('.cs-emoji-panel.on').forEach(function(p) { if (p !== panel) p.classList.remove('on'); });
        if (!panel.classList.contains('on')) {
            if (!panel.dataset.built) {
                var html = '';
                EMOJI_CATS.forEach(function(cat) {
                    html += '<div class="cs-emoji-cat">' + cat.name + '</div>';
                    cat.list.forEach(function(e) {
                        html += '<button type="button" class="cs-emoji-btn-item" onclick="csInsertEmoji(event, ' + peerId + ', \'' + e.replace(/'/g, "\\'") + '\')">' + e + '</button>';
                    });
                });
                panel.innerHTML = html;
                panel.dataset.built = '1';
            }
            panel.classList.add('on');
        } else {
            panel.classList.remove('on');
        }
    };

    window.csInsertEmoji = function(ev, peerId, emoji) {
        if (ev) ev.stopPropagation();
        var state = openBoxes[peerId];
        if (!state) return;
        var input = state.el.querySelector('.cs-box-input');
        var start = input.selectionStart || 0;
        var end   = input.selectionEnd   || 0;
        var val   = input.value || '';
        input.value = val.slice(0, start) + emoji + val.slice(end);
        var pos = start + emoji.length;
        input.focus();
        try { input.setSelectionRange(pos, pos); } catch (e) {}
        csBoxAutosize(input, peerId);
    };

    // Close emoji panels on outside click
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.cs-emoji-panel') && !e.target.closest('.cs-emoji-btn')) {
            document.querySelectorAll('.cs-emoji-panel.on').forEach(function(p) { p.classList.remove('on'); });
        }
    });

    window.csBoxAutosize = function(el, peerId) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 90) + 'px';
        el.closest('form').querySelector('.cs-box-send').disabled = el.value.trim() === '';
        // Debounced draft save
        var state = openBoxes[peerId];
        if (state) {
            clearTimeout(state.draftTimer);
            state.draftTimer = setTimeout(function() { saveDraft(peerId, el.value); }, 350);

            // Typing ping (throttled once per 3s)
            var now = Date.now();
            if (el.value.trim() !== '' && (!state.lastTypingPing || (now - state.lastTypingPing) > 3000)) {
                state.lastTypingPing = now;
                csPostTyping(peerId, 1);
                // Auto-stop after 5s of inactivity
                clearTimeout(state.typingStopTimer);
                state.typingStopTimer = setTimeout(function() {
                    csPostTyping(peerId, 0);
                    state.lastTypingPing = 0;
                }, 5000);
            }
        }
    };

    /* ── Image attach ── */
    window.csPickAttach = function(input, peerId) {
        if (!input.files || !input.files[0]) return;
        var f = input.files[0];
        if (f.size > 5 * 1024 * 1024) { alert('Image is too large (max 5 MB).'); input.value = ''; return; }
        if (!/^image\//.test(f.type)) { alert('Please pick an image file.'); input.value = ''; return; }
        var state = openBoxes[peerId]; if (!state) return;
        state.attachedFile = f;
        var r = new FileReader();
        r.onload = function(e) {
            var prev = document.getElementById('csAttachPrev-' + peerId);
            prev.querySelector('img').src = e.target.result;
            prev.classList.add('on');
            // Enable send button even without text
            var sendBtn = state.el.querySelector('.cs-box-send');
            if (sendBtn) sendBtn.disabled = false;
        };
        r.readAsDataURL(f);
    };

    window.csClearAttach = function(peerId) {
        var state = openBoxes[peerId];
        if (state) state.attachedFile = null;
        var prev = document.getElementById('csAttachPrev-' + peerId);
        if (prev) { prev.classList.remove('on'); prev.querySelector('img').src = ''; }
        var input = document.querySelector('#csBox-' + peerId + ' .cs-attach-btn input');
        if (input) input.value = '';
        // Re-evaluate send button
        var state2 = openBoxes[peerId];
        if (state2) {
            var ta = state2.el.querySelector('.cs-box-input');
            var sendBtn = state2.el.querySelector('.cs-box-send');
            if (sendBtn) sendBtn.disabled = ta.value.trim() === '';
        }
    };

    window.csShowLightbox = function(url) {
        var box = document.getElementById('csLightbox');
        document.getElementById('csLightboxImg').src = url;
        box.classList.add('on');
    };
    window.csHideLightbox = function() {
        var box = document.getElementById('csLightbox');
        box.classList.remove('on');
        document.getElementById('csLightboxImg').src = '';
    };

    function csPostTyping(peerId, typing) {
        var fd = new FormData();
        if (isGroupId(peerId)) fd.append('group_id', realGid(peerId));
        else                    fd.append('peer_id',  peerId);
        fd.append('typing', typing);
        fetch(API_MESSAGES, { method: 'POST', body: fd, credentials: 'include' }).catch(function(){});
    }
    window.csBoxKey = function(e, peerId) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            csBoxSend(e, peerId);
        }
    };
    window.csBoxSend = function(e, peerId) {
        e.preventDefault();
        peerId = parseInt(peerId);
        var state = openBoxes[peerId]; if (!state) return false;
        if (state.sending) return false;  // in-flight lock
        var input = state.el.querySelector('.cs-box-input');
        var sendBtn = state.el.querySelector('.cs-box-send');
        var txt = input.value.trim();
        var hasImage = !!state.attachedFile;
        if (!txt && !hasImage) return false;

        state.sending = true;
        var peerUser = usersCache.find(function(x) { return parseInt(x.id) === peerId; }) || {};

        // Prepend reply marker if user is replying
        var finalContent = txt;
        if (replyState[peerId]) {
            finalContent = '↩️[#' + replyState[peerId].msgId + ']::' + txt;
        }

        // Client-side nonce for server dedupe
        var nonce = Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);

        var fd = new FormData();
        if (isGroupId(peerId)) fd.append('group_id', realGid(peerId));
        else                    fd.append('peer_id',  peerId);
        fd.append('content', finalContent);
        fd.append('nonce',   nonce);
        if (state.attachedFile) fd.append('image', state.attachedFile);
        input.value = '';
        input.style.height = 'auto';
        sendBtn.disabled = true;
        saveDraft(peerId, ''); // clear persisted draft once sent
        csCancelReply(peerId); // clear reply state once sent
        csClearAttach(peerId); // clear any attached image
        // Stop typing indicator
        clearTimeout(state.typingStopTimer);
        state.lastTypingPing = 0;
        csPostTyping(peerId, 0);

        fetch(API_MESSAGES, { method: 'POST', body: fd, credentials: 'include' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                state.sending = false;
                if (d && d.error) {
                    // Restore text so the user can retry (image would need re-pick)
                    input.value = txt;
                    csBoxAutosize(input, peerId);
                    alert('Failed to send: ' + d.error + (hasImage ? ' (please re-select your image)' : ''));
                    return;
                }
                csPollBox(peerId, false);
                csLoadUsers();
                // Note: server-side logs this to activity_logs automatically (see api/chat-messages.php)
            })
            .catch(function() {
                state.sending = false;
                // Network failure — put the text back so user can retry manually
                input.value = txt;
                csBoxAutosize(input, peerId);
            });
        return false;
    };

    /* ── Guest login modal ── */
    window.csShowLoginModal = function(u) {
        var modal = document.getElementById('csModal');
        if (u && u.display_name) {
            document.getElementById('csModalTitle').textContent = 'Chat with ' + u.display_name;
            document.getElementById('csModalSub').textContent   = 'Log in or create an account to start chatting with Apex Cybernet players.';
        }
        modal.classList.add('on');
    };
    window.csCloseModal = function(e) {
        document.getElementById('csModal').classList.remove('on');
    };
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') csCloseModal();
    });

    /* ── Voice calls (group, WebRTC mesh via Node SSE signaling) ── */
    var voiceCalls = {}; // peerId (group key) -> { active, token, peerId, groupId, pcs:{peerId:RTCPeerConnection}, localStream, es, audioEls:{peerId:<audio>}, tickTimer, startedAt, muted, analysers:{peerId:{an,speaking}}, iceServers }

    /* ── Opus SDP tweak: higher bitrate, FEC on, mono for voice ── */
    function csTuneOpusSdp(sdp) {
        // Find the Opus payload type number
        var m = sdp.match(/a=rtpmap:(\d+) opus\/\d+\/\d+/);
        if (!m) return sdp;
        var pt = m[1];
        // If there's already an a=fmtp: line for this PT, extend it; otherwise inject
        var fmtpRe = new RegExp('a=fmtp:' + pt + ' ([^\\r\\n]+)', 'g');
        if (fmtpRe.test(sdp)) {
            return sdp.replace(fmtpRe, function(_, params) {
                var parts = params.split(';').map(function(s){ return s.trim(); });
                function ensure(key, val) {
                    for (var i = 0; i < parts.length; i++) {
                        if (parts[i].indexOf(key + '=') === 0) { parts[i] = key + '=' + val; return; }
                    }
                    parts.push(key + '=' + val);
                }
                ensure('stereo', '0');
                ensure('maxaveragebitrate', '48000');
                ensure('useinbandfec', '1');
                ensure('usedtx', '0');
                ensure('cbr', '0');
                return 'a=fmtp:' + pt + ' ' + parts.join(';');
            });
        }
        // No existing fmtp — inject a new one after the rtpmap
        return sdp.replace(
            new RegExp('(a=rtpmap:' + pt + ' opus\\/\\d+\\/\\d+)'),
            '$1\r\na=fmtp:' + pt + ' stereo=0;maxaveragebitrate=48000;useinbandfec=1;usedtx=0;cbr=0'
        );
    }

    function csBuildRtcConfig(state) {
        return {
            iceServers: (state && state.iceServers) || [
                // Absolute fallback if server-side didn't provide any
                { urls: ['stun:stun.l.google.com:19302', 'stun:stun1.l.google.com:19302'] }
            ],
            iceCandidatePoolSize: 4,
            iceTransportPolicy: 'all'
        };
    }

    /* ── Voice invite (DM ringing flow) ── */
    var INVITE_API     = '<?= base_url('api/voice-invite.php') ?>';
    var outgoingInvites = {};   // peerId → { id, startedAt, tickTimer }
    var currentRingingInvite = null;  // active incoming invite shown in modal
    var invitePollTimer = null;
    var ringtoneCtx = null, ringtoneOsc = null;

    function csInvitePollStart() {
        if (invitePollTimer) return;
        csInvitePoll();
        invitePollTimer = setInterval(csInvitePoll, 3000);
    }

    function csInvitePoll() {
        if (IS_GUEST) return;
        fetch(INVITE_API + '?action=poll', { credentials: 'include' })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.error) return;
                // Incoming: show ring modal for the newest pending one
                var inc = (d.incoming || [])[0];
                if (inc) {
                    if (!currentRingingInvite || currentRingingInvite.id !== parseInt(inc.id)) {
                        csShowRing(inc);
                    }
                } else if (currentRingingInvite) {
                    // Caller cancelled or it timed out
                    csHideRing();
                }
                // Outgoing: react to acceptance/decline
                (d.outgoing || []).forEach(function(out) {
                    var pid = parseInt(out.callee_id);
                    var rec = outgoingInvites[pid];
                    if (!rec || rec.id !== parseInt(out.id)) return;
                    if (out.status === 'accepted') {
                        // Peer picked up — clear ringing state, join the room
                        csClearOutgoing(pid);
                        csVoiceJoin(pid);
                    } else if (out.status === 'declined' || out.status === 'timeout' || out.status === 'cancelled') {
                        var label = out.status === 'declined' ? 'declined' : (out.status === 'timeout' ? 'didn\'t answer' : 'cancelled');
                        csClearOutgoing(pid);
                        csToast((out.callee_name || 'Peer') + ' ' + label + ' the call');
                    }
                });
            })
            .catch(function(){});
    }

    window.csVoiceInvite = function(peerId) {
        peerId = parseInt(peerId);
        if (IS_GUEST) { csShowLoginModal({ display_name: 'Voice call' }); return; }
        if (outgoingInvites[peerId]) return;
        document.querySelectorAll('.cs-settings-menu.on').forEach(function(m){ m.classList.remove('on'); });

        var u = usersCache.find(function(x){ return parseInt(x.id) === peerId; }) || {};
        var calling = document.getElementById('csCalling-' + peerId);
        var nameEl  = document.getElementById('csCallingName-' + peerId);
        if (nameEl) nameEl.textContent = u.display_name || 'Player';
        if (calling) calling.classList.add('on');
        // If chatbox isn't open, open it so the user sees the calling state
        if (!openBoxes[peerId]) csOpenBox(peerId);

        var fd = new FormData();
        fd.append('action', 'invite');
        fd.append('peer_id', peerId);
        fetch(INVITE_API + '?action=invite', { method: 'POST', body: fd, credentials: 'include' })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.error) {
                    if (calling) calling.classList.remove('on');
                    alert('Could not start call: ' + d.error);
                    return;
                }
                outgoingInvites[peerId] = { id: parseInt(d.invite_id), startedAt: Date.now() };
                csInvitePollStart();
                // Tick the elapsed timer
                outgoingInvites[peerId].tickTimer = setInterval(function() {
                    var rec = outgoingInvites[peerId]; if (!rec) return;
                    var s = Math.floor((Date.now() - rec.startedAt) / 1000);
                    var t = document.getElementById('csCallingTime-' + peerId);
                    if (t) t.textContent = s + 's';
                    // Auto-cancel after 30s
                    if (s >= 30) csVoiceCancel(peerId);
                }, 1000);
            })
            .catch(function(){
                if (calling) calling.classList.remove('on');
                alert('Network error.');
            });
    };

    window.csVoiceCancel = function(peerId) {
        peerId = parseInt(peerId);
        var rec = outgoingInvites[peerId];
        if (!rec) return;
        var fd = new FormData();
        fd.append('action', 'respond');
        fd.append('invite_id', rec.id);
        fd.append('decision', 'cancel');
        fetch(INVITE_API + '?action=respond', { method: 'POST', body: fd, credentials: 'include' }).catch(function(){});
        csClearOutgoing(peerId);
    };

    function csClearOutgoing(peerId) {
        var rec = outgoingInvites[peerId];
        if (!rec) return;
        if (rec.tickTimer) clearInterval(rec.tickTimer);
        delete outgoingInvites[peerId];
        var calling = document.getElementById('csCalling-' + peerId);
        if (calling) calling.classList.remove('on');
    }

    function csShowRing(inv) {
        currentRingingInvite = { id: parseInt(inv.id), caller_id: parseInt(inv.caller_id), room_id: inv.room_id };
        var modal = document.getElementById('csRing');
        modal.dataset.invite = inv.id;
        modal.dataset.caller = inv.caller_id;
        modal.dataset.room   = inv.room_id;
        document.getElementById('csRingName').textContent = inv.caller_name || ('User ' + inv.caller_id);
        var av = document.getElementById('csRingAv');
        av.innerHTML = inv.caller_pic ? '<img src="' + BASE + '/' + esc(inv.caller_pic) + '">' : '<i class="bi bi-person-fill"></i>';
        modal.classList.add('on');
        csStartRingtone();
    }

    function csHideRing() {
        currentRingingInvite = null;
        var modal = document.getElementById('csRing');
        if (modal) modal.classList.remove('on');
        csStopRingtone();
    }

    window.csRingRespond = function(decision) {
        if (!currentRingingInvite) return;
        var inv = currentRingingInvite;
        if (decision === 'accept' || decision === 'listen') {
            var fd = new FormData();
            fd.append('action', 'respond');
            fd.append('invite_id', inv.id);
            fd.append('decision', 'accept');
            fetch(INVITE_API + '?action=respond', { method: 'POST', body: fd, credentials: 'include' })
                .then(function(r){ return r.json(); })
                .then(function() {
                    csHideRing();
                    // Open peer's chatbox + join voice room
                    if (!openBoxes[inv.caller_id]) csOpenBox(inv.caller_id);
                    setTimeout(function() { csVoiceJoin(inv.caller_id, decision === 'listen'); }, 200);
                });
        } else {
            var fd = new FormData();
            fd.append('action', 'respond');
            fd.append('invite_id', inv.id);
            fd.append('decision', 'decline');
            fetch(INVITE_API + '?action=respond', { method: 'POST', body: fd, credentials: 'include' });
            csHideRing();
        }
    };

    /* ── Ringtone (Web Audio chime loop while ringing) ──
       Skips entirely if the user hasn't interacted with the page yet (Chrome blocks audio).
       The ring modal is still visible — they just don't get the chime until first interaction. */
    function csStartRingtone() {
        if (!csUserHasInteracted) return; // can't start audio yet
        try {
            ringtoneCtx = ringtoneCtx || new (window.AudioContext || window.webkitAudioContext)();
            if (ringtoneCtx.state === 'suspended') {
                ringtoneCtx.resume().catch(function(){});
            }
            if (ringtoneOsc) return;
            var loop = function() {
                if (!currentRingingInvite) return;
                if (ringtoneCtx.state !== 'running') { setTimeout(loop, 1400); return; }
                try {
                    var t = ringtoneCtx.currentTime;
                    [880, 660].forEach(function(freq, i) {
                        var o = ringtoneCtx.createOscillator(); var g = ringtoneCtx.createGain();
                        o.frequency.value = freq; o.type = 'sine';
                        g.gain.setValueAtTime(0.0001, t + i*0.18);
                        g.gain.exponentialRampToValueAtTime(0.06, t + i*0.18 + 0.02);
                        g.gain.exponentialRampToValueAtTime(0.0001, t + i*0.18 + 0.18);
                        o.connect(g); g.connect(ringtoneCtx.destination);
                        o.start(t + i*0.18); o.stop(t + i*0.18 + 0.2);
                    });
                } catch (e) {}
                setTimeout(loop, 1400);
            };
            loop();
            ringtoneOsc = true;
        } catch (e) {}
    }
    function csStopRingtone() {
        ringtoneOsc = null;
    }

    /* ── Global "user has interacted" tracker — required by Chrome to start audio ── */
    var csUserHasInteracted = false;
    function csMarkInteracted() {
        if (csUserHasInteracted) return;
        csUserHasInteracted = true;
        // Resume any AudioContexts that might be suspended
        if (ringtoneCtx && ringtoneCtx.state === 'suspended') ringtoneCtx.resume().catch(function(){});
        Object.values(voiceCalls).forEach(function(s) {
            if (s && s.audioCtx && s.audioCtx.state === 'suspended') s.audioCtx.resume().catch(function(){});
        });
    }
    document.addEventListener('click',    csMarkInteracted, { once: false });
    document.addEventListener('keydown',  csMarkInteracted, { once: false });
    document.addEventListener('touchend', csMarkInteracted, { once: false });

    /* ── Toast for call status ── */
    function csToast(msg) {
        var t = document.createElement('div');
        t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:var(--bg-card);border:1px solid var(--border);border-radius:10px;padding:0.7rem 1.1rem;font-size:0.85rem;color:var(--text);z-index:3001;box-shadow:0 8px 24px rgba(0,0,0,0.5);';
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(function(){ try { t.remove(); } catch(e){} }, 3500);
    }

    // Quick-click button beside gear: start call if not in one, otherwise scroll the voice panel into view
    window.csVoiceQuickClick = function(key) {
        key = parseInt(key);
        if (voiceCalls[key] && voiceCalls[key].active) {
            var panel = document.getElementById('csVoice-' + key);
            if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return;
        }
        if (outgoingInvites[key]) return; // already ringing
        // DM = ring/accept flow; group = direct join (anyone can join Discord-style)
        if (isGroupId(key)) csVoiceJoin(key);
        else                csVoiceInvite(key);
    };

    function csVoiceQuickSync(key) {
        var btn = document.getElementById('csVoiceQuick-' + key);
        if (!btn) return;
        var live = voiceCalls[key] && voiceCalls[key].active;
        btn.classList.toggle('live', !!live);
        btn.setAttribute('title', live ? 'Voice call in progress' : 'Start voice call');
        btn.querySelector('i').className = live ? 'bi bi-mic-fill' : 'bi bi-mic-fill';
    }

    window.csVoiceJoinListenOnly = function(key) { return csVoiceJoin(key, true); };

    window.csVoiceJoin = async function(key, listenOnly) {
        console.log('[voice] join request, key=', key, 'listenOnly=', !!listenOnly);
        key = parseInt(key);
        var isGroup = isGroupId(key);
        if (voiceCalls[key] && voiceCalls[key].active) { console.log('[voice] already active'); return; }
        document.querySelectorAll('.cs-settings-menu.on').forEach(function(m){ m.classList.remove('on'); });

        // Open the panel IMMEDIATELY so there's visual feedback
        var panel = document.getElementById('csVoice-' + key);
        if (panel) {
            panel.classList.add('on');
            var membersHost = document.getElementById('csVoiceMembers-' + key);
            if (membersHost) membersHost.innerHTML = '<div style="font-size:0.72rem;color:var(--text-muted);padding:0.3rem 0.2rem;"><i class="bi bi-hourglass-split"></i> Connecting' + (listenOnly ? ' (listen only)' : '') + '…</div>';
        }

        // 1) Ask PHP for a token (group_id for groups, peer_id for DMs)
        var tokRes;
        try {
            var fd = new FormData();
            if (isGroup) { fd.append('group_id', realGid(key)); console.log('[voice] requesting GROUP token for', realGid(key)); }
            else         { fd.append('peer_id',  key);          console.log('[voice] requesting DM token for peer', key); }
            var r = await fetch('<?= base_url('api/voice-token.php') ?>', { method: 'POST', body: fd, credentials: 'include' });
            tokRes = await r.json();
            console.log('[voice] token response', tokRes);
            if (!tokRes.ok) {
                if (panel) panel.classList.remove('on');
                alert('Voice unavailable: ' + (tokRes.error || 'unknown') + (tokRes.hint ? ' — ' + tokRes.hint : ''));
                return;
            }
        } catch (e) {
            console.error('[voice] token fetch error', e);
            if (panel) panel.classList.remove('on');
            alert('Voice unavailable: network error'); return;
        }

        // 2) Get mic — SKIPPED in listen-only mode
        var stream = null;
        if (!listenOnly) {
            try {
                console.log('[voice] requesting microphone');
                stream = await navigator.mediaDevices.getUserMedia({
                    audio: {
                        echoCancellation: { ideal: true },
                        noiseSuppression: { ideal: true },
                        autoGainControl:  { ideal: true },
                        channelCount:     { ideal: 1 },
                        sampleRate:       { ideal: 48000 },
                        sampleSize:       { ideal: 16 }
                    },
                    video: false
                });
                console.log('[voice] got mic stream');
            } catch (e) {
                console.error('[voice] mic error', e);
                // Auto-fallback to listen-only if mic is unavailable
                if (confirm('Microphone unavailable. Join as listen-only instead?')) {
                    listenOnly = true;
                } else {
                    if (panel) panel.classList.remove('on');
                    return;
                }
            }
        } else {
            console.log('[voice] skipping mic request (listen-only mode)');
        }

        // 3) Init call state
        var state = {
            active: true, token: tokRes.token, peerId: tokRes.peer_id, roomId: tokRes.room_id,
            pcs: {}, localStream: stream, es: null, audioEls: {},
            startedAt: Date.now(), muted: listenOnly, listenOnly: !!listenOnly, analysers: {},
            iceServers: tokRes.ice_servers || null
        };
        console.log('[voice] ICE servers from token:', state.iceServers);
        voiceCalls[key] = state;

        // Mute button + UI reflect listen-only state
        if (listenOnly) {
            var micBtn = document.getElementById('csVoiceMic-' + key);
            if (micBtn) {
                micBtn.disabled = true;
                micBtn.innerHTML = '<i class="bi bi-headphones"></i> Listening';
                micBtn.style.opacity = '0.6';
                micBtn.style.cursor = 'default';
            }
            // Tell the room we're muted (listen-only)
            fetch('/rtc/mic', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ token: state.token, mic: false }) }).catch(function(){});
        }

        // UI
        if (panel) panel.classList.add('on');
        csVoiceRender(key);
        csVoiceQuickSync(key);
        state.tickTimer = setInterval(function() { csVoiceTick(key); }, 1000);
        if (stream) csVoiceStartSpeechAnalyser(key, state.peerId, stream, true);

        // 4) Connect to signaling SSE
        var es = new EventSource('/rtc/stream?token=' + encodeURIComponent(state.token));
        state.es = es;
        es.addEventListener('message', function(ev) {
            var msg; try { msg = JSON.parse(ev.data); } catch { return; }
            csVoiceOnSignal(key, msg);
        });
        es.addEventListener('error', function() {
            if (state.active) { console.warn('[voice] SSE error'); /* auto-reconnect handled by browser */ }
        });
    };

    async function csVoiceOnSignal(key, msg) {
        var state = voiceCalls[key]; if (!state || !state.active) return;

        if (msg.type === 'roster') {
            // Create offers to everyone already in the room
            for (var i = 0; i < (msg.members || []).length; i++) {
                var m = msg.members[i];
                await csVoiceConnectToPeer(key, m.peer_id, m.display_name || ('#' + m.peer_id), true);
            }
            csVoiceRender(key);
        } else if (msg.type === 'join') {
            // A new peer joined — wait for them to send us an offer
            state.pcs[msg.peer.peer_id] = state.pcs[msg.peer.peer_id] || null;
            state.__peerNames = state.__peerNames || {};
            state.__peerNames[msg.peer.peer_id] = msg.peer.display_name;
            csVoiceRender(key);
        } else if (msg.type === 'leave') {
            csVoiceDropPeer(key, msg.peer_id);
            csVoiceRender(key);
        } else if (msg.type === 'signal') {
            await csVoiceHandleSignal(key, msg.from, msg.data);
        } else if (msg.type === 'mic') {
            state.__peerMic = state.__peerMic || {};
            state.__peerMic[msg.peer_id] = msg.mic;
            csVoiceRender(key);
        }
    }

    async function csVoiceConnectToPeer(key, remoteId, remoteName, createOffer) {
        var state = voiceCalls[key]; if (!state) return;
        console.log('[voice] connect to peer', remoteId, 'createOffer=', createOffer);
        var pc = new RTCPeerConnection(csBuildRtcConfig(state));
        state.pcs[remoteId] = pc;
        state.__peerNames = state.__peerNames || {};
        if (remoteName) state.__peerNames[remoteId] = remoteName;

        // Local tracks (or recvonly transceiver if listen-only)
        if (state.localStream && !state.listenOnly) {
            state.localStream.getTracks().forEach(function(t) {
                pc.addTrack(t, state.localStream);
                console.log('[voice] added local track', t.kind, 'enabled=', t.enabled);
            });
        } else {
            // Listen-only: explicitly request to receive audio without sending any
            try { pc.addTransceiver('audio', { direction: 'recvonly' }); console.log('[voice] added recvonly audio transceiver'); }
            catch (e) { console.warn('[voice] addTransceiver failed', e); }
        }

        pc.ontrack = function(ev) {
            console.log('[voice] GOT REMOTE TRACK from', remoteId, 'kind=', ev.track.kind, 'streams=', ev.streams.length);
            var stream = ev.streams[0] || new MediaStream([ev.track]);
            // Route through Web Audio — playback AND speaking analysis from one source node.
            // This avoids a Chrome bug where <audio>.srcObject is silenced if Web Audio also
            // taps the same MediaStream (createMediaStreamSource hijacks output).
            csVoiceAttachRemote(key, remoteId, stream, pc);
        };

        pc.onicecandidate = function(ev) {
            if (ev.candidate) {
                console.log('[voice] local ICE →', remoteId, ev.candidate.candidate.slice(0, 80));
                csVoicePostSignal(key, remoteId, { kind: 'ice', candidate: ev.candidate });
            } else {
                console.log('[voice] ICE gathering complete for', remoteId);
            }
        };

        pc.oniceconnectionstatechange = function() {
            console.log('[voice] ICE state →', remoteId, pc.iceConnectionState);
        };

        pc.onconnectionstatechange = function() {
            console.log('[voice] connection state →', remoteId, pc.connectionState);
        };

        if (createOffer) {
            try {
                var offer = await pc.createOffer();
                // Bump Opus bitrate + FEC for cleaner voice
                offer.sdp = csTuneOpusSdp(offer.sdp);
                await pc.setLocalDescription(offer);
                console.log('[voice] sending offer →', remoteId);
                csVoicePostSignal(key, remoteId, { kind: 'sdp', sdp: offer });
            } catch (e) { console.error('[voice] createOffer failed', e); }
        }
    }

    async function csVoiceHandleSignal(key, fromId, data) {
        var state = voiceCalls[key]; if (!state) return;
        var pc = state.pcs[fromId];
        if (!pc) {
            await csVoiceConnectToPeer(key, fromId, null, false);
            pc = state.pcs[fromId];
        }
        if (data.kind === 'sdp') {
            console.log('[voice] received', data.sdp.type, 'from', fromId);
            try {
                if (data.sdp.type === 'offer') {
                    await pc.setRemoteDescription(new RTCSessionDescription(data.sdp));
                    var ans = await pc.createAnswer();
                    ans.sdp = csTuneOpusSdp(ans.sdp);
                    await pc.setLocalDescription(ans);
                    csVoicePostSignal(key, fromId, { kind: 'sdp', sdp: ans });
                    console.log('[voice] sent answer →', fromId);
                } else if (data.sdp.type === 'answer') {
                    await pc.setRemoteDescription(new RTCSessionDescription(data.sdp));
                }
            } catch (e) { console.error('[voice] SDP handling failed', e); }
        } else if (data.kind === 'ice' && data.candidate) {
            try { await pc.addIceCandidate(new RTCIceCandidate(data.candidate)); }
            catch (e) { console.warn('[voice] addIceCandidate failed', e); }
        }
    }

    function csVoicePostSignal(key, toId, data) {
        var state = voiceCalls[key]; if (!state) return;
        fetch('/rtc/signal', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: state.token, to: toId, data: data })
        }).catch(function(){});
    }

    /* ── Attach remote stream: <audio> for playback (best quality, platform EC/NS),
       cloned track for Web Audio analysis (avoids Chrome silence bug). ── */
    function csVoiceAttachRemote(key, remoteId, stream, pc) {
        var state = voiceCalls[key]; if (!state) return;

        // Drop any prior entry
        if (state.analysers[remoteId]) {
            try { state.analysers[remoteId].disconnect && state.analysers[remoteId].disconnect(); } catch {}
            delete state.analysers[remoteId];
        }

        // ── 1. PLAYBACK via <audio> element (keeps full browser audio pipeline: EC, NS, AGC, resampling) ──
        if (!state.audioEls[remoteId]) {
            var a = document.createElement('audio');
            a.autoplay = true;
            a.setAttribute('playsinline', '');
            a.muted = false;
            a.volume = 1.0;
            a.id = 'csRA-' + key + '-' + remoteId;
            document.body.appendChild(a);
            state.audioEls[remoteId] = a;
        }
        state.audioEls[remoteId].srcObject = stream;
        var p = state.audioEls[remoteId].play();
        if (p && p.catch) {
            p.catch(function(err) {
                console.error('[voice] AUDIO PLAY BLOCKED for', remoteId, err);
                csShowAudioUnlock(key, remoteId);
            });
        }

        // ── 2. ANALYSIS on a CLONED track — doesn't silence the <audio> element on Chrome ──
        var tracks = stream.getAudioTracks();
        if (tracks.length > 0 && tracks[0].clone) {
            try {
                if (!state.audioCtx) {
                    state.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                }
                var ctx = state.audioCtx;
                if (ctx.state === 'suspended') ctx.resume().catch(function(){});

                var cloneStream = new MediaStream([tracks[0].clone()]);
                var src = ctx.createMediaStreamSource(cloneStream);
                var an  = ctx.createAnalyser(); an.fftSize = 256;
                src.connect(an);  // analysis-only — NOT connected to ctx.destination (no audible output from Web Audio)

                var entry = {
                    speaking: false, isSelf: false,
                    disconnect: function() { try { src.disconnect(); an.disconnect(); } catch {} }
                };
                state.analysers[remoteId] = entry;

                var buf = new Uint8Array(an.frequencyBinCount);
                function loop() {
                    if (!voiceCalls[key] || !voiceCalls[key].active || !state.analysers[remoteId]) return;
                    an.getByteFrequencyData(buf);
                    var sum = 0; for (var i = 0; i < buf.length; i++) sum += buf[i];
                    var avg = sum / buf.length;
                    var speaking = avg > 18;
                    if (speaking !== entry.speaking) { entry.speaking = speaking; csVoiceRender(key); }
                    requestAnimationFrame(loop);
                }
                loop();
            } catch (e) { console.warn('[voice] analyser clone setup failed', e); }
        }

        // Diagnostic stats
        setTimeout(function() {
            if (!pc) return;
            pc.getStats(null).then(function(stats) {
                var seen = false;
                stats.forEach(function(rep) {
                    if (rep.type === 'inbound-rtp' && rep.kind === 'audio') {
                        seen = true;
                        console.log('[voice] inbound audio from ' + remoteId + ':',
                            'bytes=', rep.bytesReceived, 'packets=', rep.packetsReceived, 'jitter=', rep.jitter, 'lost=', rep.packetsLost);
                    }
                });
                if (!seen) console.warn('[voice] no inbound audio RTP stats yet for', remoteId);
            });
        }, 2000);
    }

    function csVoiceDropPeer(key, remoteId) {
        var state = voiceCalls[key]; if (!state) return;
        if (state.pcs[remoteId]) { try { state.pcs[remoteId].close(); } catch {} delete state.pcs[remoteId]; }
        if (state.audioEls[remoteId]) { try { state.audioEls[remoteId].srcObject = null; state.audioEls[remoteId].remove(); } catch {} delete state.audioEls[remoteId]; }
        if (state.analysers[remoteId]) { try { state.analysers[remoteId].ctx.close(); } catch {} delete state.analysers[remoteId]; }
    }

    /* ── If the browser blocks autoplay despite our user-gesture chain,
       show a one-time button the user MUST click to start audio playback. */
    function csShowAudioUnlock(key, remoteId) {
        if (document.getElementById('csAudioUnlock')) return;
        var box = document.createElement('div');
        box.id = 'csAudioUnlock';
        box.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:5000;background:#7c3aed;color:#fff;padding:1rem 1.5rem;border-radius:12px;font-weight:800;box-shadow:0 8px 28px rgba(0,0,0,0.5);cursor:pointer;font-size:0.9rem;display:flex;align-items:center;gap:0.6rem;';
        box.innerHTML = '<i class="bi bi-volume-up-fill"></i> Click to enable voice audio';
        box.onclick = function() {
            Object.keys(voiceCalls).forEach(function(k) {
                var s = voiceCalls[k];
                if (!s || !s.active) return;
                if (s.audioCtx && s.audioCtx.state === 'suspended') s.audioCtx.resume().catch(function(){});
                Object.values(s.audioEls || {}).forEach(function(el) { try { el.play(); } catch (e) {} });
            });
            box.remove();
        };
        document.body.appendChild(box);
    }

    window.csVoiceLeave = function(key) {
        key = parseInt(key);
        var state = voiceCalls[key]; if (!state) return;
        state.active = false;
        if (state.es) try { state.es.close(); } catch {}
        Object.keys(state.pcs).forEach(function(id){ csVoiceDropPeer(key, parseInt(id)); });
        if (state.localStream) state.localStream.getTracks().forEach(function(t){ t.stop(); });
        if (state.tickTimer) clearInterval(state.tickTimer);
        // Close shared AudioContext used for remote playback + analysis
        if (state.audioCtx) { try { state.audioCtx.close(); } catch {} }
        // Close self-analyser ctx (separate one, see csVoiceStartSpeechAnalyser)
        if (state.analysers[state.peerId] && state.analysers[state.peerId].ctx) {
            try { state.analysers[state.peerId].ctx.close(); } catch {}
        }
        delete voiceCalls[key];
        var panel = document.getElementById('csVoice-' + key);
        if (panel) panel.classList.remove('on');
        // Reset mute button (was disabled if listen-only)
        var micBtn = document.getElementById('csVoiceMic-' + key);
        if (micBtn) {
            micBtn.disabled = false;
            micBtn.classList.remove('on');
            micBtn.style.opacity = '';
            micBtn.style.cursor = '';
            micBtn.innerHTML = '<i class="bi bi-mic-fill"></i> Mute';
        }
        csVoiceQuickSync(key);
    };

    window.csVoiceMute = function(key) {
        var state = voiceCalls[key]; if (!state) return;
        state.muted = !state.muted;
        state.localStream.getAudioTracks().forEach(function(t){ t.enabled = !state.muted; });
        var btn = document.getElementById('csVoiceMic-' + key);
        if (btn) {
            btn.classList.toggle('on', state.muted);
            btn.innerHTML = state.muted ? '<i class="bi bi-mic-mute-fill"></i> Unmute' : '<i class="bi bi-mic-fill"></i> Mute';
        }
        // Broadcast mic state
        fetch('/rtc/mic', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: state.token, mic: !state.muted })
        }).catch(function(){});
        csVoiceRender(key);
    };

    function csVoiceTick(key) {
        var state = voiceCalls[key]; if (!state) return;
        var s = Math.floor((Date.now() - state.startedAt) / 1000);
        var mm = String(Math.floor(s / 60)).padStart(2, '0');
        var ss = String(s % 60).padStart(2, '0');
        var el = document.getElementById('csVoiceTime-' + key);
        if (el) el.textContent = mm + ':' + ss;
    }

    function csVoiceRender(key) {
        var state = voiceCalls[key]; if (!state) return;
        var host = document.getElementById('csVoiceMembers-' + key); if (!host) return;
        var ids = Object.keys(state.pcs).map(Number);
        ids.unshift(state.peerId);  // include self
        var names = state.__peerNames || {};
        var mics  = state.__peerMic   || {};
        host.innerHTML = ids.map(function(id) {
            // Resolve name: own → "You"; otherwise lookup in __peerNames first, then usersCache (for DMs)
            var name;
            if (id === state.peerId) {
                name = 'You';
            } else if (names[id]) {
                name = names[id];
            } else {
                var u = usersCache.find(function(x){ return parseInt(x.id) === id; });
                name = (u && u.display_name) || ('#' + id);
            }
            var isSelf = id === state.peerId;
            var mic  = isSelf ? !state.muted : (mics[id] !== false);
            // Listen-only detection: self via state.listenOnly, others via persistent mic=false
            var listenOnly = isSelf ? state.listenOnly : false;
            var an   = state.analysers[id];
            var speakingCls = (an && an.speaking) ? ' speaking' : '';
            var mutedCls    = mic ? '' : ' muted';
            var nameSuffix  = listenOnly ? ' <span style="font-size:0.6rem;color:#60a5fa;font-weight:700;">(listening)</span>' : '';
            var icon;
            if (listenOnly) icon = 'headphones';
            else            icon = mic ? 'mic-fill' : 'mic-mute-fill';
            return '<div class="cs-voice-member' + speakingCls + mutedCls + '">'
                + '<div class="cs-voice-mav">' + esc((name||'?').slice(0,2).toUpperCase()) + '</div>'
                + '<span class="cs-voice-member-name">' + esc(name) + nameSuffix + '</span>'
                + '<i class="bi bi-' + icon + ' cs-voice-member-mic"' + (listenOnly ? ' style="color:#60a5fa;"' : '') + '></i>'
                + '</div>';
        }).join('');
    }

    function csVoiceStartSpeechAnalyser(key, peerId, stream, isSelf) {
        var state = voiceCalls[key]; if (!state) return;
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            var src = ctx.createMediaStreamSource(stream);
            var an  = ctx.createAnalyser();
            an.fftSize = 256;
            src.connect(an);
            var buf = new Uint8Array(an.frequencyBinCount);
            var entry = { ctx: ctx, speaking: false, isSelf: isSelf };
            state.analysers[peerId] = entry;
            function loop() {
                if (!voiceCalls[key] || !voiceCalls[key].active) { try { ctx.close(); } catch {} return; }
                an.getByteFrequencyData(buf);
                var sum = 0; for (var i = 0; i < buf.length; i++) sum += buf[i];
                var avg = sum / buf.length;
                var speaking = avg > 18;
                if (isSelf && state.muted) speaking = false;
                if (speaking !== entry.speaking) {
                    entry.speaking = speaking;
                    csVoiceRender(key);
                }
                requestAnimationFrame(loop);
            }
            loop();
        } catch (e) { /* Web Audio not available, skip detection */ }
    }

    /* ── Init ── */
    function csInitVisibility() {
        var pref = null;
        try { pref = localStorage.getItem('cs-collapsed'); } catch (e) {}
        if (isDesktop() && pref !== '1') {
            csOpen();
        } else {
            document.getElementById('csSide').classList.add('cs-collapsed');
            document.body.classList.remove('cs-enabled');
            csLoadUsers();
            clearInterval(userTimer);
            userTimer = setInterval(csLoadUsers, POLL_USERS_MS);
        }
        // Always poll for incoming voice invites (very lightweight)
        if (!IS_GUEST) csInvitePollStart();
    }

    window.addEventListener('resize', function() {
        if (!isDesktop()) document.body.classList.remove('cs-enabled');
        else if (!document.getElementById('csSide').classList.contains('cs-collapsed')) document.body.classList.add('cs-enabled');
    });

    csInitVisibility();
})();
</script>
