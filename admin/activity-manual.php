<?php
require_once __DIR__ . '/../includes/db.php';
if (!isset($_SESSION['admin_username'])) {
    header('Location: ' . base_url('admin/')); exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Omniscient Manual — Argonar</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
<style>
:root {
    --bg:     #0a0a0f;
    --surface:#111118;
    --border: rgba(255,255,255,0.07);
    --text:   #e5e7eb;
    --muted:  #6b7280;
    --purple: #a78bfa;
    --cyan:   #38bdf8;
    --green:  #34d399;
    --yellow: #fbbf24;
    --red:    #f87171;
}
* { box-sizing:border-box; margin:0; padding:0; }
body { background:var(--bg); color:var(--text); font-family:'Inter',sans-serif; font-size:0.875rem; line-height:1.6; }

/* ── Layout ── */
.omni-layout { display:flex; min-height:100vh; }
.omni-sidebar {
    width:220px; min-width:220px; flex-shrink:0;
    background:var(--surface); border-right:1px solid var(--border);
    display:flex; flex-direction:column;
    position:sticky; top:0; height:100vh; overflow-y:auto; z-index:100;
}
.omni-main { flex:1; min-width:0; max-width:860px; padding:2.5rem 2rem 4rem; }

/* ── Sidebar ── */
.sidebar-logo { padding:1.3rem 1.1rem 1rem; border-bottom:1px solid var(--border); }
.sidebar-logo-mark { font-size:1rem; color:var(--purple); margin-bottom:4px; }
.sidebar-logo-title { font-weight:700; font-size:0.85rem; color:var(--text); }
.sidebar-logo-sub { font-size:0.68rem; color:var(--muted); }
.sidebar-nav { flex:1; padding:0.75rem 0; }
.sidebar-section-label { font-size:0.62rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.08em; padding:0.6rem 1.1rem 0.25rem; }
.sidebar-link {
    display:flex; align-items:center; gap:0.6rem;
    padding:0.5rem 1.1rem; color:var(--muted); font-size:0.78rem;
    text-decoration:none; border-radius:6px; margin:0.1rem 0.4rem;
    transition:background 0.15s, color 0.15s;
}
.sidebar-link:hover { background:rgba(255,255,255,0.05); color:var(--text); }
.sidebar-link.active { background:rgba(167,139,250,0.1); color:var(--purple); border:1px solid rgba(167,139,250,0.25); }
.sidebar-link i { font-size:0.85rem; }
.sidebar-footer { padding:0.75rem 0.85rem; border-top:1px solid var(--border); margin-top:auto; }
.sidebar-footer a { color:var(--muted); font-size:0.75rem; text-decoration:none; display:flex; align-items:center; gap:0.4rem; }
.sidebar-footer a:hover { color:var(--text); }

/* ── Manual content ── */
.manual-hero { margin-bottom:2.5rem; }
.manual-hero h1 { font-size:1.6rem; font-weight:800; color:var(--purple); margin-bottom:0.35rem; }
.manual-hero p { color:var(--muted); font-size:0.85rem; max-width:560px; }
.manual-toc {
    background:var(--surface); border:1px solid var(--border);
    border-radius:10px; padding:1.1rem 1.4rem; margin-bottom:2.5rem;
}
.manual-toc h3 { font-size:0.72rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.08em; margin-bottom:0.65rem; }
.manual-toc ol { padding-left:1.2rem; }
.manual-toc li { margin-bottom:0.25rem; }
.manual-toc a { color:var(--purple); text-decoration:none; font-size:0.82rem; }
.manual-toc a:hover { text-decoration:underline; }

.section { margin-bottom:3rem; scroll-margin-top:1.5rem; }
.section-header {
    display:flex; align-items:center; gap:0.65rem;
    margin-bottom:1.1rem; padding-bottom:0.65rem;
    border-bottom:1px solid var(--border);
}
.section-icon {
    width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center;
    font-size:0.9rem; flex-shrink:0;
}
.section-header h2 { font-size:1.05rem; font-weight:700; color:var(--text); }
.section-header .badge-new {
    font-size:0.6rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em;
    background:rgba(167,139,250,0.15); color:var(--purple); border:1px solid rgba(167,139,250,0.3);
    padding:0.1rem 0.45rem; border-radius:99px;
}
.prose p { color:#d1d5db; margin-bottom:0.9rem; font-size:0.84rem; }
.prose ul, .prose ol { padding-left:1.4rem; margin-bottom:0.9rem; }
.prose li { color:#d1d5db; font-size:0.84rem; margin-bottom:0.3rem; }
.prose strong { color:var(--text); font-weight:600; }
.prose code {
    background:rgba(167,139,250,0.1); color:var(--purple);
    padding:0.1rem 0.4rem; border-radius:4px; font-size:0.78rem; font-family:monospace;
}
.info-box {
    background:var(--surface); border:1px solid var(--border);
    border-left:3px solid; border-radius:8px; padding:0.9rem 1.1rem; margin-bottom:1rem;
}
.info-box.purple  { border-left-color:var(--purple); }
.info-box.cyan    { border-left-color:var(--cyan); }
.info-box.green   { border-left-color:var(--green); }
.info-box.yellow  { border-left-color:var(--yellow); }
.info-box .ib-title { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.35rem; }
.info-box.purple .ib-title { color:var(--purple); }
.info-box.cyan    .ib-title { color:var(--cyan); }
.info-box.green   .ib-title { color:var(--green); }
.info-box.yellow  .ib-title { color:var(--yellow); }
.info-box p { color:#9ca3af; font-size:0.81rem; margin:0; }

.feature-grid { display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:1rem; }
.feature-card {
    background:var(--surface); border:1px solid var(--border);
    border-radius:8px; padding:0.85rem 1rem;
}
.feature-card .fc-icon { font-size:1.1rem; margin-bottom:0.4rem; }
.feature-card .fc-title { font-size:0.8rem; font-weight:600; color:var(--text); margin-bottom:0.25rem; }
.feature-card .fc-desc { font-size:0.75rem; color:var(--muted); }

.step-list { counter-reset:steps; }
.step-item {
    display:flex; gap:0.85rem; margin-bottom:0.9rem; align-items:flex-start;
}
.step-num {
    counter-increment:steps; width:24px; height:24px; border-radius:50%;
    background:rgba(167,139,250,0.15); border:1px solid rgba(167,139,250,0.3);
    color:var(--purple); font-size:0.7rem; font-weight:700;
    display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:1px;
}
.step-num::before { content:counter(steps); }
.step-body .step-title { font-size:0.82rem; font-weight:600; color:var(--text); margin-bottom:0.2rem; }
.step-body .step-desc { font-size:0.79rem; color:var(--muted); }

@media (max-width:768px) {
    .omni-sidebar { display:none; }
    .omni-main { padding:1.5rem 1rem 3rem; }
    .feature-grid { grid-template-columns:1fr; }
}
</style>
</head>
<body>
<div class="omni-layout">

<!-- ══ SIDEBAR ══ -->
<aside class="omni-sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-mark">◈</div>
        <div class="sidebar-logo-title">Omniscient</div>
        <div class="sidebar-logo-sub">Manual</div>
    </div>
    <nav class="sidebar-nav">
        <div class="sidebar-section-label">Contents</div>
        <a href="#overview"   class="sidebar-link"><i class="bi bi-book"></i> Overview</a>
        <a href="#businesses" class="sidebar-link"><i class="bi bi-diagram-3"></i> Businesses</a>
        <a href="#metrics"    class="sidebar-link"><i class="bi bi-bar-chart"></i> Metrics</a>
        <a href="#event-log"  class="sidebar-link"><i class="bi bi-list-ul"></i> Event Log</a>
        <a href="#sessions"   class="sidebar-link"><i class="bi bi-person-video2"></i> Sessions</a>
        <a href="#identity"   class="sidebar-link active"><i class="bi bi-fingerprint"></i> Identity Graph</a>
        <a href="#funnels"    class="sidebar-link"><i class="bi bi-filter"></i> Funnel Analyzer</a>
        <a href="#segments"   class="sidebar-link"><i class="bi bi-people"></i> Segment Explorer</a>
        <a href="#alerts"     class="sidebar-link"><i class="bi bi-bell"></i> Alert Engine</a>
        <a href="#retarget"   class="sidebar-link"><i class="bi bi-cursor"></i> Retargeting</a>
        <a href="#export"     class="sidebar-link"><i class="bi bi-download"></i> Export</a>
    </nav>
    <div class="sidebar-footer">
        <a href="<?= base_url('admin/activity.php') ?>"><i class="bi bi-arrow-left"></i> <span>Back to Dashboard</span></a>
    </div>
</aside>

<!-- ══ MAIN ══ -->
<div class="omni-main">

<div class="manual-hero">
    <h1>◈ Omniscient Manual</h1>
    <p>Complete reference for the Omniscient Analytics dashboard — what every panel does, how the data is collected, and how to act on it.</p>
</div>

<div class="manual-toc">
    <h3>Table of Contents</h3>
    <ol>
        <li><a href="#overview">Overview — what is Omniscient?</a></li>
        <li><a href="#businesses">Businesses &amp; the sidebar</a></li>
        <li><a href="#metrics">Metrics strip (KPIs)</a></li>
        <li><a href="#event-log">Event log &amp; search</a></li>
        <li><a href="#sessions">Session drawer</a></li>
        <li><a href="#identity">Identity Graph ← start here</a></li>
        <li><a href="#funnels">Funnel Analyzer</a></li>
        <li><a href="#segments">Segment Explorer</a></li>
        <li><a href="#alerts">Alert Engine</a></li>
        <li><a href="#retarget">Retargeting panel</a></li>
        <li><a href="#export">CSV export</a></li>
    </ol>
</div>

<!-- ══ 1. Overview ══ -->
<div class="section" id="overview">
    <div class="section-header">
        <div class="section-icon" style="background:rgba(167,139,250,0.12); color:#a78bfa;"><i class="bi bi-book"></i></div>
        <h2>Overview</h2>
    </div>
    <div class="prose">
        <p>Omniscient is a first-party analytics system built into Argonar. It tracks every visitor interaction — page views, clicks, scroll depth, time on page, JS errors, and load times — across all three businesses from a single dashboard without relying on Google Analytics or any third-party tool.</p>
        <p>All data is stored in <code>activity_logs</code> on the Argonar server. The tracker runs as a tiny JavaScript snippet injected into each site's footer and batches events to <code>/api/track.php</code> via <code>navigator.sendBeacon</code> so it never blocks page loads.</p>
    </div>
    <div class="feature-grid">
        <div class="feature-card">
            <div class="fc-icon">⚡</div>
            <div class="fc-title">Real-time</div>
            <div class="fc-desc">Live feed polls every 5 s. See events the moment they happen.</div>
        </div>
        <div class="feature-card">
            <div class="fc-icon">🔒</div>
            <div class="fc-title">First-party</div>
            <div class="fc-desc">No third-party cookies, no GDPR exposure. Your data stays on your server.</div>
        </div>
        <div class="feature-card">
            <div class="fc-icon">🌐</div>
            <div class="fc-title">Cross-site</div>
            <div class="fc-desc">Argonar, OCPD, and Loan Management all beam into one database.</div>
        </div>
        <div class="feature-card">
            <div class="fc-icon">🧩</div>
            <div class="fc-title">Identity stitching</div>
            <div class="fc-desc">Links anonymous sessions to real user accounts when they log in.</div>
        </div>
    </div>
</div>

<!-- ══ 2. Businesses ══ -->
<div class="section" id="businesses">
    <div class="section-header">
        <div class="section-icon" style="background:rgba(56,189,248,0.12); color:#38bdf8;"><i class="bi bi-diagram-3"></i></div>
        <h2>Businesses &amp; the sidebar</h2>
    </div>
    <div class="prose">
        <p>The left sidebar lists the three tracked businesses. Click any of them to switch the entire dashboard — all metrics, charts, and Palantir panels — to that site's data.</p>
        <ul>
            <li><strong style="color:#a78bfa;">A — Argonar</strong> · argonar.co — tournament platform</li>
            <li><strong style="color:#38bdf8;">O — OCPD</strong> · oslobcebuparagliding.com — paragliding club</li>
            <li><strong style="color:#c4b5fd;">L — Loan</strong> · argonarsoftware.com — loan management SaaS</li>
        </ul>
        <p>The session count and live-now pip shown per business update on each page load. The live count is visitors active in the last 5 minutes.</p>
    </div>
</div>

<!-- ══ 3. Metrics ══ -->
<div class="section" id="metrics">
    <div class="section-header">
        <div class="section-icon" style="background:rgba(52,211,153,0.12); color:#34d399;"><i class="bi bi-bar-chart"></i></div>
        <h2>Metrics strip (KPIs)</h2>
    </div>
    <div class="prose">
        <p>The row of cards at the top of the main area shows key performance indicators for the selected date range (Today / Last 7 days / Last 30 days / All time).</p>
        <ul>
            <li><strong>Pageviews</strong> — total <code>pageview</code> events in the period.</li>
            <li><strong>Sessions</strong> — unique <code>session_id</code> values. One session = one browser visit.</li>
            <li><strong>Users</strong> — sessions with a logged-in <code>account_id</code>.</li>
            <li><strong>Clicks</strong> — total <code>click</code> events recorded.</li>
            <li><strong>Avg scroll</strong> — mean scroll depth percentage across all pageviews.</li>
            <li><strong>Avg load</strong> — mean page load time in milliseconds.</li>
            <li><strong>Errors</strong> — total <code>error</code> events (uncaught JS exceptions).</li>
            <li><strong>Countries</strong> — distinct visitor countries detected via IP geolocation.</li>
        </ul>
    </div>
</div>

<!-- ══ 4. Event log ══ -->
<div class="section" id="event-log">
    <div class="section-header">
        <div class="section-icon" style="background:rgba(251,191,36,0.12); color:#fbbf24;"><i class="bi bi-list-ul"></i></div>
        <h2>Event log &amp; search</h2>
    </div>
    <div class="prose">
        <p>The main table shows every raw event recorded. Columns are:</p>
        <ul>
            <li><strong>Time</strong> — timestamp + date, plus a clickable SID chip that opens the session drawer.</li>
            <li><strong>Type</strong> — badge: <code>PV</code> (pageview), <code>CLK</code> (click), or the event name.</li>
            <li><strong>User</strong> — purple tag for logged-in accounts, grey "Guest" for anonymous.</li>
            <li><strong>URL</strong> — path + query string of the page.</li>
            <li><strong>Action</strong> — for clicks: the button/link text. For pageviews: the page title.</li>
            <li><strong>IP</strong> — visitor IP address.</li>
        </ul>
        <p>Use the <strong>search bar</strong> to filter by URL, user name, element text, or IP. Use the event-type dropdown to show only <code>pageview</code>, <code>click</code>, <code>error</code>, etc. Use the user filter chip to isolate a single account.</p>
    </div>
    <div class="info-box yellow">
        <div class="ib-title">Tip — filter by user</div>
        <p>Clicking a purple user tag anywhere in the table automatically sets the user filter to that account so you can see their full journey.</p>
    </div>
</div>

<!-- ══ 5. Sessions ══ -->
<div class="section" id="sessions">
    <div class="section-header">
        <div class="section-icon" style="background:rgba(56,189,248,0.12); color:#38bdf8;"><i class="bi bi-person-video2"></i></div>
        <h2>Session drawer</h2>
    </div>
    <div class="prose">
        <p>Click any <strong>SID chip</strong> (e.g. <code>SID a3f9b1…</code>) to open a side drawer showing the full replay of that session in chronological order: every page visited, every button clicked, scroll depth reached, and time spent on each page. Useful for understanding exactly what a specific visitor did.</p>
    </div>
</div>

<!-- ══ 6. Identity Graph ══ -->
<div class="section" id="identity">
    <div class="section-header">
        <div class="section-icon" style="background:rgba(167,139,250,0.12); color:#a78bfa;"><i class="bi bi-fingerprint"></i></div>
        <h2>Identity Graph</h2>
        <span class="badge-new">Palantir</span>
    </div>
    <div class="prose">
        <p>The Identity Graph is the most powerful feature in Omniscient. Its job is to answer one question: <strong>"Who is this visitor, really?"</strong></p>
        <p>Every time someone visits your site they get a random <code>session_id</code> stored in <code>localStorage</code>. After a browser restart, a different device, or clearing cookies — they get a new session ID. Without identity stitching, these look like completely separate visitors.</p>
        <p>The Identity Graph solves this by building a <code>user_graph</code> record per session and then linking records together when they share:</p>
        <ul>
            <li>The same <strong>logged-in account</strong> (<code>account_id</code>) — strongest signal.</li>
            <li>The same <strong>IP address</strong> — links household/office devices.</li>
            <li>The same <strong>UTM campaign</strong> — groups users from the same ad.</li>
        </ul>
    </div>
    <div class="info-box purple">
        <div class="ib-title">Real-world example</div>
        <p>A visitor arrives from a Facebook ad (UTM source = facebook) and browses 3 pages as a guest. Three days later they return directly and register. The Identity Graph backfills the UTM source onto their account record — so you know Facebook drove the conversion, not the direct visit.</p>
    </div>
    <div class="prose">
        <p>The Identity Graph panel in the dashboard shows:</p>
        <ul>
            <li><strong>Total identities</strong> — distinct sessions tracked in <code>user_graph</code>.</li>
            <li><strong>Identified users</strong> — sessions where we know the logged-in account.</li>
            <li><strong>Anonymous</strong> — guest sessions with no account match yet.</li>
            <li><strong>Avg pages / identity</strong> — engagement depth per visitor.</li>
            <li><strong>Top UTM sources</strong> — which acquisition channels drive the most identities.</li>
            <li><strong>Top countries</strong> — geographic breakdown of known identities.</li>
            <li><strong>Recent identities</strong> — latest 10 session records with their stitched data.</li>
        </ul>
    </div>
    <div class="info-box green">
        <div class="ib-title">Why it matters</div>
        <p>Standard analytics count sessions; the Identity Graph counts people. A visitor with 12 sessions is one loyal user, not 12 new visitors. This gives you true retention, true acquisition attribution, and a retargeting list of real humans.</p>
    </div>
</div>

<!-- ══ 7. Funnels ══ -->
<div class="section" id="funnels">
    <div class="section-header">
        <div class="section-icon" style="background:rgba(52,211,153,0.12); color:#34d399;"><i class="bi bi-filter"></i></div>
        <h2>Funnel Analyzer</h2>
        <span class="badge-new">Palantir</span>
    </div>
    <div class="prose">
        <p>A funnel is an ordered sequence of pages a visitor must hit — in order — within the same session. The Funnel Analyzer tells you how many sessions entered each step and where they dropped off.</p>
        <p>Pre-configured funnels:</p>
        <ul>
            <li><strong>Loan conversion</strong> — Landing → Register → Dashboard (loan site)</li>
            <li><strong>Argonar registration</strong> — Home → Register → Success (argonar)</li>
            <li><strong>OCPD booking</strong> — Home → Book → Confirm (ocpd)</li>
        </ul>
        <p>Each step shows an absolute count and a % drop-off from the previous step. A 90% drop between step 1 and step 2 means almost nobody scrolls past the landing page — that page needs work.</p>
    </div>
    <div class="info-box cyan">
        <div class="ib-title">How steps are counted</div>
        <p>A step counts if any <code>pageview</code> event in the session has a URL that contains the step's path string (partial match). Steps must appear in order within the session but do not need to be consecutive pages.</p>
    </div>
</div>

<!-- ══ 8. Segments ══ -->
<div class="section" id="segments">
    <div class="section-header">
        <div class="section-icon" style="background:rgba(251,191,36,0.12); color:#fbbf24;"><i class="bi bi-people"></i></div>
        <h2>Segment Explorer</h2>
        <span class="badge-new">Palantir</span>
    </div>
    <div class="prose">
        <p>Segments let you slice your audience and see metrics for a sub-group. Filter by any combination of:</p>
        <ul>
            <li><strong>Country</strong> — e.g. <code>PH</code> for Philippines</li>
            <li><strong>Device type</strong> — <code>mobile</code>, <code>desktop</code>, or <code>tablet</code></li>
            <li><strong>Browser</strong> — e.g. <code>Chrome 124</code></li>
            <li><strong>UTM source</strong> — e.g. <code>facebook</code>, <code>google</code></li>
        </ul>
        <p>After filtering, you see the segment's session count, pageview count, top pages visited, and top countries. Use this to answer questions like: <em>"Do mobile users from the Philippines visit different pages than desktop users?"</em></p>
    </div>
</div>

<!-- ══ 9. Alerts ══ -->
<div class="section" id="alerts">
    <div class="section-header">
        <div class="section-icon" style="background:rgba(248,113,113,0.12); color:#f87171;"><i class="bi bi-bell"></i></div>
        <h2>Alert Engine</h2>
        <span class="badge-new">Palantir</span>
    </div>
    <div class="prose">
        <p>The Alert Engine monitors your sites automatically and sends you an email when something looks wrong. Alerts run every 15 minutes via a cron job at <code>/cron/alerts.php</code>.</p>
        <p>Three alert types:</p>
    </div>
    <div class="step-list">
        <div class="step-item">
            <div class="step-num"></div>
            <div class="step-body">
                <div class="step-title">traffic_drop</div>
                <div class="step-desc">Fires when traffic in the current window is below a set % of the 7-day baseline average. E.g. if you normally get 50 pageviews/hour and the last hour had only 5, that's a 90% drop — likely a broken page or server issue.</div>
            </div>
        </div>
        <div class="step-item">
            <div class="step-num"></div>
            <div class="step-body">
                <div class="step-title">error_spike</div>
                <div class="step-desc">Fires when JS error events exceed a threshold in the current window. Catches broken deployments before users start complaining.</div>
            </div>
        </div>
        <div class="step-item">
            <div class="step-num"></div>
            <div class="step-body">
                <div class="step-title">no_traffic</div>
                <div class="step-desc">Fires when no pageviews have been recorded for X minutes. Catches total outages — if the site is down, the tracker can't fire, so this acts as a dead-man's switch.</div>
            </div>
        </div>
    </div>
    <div class="prose">
        <p>Each rule has a <strong>cooldown</strong> (minutes) that prevents repeated emails for the same ongoing problem. The Alert Log panel shows the last 10 fired alerts so you have a history of incidents.</p>
    </div>
    <div class="info-box yellow">
        <div class="ib-title">Email delivery</div>
        <p>Alerts use the Brevo transactional email API (key loaded from the loan-management <code>.env</code>). If Brevo is unavailable, it falls back to PHP <code>mail()</code>.</p>
    </div>
</div>

<!-- ══ 10. Retargeting ══ -->
<div class="section" id="retarget">
    <div class="section-header">
        <div class="section-icon" style="background:rgba(56,189,248,0.12); color:#38bdf8;"><i class="bi bi-cursor"></i></div>
        <h2>Retargeting panel</h2>
    </div>
    <div class="prose">
        <p>Enter any URL path (e.g. <code>/pricing</code>) and a date range, and the retargeting panel returns every logged-in user who visited that page. You also get a count of anonymous guest sessions for the same URL.</p>
        <p>Use the <strong>Export CSV</strong> button to download the user list with names and emails — ready to paste into an email campaign or Brevo contact list.</p>
    </div>
    <div class="info-box green">
        <div class="ib-title">Example use case</div>
        <p>Export everyone who visited <code>/pricing</code> in the last 30 days but never completed checkout — email them a discount code.</p>
    </div>
</div>

<!-- ══ 11. Export ══ -->
<div class="section" id="export">
    <div class="section-header">
        <div class="section-icon" style="background:rgba(52,211,153,0.12); color:#34d399;"><i class="bi bi-download"></i></div>
        <h2>CSV export</h2>
    </div>
    <div class="prose">
        <p>The <strong>Export CSV</strong> button at the top of the event log exports the current filtered view to a CSV file. The export respects all active filters: date range, event type, user filter, and search query. Columns exported: time, site, session ID, user, event type, URL, page title, element text, IP, browser, OS, device, country, city, UTM source/medium/campaign, scroll depth, time on page, load time.</p>
        <p>Exports are capped at <strong>5,000 rows</strong> per download to keep files manageable. For larger extracts, narrow the date range first.</p>
    </div>
</div>

</div><!-- /.omni-main -->
</div><!-- /.omni-layout -->
</body>
</html>
