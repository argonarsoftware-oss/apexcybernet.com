<style>
:root {
    --bg: #0f0f13; --surface: #17171f; --surface2: #1e1e28;
    --border: rgba(255,255,255,0.07);
    --accent: #7c3aed; --accent-light: #a78bfa;
    --green: #34d399; --yellow: #fbbf24; --red: #f87171; --blue: #60a5fa; --orange: #fb923c;
}
* { box-sizing: border-box; }
body { background: var(--bg); color: #e5e7eb; font-family: 'Inter', system-ui, sans-serif; margin: 0; font-size: 14px; }
a { text-decoration: none; }

/* ── Topbar ── */
.topbar { background: var(--surface); border-bottom: 1px solid var(--border); padding: 0.75rem 1.5rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
.topbar h1 { margin: 0; font-size: 1.05rem; font-weight: 800; color: #fff; }
.topbar a  { color: var(--accent-light); font-size: 0.82rem; }
.topbar a:hover { color: #fff; }

.wrap { padding: 1.5rem; max-width: 1600px; }

/* ── Date tab pills ── */
.date-tabs { display: flex; gap: 0.4rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.date-tab { padding: 0.35rem 1rem; border-radius: 20px; font-size: 0.8rem; font-weight: 700;
    border: 1px solid var(--border); color: #9ca3af; cursor: pointer; }
.date-tab.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.date-tab:hover:not(.active) { border-color: var(--accent-light); color: #fff; }

/* ── KPI cards ── */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(155px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.kpi { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 1.1rem 1.3rem; position: relative; overflow: hidden; }
.kpi::before { content:''; position:absolute; inset:0; background: var(--kpi-glow,transparent); pointer-events:none; }
.kpi .kpi-label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #6b7280; margin-bottom: 0.5rem; }
.kpi .kpi-val   { font-size: 1.9rem; font-weight: 900; line-height: 1; color: var(--kpi-color, #fff); }
.kpi .kpi-sub   { font-size: 0.72rem; color: #6b7280; margin-top: 0.4rem; display: flex; align-items: center; gap: 4px; }

/* ── Chart card ── */
.chart-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 1.25rem; margin-bottom: 1.5rem; }
.chart-card .card-title { font-size: 0.82rem; font-weight: 700; color: #d1d5db; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; }
.chart-wrap { position: relative; height: 220px; }

/* ── Insights row ── */
.insights-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.ins-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 1rem 1.2rem; }
.ins-card .ins-title { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; margin-bottom: 0.75rem; }

/* Device bar */
.dev-bar { display: flex; height: 10px; border-radius: 99px; overflow: hidden; margin: 0.5rem 0 0.75rem; }
.dev-seg { transition: width 0.4s; }
.dev-legend { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.dev-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 4px; }
.dev-item { font-size: 0.72rem; color: #9ca3af; display: flex; align-items: center; }

/* Heatmap */
.heatmap { display: grid; grid-template-columns: repeat(24, 1fr); gap: 2px; margin-top: 0.5rem; }
.hm-cell { height: 28px; border-radius: 4px; background: var(--bg); position: relative; }
.hm-cell:hover::after { content: attr(data-tip); position: absolute; bottom: 110%; left: 50%; transform: translateX(-50%);
    background: #1e1e28; border: 1px solid var(--border); color: #e5e7eb; font-size: 0.65rem;
    padding: 2px 6px; border-radius: 4px; white-space: nowrap; pointer-events: none; z-index: 10; }
.hm-hour { font-size: 0.58rem; color: #4b5563; text-align: center; margin-top: 2px; }

/* Referrers */
.ref-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0; border-bottom: 1px solid var(--border); }
.ref-item:last-child { border-bottom: none; }
.ref-bar-wrap { flex: 1; height: 6px; background: var(--bg); border-radius: 99px; overflow: hidden; }
.ref-bar      { height: 100%; border-radius: 99px; background: var(--accent); }
.ref-name     { font-size: 0.75rem; color: #d1d5db; width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex-shrink: 0; }
.ref-count    { font-size: 0.72rem; color: #6b7280; width: 36px; text-align: right; flex-shrink: 0; }

/* New vs returning */
.donut-wrap { display: flex; align-items: center; gap: 1.5rem; }
.donut-legend { display: flex; flex-direction: column; gap: 0.5rem; }
.donut-item { font-size: 0.75rem; color: #9ca3af; display: flex; align-items: center; gap: 6px; }
.donut-item strong { color: #e5e7eb; }

/* ── Main grid ── */
.main-grid { display: grid; grid-template-columns: 1fr 300px; gap: 1.25rem; align-items: start; }
@media (max-width: 960px) { .main-grid { grid-template-columns: 1fr; } }

.card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
.card-header { padding: 0.7rem 1.2rem; border-bottom: 1px solid var(--border); font-weight: 700; font-size: 0.82rem; color: #d1d5db; display: flex; justify-content: space-between; align-items: center; }

/* Filter bar */
.filter-bar { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 0.75rem 1.2rem; margin-bottom: 1.25rem; display: flex; gap: 0.6rem; flex-wrap: wrap; align-items: center; }
.filter-bar select, .filter-bar input { background: var(--bg); color: #e5e7eb; border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; padding: 0.3rem 0.65rem; font-size: 0.8rem; }
.filter-bar select:focus, .filter-bar input:focus { outline: none; border-color: var(--accent); }
.btn-filter { background: var(--accent); color: #fff; border: none; border-radius: 8px; padding: 0.3rem 0.85rem; font-size: 0.8rem; font-weight: 700; cursor: pointer; }
.btn-filter:hover { background: #6d28d9; }
.btn-export { background: transparent; color: #34d399; border: 1px solid rgba(52,211,153,0.3); border-radius: 8px; padding: 0.3rem 0.85rem; font-size: 0.8rem; font-weight: 700; cursor: pointer; }
.btn-export:hover { background: rgba(52,211,153,0.08); }

/* Log table */
.log-table { width: 100%; border-collapse: collapse; font-size: 0.76rem; }
.log-table th { padding: 0.55rem 0.75rem; text-align: left; color: #6b7280; font-weight: 600; border-bottom: 1px solid var(--border); white-space: nowrap; }
.log-table td { padding: 0.45rem 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: middle; }
.log-table tr:last-child td { border-bottom: none; }
.log-table tr:hover td { background: rgba(255,255,255,0.025); }
.badge-pv  { background:rgba(96,165,250,0.18); color:var(--blue); border-radius:6px; padding:0.12rem 0.45rem; font-size:0.68rem; font-weight:700; }
.badge-cl  { background:rgba(167,139,250,0.18); color:var(--accent-light); border-radius:6px; padding:0.12rem 0.45rem; font-size:0.68rem; font-weight:700; }
.badge-oth { background:rgba(156,163,175,0.18); color:#9ca3af; border-radius:6px; padding:0.12rem 0.45rem; font-size:0.68rem; font-weight:700; }
.user-tag  { font-weight: 600; color: #e5e7eb; cursor: pointer; }
.user-tag:hover { color: var(--accent-light); }
.guest-tag { color: #6b7280; font-style: italic; }
.url-cell  { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--accent-light); }
.action-cell { max-width: 190px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ip-cell   { color: #6b7280; white-space: nowrap; }
.time-cell { white-space: nowrap; color: #9ca3af; }
.sess-chip { font-size: 0.62rem; color: #6b7280; background: rgba(255,255,255,0.05); border-radius: 4px; padding: 0.08rem 0.35rem; cursor: pointer; border: 1px solid transparent; }
.sess-chip:hover { border-color: var(--accent); color: var(--accent-light); }
.pagination { display: flex; gap: 0.3rem; flex-wrap: wrap; align-items: center; padding: 0.75rem 1.2rem; border-top: 1px solid var(--border); }
.pg-btn { background: var(--bg); color: #e5e7eb; border: 1px solid var(--border); border-radius: 6px; padding: 0.22rem 0.55rem; font-size: 0.76rem; cursor: pointer; }
.pg-btn:hover { border-color: var(--accent); color: var(--accent-light); }
.pg-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.side-card { margin-bottom: 1.1rem; }
.top-item { padding: 0.4rem 1rem; border-bottom: 1px solid var(--border); font-size: 0.76rem; display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; }
.top-item:last-child { border-bottom: none; }
.top-item .count { background: rgba(139,92,246,0.15); color: var(--accent-light); border-radius: 99px; padding: 0.05rem 0.5rem; font-size: 0.68rem; font-weight: 700; white-space: nowrap; }
.top-item .page-path { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #d1d5db; flex: 1; }
.auto-refresh-wrap { display: flex; align-items: center; gap: 0.4rem; font-size: 0.76rem; color: #9ca3af; }
.auto-refresh-wrap input { width: 13px; height: 13px; accent-color: var(--accent); }

/* ── Live feed ── */
.live-bar { display: flex; align-items: center; gap: 0.6rem; padding: 0.6rem 1.2rem; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 1.25rem; font-size: 0.78rem; color: #9ca3af; }
.live-dot { width: 8px; height: 8px; border-radius: 50%; background: #6b7280; }
.live-dot.active { background: #34d399; animation: pulse 1.5s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }
.live-btn { background: transparent; border: 1px solid rgba(52,211,153,0.3); color: #34d399; border-radius: 8px; padding: 0.2rem 0.7rem; font-size: 0.76rem; cursor: pointer; font-weight: 700; }
.live-btn:hover { background: rgba(52,211,153,0.08); }
.live-btn.on { background: rgba(52,211,153,0.12); }
#liveRows tr.new-row { animation: fadeIn 0.4s ease; }
@keyframes fadeIn { from { background: rgba(52,211,153,0.08); } to { background: transparent; } }

/* ── Session modal ── */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center; }
.modal-overlay.open { display: flex; }
.modal-box { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; width: 90%; max-width: 750px; max-height: 85vh; overflow: hidden; display: flex; flex-direction: column; }
.modal-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { margin: 0; font-size: 0.9rem; font-weight: 800; }
.modal-close { background: none; border: none; color: #9ca3af; font-size: 1.2rem; cursor: pointer; padding: 0; line-height: 1; }
.modal-close:hover { color: #fff; }
.modal-body { overflow-y: auto; padding: 1rem 1.25rem; flex: 1; }
.timeline { position: relative; padding-left: 1.5rem; }
.timeline::before { content:''; position:absolute; left:6px; top:0; bottom:0; width:2px; background: var(--border); }
.tl-item { position: relative; margin-bottom: 1rem; }
.tl-dot { position: absolute; left: -1.5rem; top: 4px; width: 10px; height: 10px; border-radius: 50%; border: 2px solid var(--bg); }
.tl-time { font-size: 0.65rem; color: #6b7280; margin-bottom: 2px; }
.tl-page { font-size: 0.78rem; color: var(--accent-light); word-break: break-all; }
.tl-action { font-size: 0.72rem; color: #9ca3af; margin-top: 2px; }

/* ── Retargeting panel ── */
.retarget-panel { background: var(--surface); border: 1px solid rgba(251,191,36,0.2); border-radius: 14px; padding: 1.25rem; margin-top: 1.5rem; }
.retarget-panel h2 { font-size: 0.9rem; font-weight: 800; color: var(--yellow); margin: 0 0 0.25rem; }
.retarget-panel p { font-size: 0.78rem; color: #6b7280; margin: 0 0 1rem; }
.retarget-input-row { display: flex; gap: 0.6rem; flex-wrap: wrap; align-items: center; margin-bottom: 1rem; }
.retarget-input { flex: 1; min-width: 250px; background: var(--bg); color: #e5e7eb; border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; padding: 0.4rem 0.75rem; font-size: 0.82rem; }
.retarget-input:focus { outline: none; border-color: var(--yellow); }
.retarget-dr { background: var(--bg); color: #e5e7eb; border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; padding: 0.4rem 0.65rem; font-size: 0.8rem; }
.btn-retarget { background: rgba(251,191,36,0.1); color: var(--yellow); border: 1px solid rgba(251,191,36,0.3); border-radius: 8px; padding: 0.4rem 1rem; font-size: 0.82rem; font-weight: 700; cursor: pointer; }
.btn-retarget:hover { background: rgba(251,191,36,0.18); }
.retarget-results { margin-top: 0.75rem; }
.retarget-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.retarget-table th { padding: 0.5rem 0.75rem; text-align: left; color: #6b7280; font-weight: 600; border-bottom: 1px solid var(--border); }
.retarget-table td { padding: 0.4rem 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.04); }
.retarget-table tr:hover td { background: rgba(255,255,255,0.025); }
.btn-export-rt { background: transparent; color: #34d399; border: 1px solid rgba(52,211,153,0.3); border-radius: 6px; padding: 0.2rem 0.65rem; font-size: 0.72rem; font-weight: 700; cursor: pointer; margin-left: 0.5rem; }
.btn-export-rt:hover { background: rgba(52,211,153,0.08); }
.rt-stat { display: inline-flex; align-items: center; gap: 0.4rem; background: rgba(255,255,255,0.05); border-radius: 8px; padding: 0.3rem 0.75rem; font-size: 0.78rem; margin-right: 0.5rem; margin-bottom: 0.75rem; }
.rt-stat strong { color: #fff; }

/* ── OCPD Bookings Panel ── */
.bookings-panel { margin-bottom:1.5rem; background:var(--surface); border:1px solid var(--border); border-radius:14px; overflow:hidden; }
.bookings-panel-head { display:flex; align-items:center; gap:0.75rem; padding:0.85rem 1.2rem; border-bottom:1px solid var(--border); }
.bookings-panel-head h3 { font-size:0.9rem; font-weight:700; color:var(--text); margin:0; }
.bk-tab-bar { display:flex; gap:0.3rem; padding:0.6rem 1rem; border-bottom:1px solid var(--border); background:rgba(0,0,0,0.15); flex-wrap:wrap; align-items:center; }
.bk-tab { background:transparent; border:1px solid var(--border); color:var(--muted); border-radius:7px; padding:0.28rem 0.8rem; font-size:0.75rem; cursor:pointer; font-weight:600; display:inline-flex; align-items:center; gap:0.35rem; }
.bk-tab:hover { background:rgba(255,255,255,0.05); color:var(--text); }
.bk-tab.active { background:rgba(56,189,248,0.12); border-color:rgba(56,189,248,0.35); color:#38bdf8; }
.bk-tab .bk-cnt { background:rgba(255,255,255,0.1); border-radius:99px; padding:0 0.38rem; font-size:0.65rem; }
.bk-tab.active .bk-cnt { background:rgba(56,189,248,0.2); }
.bk-search { margin-left:auto; background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:7px; padding:0.28rem 0.65rem; font-size:0.75rem; width:180px; }
.bk-search:focus { outline:none; border-color:rgba(56,189,248,0.4); }
.bk-list { padding:0.6rem 0.75rem; display:flex; flex-direction:column; gap:0.5rem; min-height:60px; }
.bk-card { background:var(--bg); border:1px solid var(--border); border-radius:10px; padding:0.75rem 0.9rem; }
.bk-card.pending  { border-left:3px solid rgba(251,191,36,0.5); }
.bk-card.confirmed{ border-left:3px solid rgba(52,211,153,0.5); }
.bk-card.cancelled{ border-left:3px solid rgba(248,113,113,0.5); opacity:0.7; }
.bk-card-top { display:flex; align-items:flex-start; gap:0.75rem; margin-bottom:0.45rem; }
.bk-ref { font-size:0.68rem; font-weight:700; color:#38bdf8; font-family:monospace; background:rgba(56,189,248,0.08); padding:0.15rem 0.4rem; border-radius:4px; flex-shrink:0; }
.bk-name { font-weight:700; color:var(--text); font-size:0.85rem; }
.bk-email { font-size:0.72rem; color:var(--muted); }
.bk-status-badge { margin-left:auto; font-size:0.65rem; font-weight:700; padding:0.15rem 0.5rem; border-radius:99px; flex-shrink:0; }
.bk-status-badge.pending   { background:rgba(251,191,36,0.12); color:#fbbf24; border:1px solid rgba(251,191,36,0.3); }
.bk-status-badge.confirmed { background:rgba(52,211,153,0.12); color:#34d399; border:1px solid rgba(52,211,153,0.3); }
.bk-status-badge.cancelled { background:rgba(248,113,113,0.1); color:#f87171; border:1px solid rgba(248,113,113,0.25); }
.bk-meta { display:flex; flex-wrap:wrap; gap:0.5rem 1.1rem; font-size:0.75rem; color:var(--muted); margin-bottom:0.45rem; }
.bk-meta strong { color:#9ca3af; }
.bk-packages { font-size:0.72rem; color:#a78bfa; margin-bottom:0.5rem; }
.bk-notes-area { width:100%; background:var(--surface); border:1px solid var(--border); border-radius:6px; color:var(--text); padding:0.4rem 0.6rem; font-size:0.75rem; resize:vertical; min-height:42px; font-family:inherit; margin-bottom:0.45rem; }
.bk-notes-area:focus { outline:none; border-color:rgba(56,189,248,0.4); }
.bk-actions { display:flex; gap:0.4rem; align-items:center; flex-wrap:wrap; }
.bk-btn { border:none; border-radius:7px; padding:0.3rem 0.75rem; font-size:0.75rem; font-weight:700; cursor:pointer; }
.bk-btn-confirm  { background:rgba(52,211,153,0.15); color:#34d399; border:1px solid rgba(52,211,153,0.3); }
.bk-btn-confirm:hover  { background:rgba(52,211,153,0.25); }
.bk-btn-cancel   { background:rgba(248,113,113,0.1); color:#f87171; border:1px solid rgba(248,113,113,0.25); }
.bk-btn-cancel:hover   { background:rgba(248,113,113,0.18); }
.bk-btn-save     { background:rgba(56,189,248,0.1); color:#38bdf8; border:1px solid rgba(56,189,248,0.25); }
.bk-btn-save:hover     { background:rgba(56,189,248,0.18); }
.bk-msg { font-size:0.72rem; margin-left:0.3rem; }
.bk-msg.ok  { color:#34d399; }
.bk-msg.err { color:#f87171; }
.bk-empty { color:var(--muted); font-size:0.8rem; padding:0.5rem; text-align:center; }

/* ── Alrisha ERP Panel ── */
.erp-panel { margin-bottom:1.5rem; }
.erp-status { display:inline-flex; align-items:center; gap:0.4rem; font-size:0.7rem; color:#6b7280; }
.erp-status.ok { color:#34d399; }
.erp-status.err { color:#f87171; }
.erp-kpi-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(155px,1fr)); gap:0.6rem; margin-bottom:1.2rem; }
.erp-kpi { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:0.8rem 0.9rem; }
.erp-kpi .lbl { font-size:0.67rem; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.3rem; }
.erp-kpi .val { font-size:1.15rem; font-weight:800; color:var(--text); }
.erp-kpi .sub { font-size:0.67rem; color:#6b7280; margin-top:0.15rem; }
.erp-kpi.warn .val { color:#fbbf24; }
.erp-kpi.danger .val { color:#f87171; }
.erp-kpi.good .val { color:#34d399; }
.erp-section-title { font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.07em; color:#6b7280; margin:0.9rem 0 0.5rem; }
.erp-table { width:100%; border-collapse:collapse; font-size:0.75rem; }
.erp-table th { color:#6b7280; font-size:0.67rem; text-transform:uppercase; letter-spacing:0.05em; padding:0.3rem 0.5rem; border-bottom:1px solid var(--border); text-align:left; }
.erp-table td { padding:0.35rem 0.5rem; border-bottom:1px solid rgba(255,255,255,0.04); color:#d1d5db; }
.erp-table tr:last-child td { border-bottom:none; }
.erp-badge { display:inline-block; font-size:0.62rem; font-weight:700; padding:0.1rem 0.45rem; border-radius:99px; }
.erp-badge.draft    { background:rgba(107,114,128,0.15); color:#9ca3af; }
.erp-badge.pending  { background:rgba(251,191,36,0.12); color:#fbbf24; }
.erp-badge.approved { background:rgba(52,211,153,0.12); color:#34d399; }
.erp-badge.open     { background:rgba(56,189,248,0.12); color:#38bdf8; }
.erp-badge.paid     { background:rgba(52,211,153,0.12); color:#34d399; }
.erp-badge.cancelled{ background:rgba(248,113,113,0.1); color:#f87171; }
.erp-badge.issued   { background:rgba(167,139,250,0.12); color:#a78bfa; }
.erp-revenue-bar { display:flex; gap:0.3rem; align-items:flex-end; height:50px; margin-bottom:0.4rem; }
.erp-revenue-bar .bar-col { flex:1; display:flex; flex-direction:column; align-items:center; gap:2px; }
.erp-revenue-bar .bar { width:100%; background:rgba(52,211,153,0.3); border-radius:3px 3px 0 0; min-height:3px; }
.erp-revenue-bar .bar-lbl { font-size:0.55rem; color:#6b7280; }
.erp-two-col { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
@media(max-width:640px) { .erp-two-col { grid-template-columns:1fr; } .erp-kpi-grid { grid-template-columns:1fr 1fr; } }

/* ── OMNISCIENT additions ── */
.live-now-badge { display: inline-flex; align-items: center; gap: 0.4rem; background: rgba(52,211,153,0.1); border: 1px solid rgba(52,211,153,0.25); border-radius: 20px; padding: 0.2rem 0.75rem; font-size: 0.76rem; color: #34d399; font-weight: 700; }
.live-dot-sm { width: 7px; height: 7px; border-radius: 50%; background: #34d399; animation: pulse 1.4s infinite; flex-shrink: 0; }
.scroll-bar-wrap { flex: 1; height: 5px; background: var(--bg); border-radius: 99px; overflow: hidden; }
.scroll-bar { height: 100%; border-radius: 99px; }
.country-flag { font-size: 1.1em; margin-right: 4px; }
.omni-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.badge-scroll { background:rgba(52,211,153,0.15); color:#34d399; border-radius:6px; padding:0.1rem 0.4rem; font-size:0.68rem; font-weight:700; }
.badge-time   { background:rgba(251,191,36,0.15); color:#fbbf24; border-radius:6px; padding:0.1rem 0.4rem; font-size:0.68rem; font-weight:700; }
.badge-error  { background:rgba(248,113,113,0.15); color:#f87171; border-radius:6px; padding:0.1rem 0.4rem; font-size:0.68rem; font-weight:700; }
.depth-pill { display:inline-block; padding:0.1rem 0.45rem; border-radius:99px; font-size:0.65rem; font-weight:800; }
.err-row { padding: 0.4rem 1rem; border-bottom: 1px solid var(--border); font-size: 0.73rem; }
.err-row:last-child { border-bottom: none; }
.err-msg { color: #f87171; font-family: monospace; word-break: break-all; }

/* ── PALANTIR panels ── */
.palantir-section { margin-bottom: 1rem; }
.palantir-header { display:flex; align-items:center; gap:0.65rem; padding:0.85rem 1.3rem;
    background:var(--surface); border:1px solid var(--border); border-radius:14px 14px 0 0;
    cursor:pointer; user-select:none; font-size:0.85rem; font-weight:800; color:#d1d5db; }
.palantir-header:hover { border-color:rgba(167,139,250,0.35); }
.palantir-header .pal-icon { color:#a78bfa; font-size:0.95rem; }
.palantir-header .pal-badge { font-size:0.68rem; font-weight:700; background:rgba(167,139,250,0.15); color:#a78bfa; border-radius:99px; padding:0.1rem 0.55rem; }
.palantir-header .pal-toggle { margin-left:auto; color:#6b7280; font-size:0.8rem; transition:transform 0.2s; }
.palantir-header.collapsed .pal-toggle { transform:rotate(-90deg); }
.palantir-body { background:var(--surface); border:1px solid var(--border); border-top:none; border-radius:0 0 14px 14px; padding:1.25rem; }
.palantir-body.hidden { display:none; }
.ident-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:0.75rem; margin-bottom:1.1rem; }
.ident-stat { background:var(--surface2); border-radius:10px; padding:0.75rem 1rem; text-align:center; }
.ident-stat .val { font-size:1.5rem; font-weight:900; color:#a78bfa; line-height:1; }
.ident-stat .lbl { font-size:0.65rem; font-weight:700; text-transform:uppercase; color:#6b7280; margin-top:4px; }
.funnel-step-row { display:flex; align-items:center; gap:0.75rem; padding:0.5rem 0.65rem; background:var(--surface2); border-radius:7px; margin-bottom:2px; }
.funnel-bar-wrap { flex:1; height:7px; background:var(--bg); border-radius:99px; overflow:hidden; }
.funnel-bar { height:100%; border-radius:99px; background:#a78bfa; transition:width 0.5s; }
.funnel-drop { text-align:center; font-size:0.68rem; color:#f87171; padding:2px 0; }
.seg-filter-bar { display:flex; gap:0.6rem; flex-wrap:wrap; align-items:center; margin-bottom:1rem; padding:0.7rem 1rem; background:var(--surface2); border-radius:10px; }
.seg-filter-bar select, .seg-filter-bar input[type=text], .seg-filter-bar input[type=number] { background:var(--bg); color:#e5e7eb; border:1px solid rgba(255,255,255,0.12); border-radius:8px; padding:0.28rem 0.65rem; font-size:0.79rem; }
.alert-rule-row { display:flex; align-items:center; gap:0.65rem; padding:0.5rem 0; border-bottom:1px solid var(--border); font-size:0.77rem; }
.alert-rule-row:last-child { border-bottom:none; }
.alert-type-badge { font-size:0.64rem; font-weight:700; padding:0.1rem 0.5rem; border-radius:6px; white-space:nowrap; }
.alert-site-badge { font-size:0.64rem; padding:0.1rem 0.45rem; border-radius:6px; background:rgba(255,255,255,0.07); color:#9ca3af; }
.alert-active-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }

/* ── Omniscient Dashboard Layout ── */
.omni-layout { display:flex; min-height:100vh; }
.omni-sidebar {
    width:220px; min-width:220px; flex-shrink:0;
    background:var(--surface); border-right:1px solid var(--border);
    display:flex; flex-direction:column;
    position:sticky; top:0; height:100vh; overflow-y:auto; z-index:100;
}
.omni-main { flex:1; min-width:0; overflow-x:hidden; }
.sidebar-logo { padding:1.3rem 1.1rem 1rem; border-bottom:1px solid var(--border); }
.sidebar-logo-mark { font-size:1rem; color:#a78bfa; margin-bottom:4px; }
.sidebar-logo-title { font-size:0.78rem; font-weight:900; letter-spacing:0.12em; text-transform:uppercase; color:#e5e7eb; }
.sidebar-logo-sub { font-size:0.58rem; color:#4b5563; letter-spacing:0.07em; text-transform:uppercase; margin-top:2px; }
.sidebar-nav { flex:1; padding:0.65rem 0; }
.sidebar-section-label { font-size:0.58rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:#4b5563; padding:0.5rem 1.1rem 0.3rem; }
.sidebar-biz {
    display:block; padding:0.65rem 0.75rem; margin:0.15rem 0.5rem;
    border-radius:10px; text-decoration:none;
    border:1px solid transparent; transition:background 0.15s, border-color 0.15s;
}
.sidebar-biz:hover { background:rgba(255,255,255,0.04); }
.sidebar-biz-head { display:flex; align-items:center; gap:0.5rem; margin-bottom:0.35rem; }
.sidebar-biz-icon { width:26px; height:26px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.72rem; font-weight:900; flex-shrink:0; }
.sidebar-biz-name { font-size:0.8rem; font-weight:700; color:#e5e7eb; }
.sidebar-biz-domain { font-size:0.6rem; color:#4b5563; margin-bottom:0.3rem; padding-left:34px; }
.sidebar-biz-stats { display:flex; gap:0.5rem; align-items:center; padding-left:34px; }
.sidebar-biz-count { font-size:0.67rem; color:#6b7280; }
.sidebar-live-pip { display:inline-flex; align-items:center; gap:3px; }
.sidebar-live-pip .dot { width:5px; height:5px; border-radius:50%; background:#34d399; animation:pulse 1.4s infinite; }
.sidebar-live-pip span { font-size:0.65rem; color:#34d399; font-weight:700; }
.sidebar-footer { padding:0.85rem 1.1rem; border-top:1px solid var(--border); }
.sidebar-footer a { font-size:0.74rem; color:#6b7280; text-decoration:none; display:flex; align-items:center; gap:0.4rem; }
.sidebar-footer a:hover { color:#e5e7eb; }
@media (max-width:768px) {
    .omni-sidebar { width:56px; min-width:56px; }
    .sidebar-logo-title, .sidebar-logo-sub, .sidebar-biz-name, .sidebar-biz-domain, .sidebar-biz-stats, .sidebar-section-label, .sidebar-footer a span { display:none; }
    .sidebar-biz { padding:0.65rem; margin:0.15rem 0.25rem; justify-content:center; }
    .sidebar-biz-head { justify-content:center; margin-bottom:0; }
    .sidebar-logo { padding:0.9rem 0; display:flex; justify-content:center; }
}
</style>
