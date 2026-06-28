<?php
$active_site = 'alrisha';
$page_file   = 'activity-alrisha.php';
require_once __DIR__ . '/../includes/db.php';
$argonar_pdo = $pdo;
require_once __DIR__ . '/omni/auth.php';

// ── AJAX: Alrisha ERP data ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'alrisha_data') {
    header('Content-Type: application/json');
    $company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
    try {
        $argonar_pdo->exec("CREATE TABLE IF NOT EXISTS alrisha_snapshots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT DEFAULT 0,
            snapshot LONGTEXT NOT NULL,
            pushed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (company_id), INDEX (pushed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $st = $argonar_pdo->prepare("SELECT snapshot, pushed_at FROM alrisha_snapshots
            WHERE company_id = ? ORDER BY pushed_at DESC LIMIT 1");
        $st->execute([$company_id]);
        $row = $st->fetch();
        if (!$row) {
            echo json_encode(['ok' => false, 'error' => 'No snapshot yet. Run sync-to-argonar.php from your local Alrisha.']);
        } else {
            $snap = json_decode($row['snapshot'], true);
            $snap['ok'] = true;
            $snap['pushed_at'] = $row['pushed_at'];
            echo json_encode($snap);
        }
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── Multi-company switcher ──
$alrisha_companies = [];
try {
    $argonar_pdo->exec("CREATE TABLE IF NOT EXISTS alrisha_snapshots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT DEFAULT 0,
        snapshot LONGTEXT NOT NULL,
        pushed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (company_id), INDEX (pushed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $alrisha_companies = $argonar_pdo->query("SELECT DISTINCT company_id FROM alrisha_snapshots ORDER BY company_id")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
$selected_company = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;

// ── Sidebar quick-stats ──
$sidebar_stats = ['argonar'=>['sessions'=>0,'live'=>0],'ocpd'=>['sessions'=>0,'live'=>0],'loan'=>['sessions'=>0,'live'=>0],'alrisha'=>['sessions'=>0,'live'=>0]];
try {
    $rows_sb = $argonar_pdo->query("SELECT CASE WHEN site IS NULL OR site='' THEN 'argonar' ELSE site END as s,
        COUNT(DISTINCT session_id) as n FROM activity_logs WHERE created_at >= CURDATE() GROUP BY s")->fetchAll();
    foreach ($rows_sb as $r) if (isset($sidebar_stats[$r['s']])) $sidebar_stats[$r['s']]['sessions'] = (int)$r['n'];
    $rows_sb = $argonar_pdo->query("SELECT CASE WHEN site IS NULL OR site='' THEN 'argonar' ELSE site END as s,
        COUNT(DISTINCT session_id) as n FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) GROUP BY s")->fetchAll();
    foreach ($rows_sb as $r) if (isset($sidebar_stats[$r['s']])) $sidebar_stats[$r['s']]['live'] = (int)$r['n'];
} catch (Exception $e) {}

// Date range (used by sidebar link)
$date_range = $_GET['dr'] ?? 'today';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Alrisha ERP — Omniscient</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<?php require __DIR__ . '/omni/css.php'; ?>
</head>
<body>
<div class="omni-layout">

<?php require __DIR__ . '/omni/sidebar.php'; ?>

<div class="omni-main">

<!-- ══ TOPBAR ══ -->
<div class="topbar">
    <a href="<?= base_url('admin/') ?>" class="btn-back-site" style="border-color:rgba(107,114,128,0.35); color:#9ca3af; font-size:0.75rem; padding:0.3rem 0.65rem; text-decoration:none; display:inline-flex; align-items:center; gap:0.3rem;"><i class="bi bi-arrow-left"></i> Admin</a>
    <h1 style="font-size:0.95rem;">
        <span style="color:#34d399;"><i class="bi bi-graph-up-arrow"></i> Alrisha ERP</span>
    </h1>
    <?php if (count($alrisha_companies) > 1): ?>
    <div style="margin-left:1rem; display:flex; align-items:center; gap:0.4rem;">
        <label style="font-size:0.7rem; color:#6b7280; flex-shrink:0;">Company:</label>
        <select id="companySwitcher"
                style="background:var(--surface); border:1px solid var(--border); color:var(--text); border-radius:7px; padding:0.22rem 0.55rem; font-size:0.76rem; cursor:pointer;"
                onchange="switchCompany(this.value)">
            <option value="0" <?= $selected_company === 0 ? 'selected' : '' ?>>All</option>
            <?php foreach ($alrisha_companies as $cid): ?>
            <option value="<?= (int)$cid ?>" <?= $selected_company === (int)$cid ? 'selected' : '' ?>>Company <?= (int)$cid ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <span style="margin-left:auto; font-size:0.72rem; color:#6b7280;" id="lastRefresh"></span>
</div>

<div class="wrap">

<!-- ══ ALRISHA ERP PANEL ══ -->
<div class="erp-panel">
    <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1rem;">
        <span style="font-size:1rem; color:#34d399;">◈</span>
        <h2 style="font-size:1rem; font-weight:800; color:var(--text); margin:0;">Alrisha ERP</h2>
        <span class="erp-status" id="erp-status">Loading…</span>
        <button onclick="loadErp(<?= (int)$selected_company ?>)" style="margin-left:auto; background:rgba(52,211,153,0.1); border:1px solid rgba(52,211,153,0.25); color:#34d399; border-radius:7px; padding:0.25rem 0.65rem; font-size:0.72rem; cursor:pointer;">↺ Refresh</button>
    </div>
    <div id="erp-body">
        <div style="color:#6b7280; font-size:0.82rem; padding:1rem 0;">Fetching ERP data…</div>
    </div>
</div>

</div><!-- /wrap -->
</div><!-- /.omni-main -->
</div><!-- /.omni-layout -->

<script>
document.getElementById('lastRefresh').textContent = 'Updated ' + new Date().toLocaleTimeString();

function switchCompany(cid) {
    const url = new URL(window.location.href);
    url.searchParams.set('company_id', cid);
    window.location.href = url.toString();
}

function loadErp(company_id) {
    const status = document.getElementById('erp-status');
    const body   = document.getElementById('erp-body');
    status.textContent = 'Loading…'; status.className = 'erp-status';
    body.innerHTML = '<div style="color:#6b7280;font-size:0.82rem;padding:1rem 0;">Fetching ERP data…</div>';
    const cq = company_id ? '&company_id=' + company_id : '';
    fetch('activity-alrisha.php?ajax=alrisha_data' + cq)
        .then(r => r.json())
        .then(d => {
            if (!d.ok) {
                status.textContent = '✗ ' + (d.error || 'Error');
                status.className = 'erp-status err';
                body.innerHTML = '<div style="color:#f87171;font-size:0.82rem;padding:0.5rem 0;">' + (d.error||'Unknown error') + '</div>';
                return;
            }
            const pushedAt = d.pushed_at ? ' · last push ' + d.pushed_at.slice(0,16) : '';
            status.textContent = '✓ Live' + pushedAt;
            status.className = 'erp-status ok';
            body.innerHTML = renderErp(d);
        })
        .catch(e => {
            status.textContent = '✗ ' + e;
            status.className = 'erp-status err';
            body.innerHTML = '<div style="color:#f87171;font-size:0.82rem;padding:0.5rem 0;">Request failed: ' + e + '</div>';
        });
}

function fmt(n) { return n != null ? parseFloat(n).toLocaleString('en-PH', {minimumFractionDigits:2,maximumFractionDigits:2}) : '—'; }
function fmtInt(n) { return n != null ? parseInt(n).toLocaleString() : '—'; }
function statusBadge(s) {
    if (!s) return '';
    const cls = {draft:'draft','pending approval':'pending','pending':'pending','approved':'approved',open:'open',issued:'issued',paid:'paid','partially paid':'open',cancelled:'cancelled','fulfilled':'approved'}[s.toLowerCase()] || 'draft';
    return `<span class="erp-badge ${cls}">${s}</span>`;
}

function renderErp(d) {
    const s = d.sales || {}, p = d.purchases || {}, py = d.payables || {}, inv = d.inventory || {};
    const currency = '₱';

    let html = `<div class="erp-kpi-grid">
        <div class="erp-kpi${parseFloat(py.overdue_payables||0)>0?' danger':''}">
            <div class="lbl">Receivables</div>
            <div class="val">${currency}${fmt(s.total_receivables)}</div>
            ${parseFloat(s.overdue_receivables||0)>0?`<div class="sub" style="color:#f87171;">⚠ ${currency}${fmt(s.overdue_receivables)} overdue</div>`:''}
        </div>
        <div class="erp-kpi${parseFloat(py.overdue_payables||0)>0?' warn':''}">
            <div class="lbl">Payables</div>
            <div class="val">${currency}${fmt(py.total_payables)}</div>
            ${parseFloat(py.overdue_payables||0)>0?`<div class="sub" style="color:#fbbf24;">⚠ ${currency}${fmt(py.overdue_payables)} overdue</div>`:''}
        </div>
        <div class="erp-kpi good">
            <div class="lbl">MTD Revenue</div>
            <div class="val">${currency}${fmt(s.mtd_revenue)}</div>
            <div class="sub">${fmtInt(s.open_invoices)} open invoices</div>
        </div>
        <div class="erp-kpi${parseInt(inv.low_stock_items||0)>0?' warn':''}">
            <div class="lbl">Inventory Value</div>
            <div class="val">${currency}${fmt(inv.total_inventory_value)}</div>
            <div class="sub">${fmtInt(inv.total_items)} items · ${fmtInt(inv.low_stock_items)||0} low stock</div>
        </div>
        <div class="erp-kpi${parseInt(p.pending_approval||0)>0?' warn':''}">
            <div class="lbl">Pending POs</div>
            <div class="val">${fmtInt(p.pending_approval)}</div>
            <div class="sub">${fmtInt(p.total_pos)} total POs</div>
        </div>
        <div class="erp-kpi">
            <div class="lbl">Active Jobs</div>
            <div class="val">${fmtInt(d.active_jobs)}</div>
            <div class="sub">${fmtInt(d.pending_requisitions)} pending reqs</div>
        </div>
        <div class="erp-kpi">
            <div class="lbl">Companies</div>
            <div class="val">${fmtInt(d.companies)}</div>
            <div class="sub">${fmtInt(d.active_users)} active users</div>
        </div>
    </div>`;

    // Revenue chart (last 6 months)
    if (d.monthly_revenue && d.monthly_revenue.length) {
        const maxRev = Math.max(...d.monthly_revenue.map(r => parseFloat(r.revenue||0)), 1);
        html += `<div class="erp-section-title">Revenue — Last 6 Months</div>
        <div class="erp-revenue-bar">`;
        d.monthly_revenue.forEach(r => {
            const h = Math.round((parseFloat(r.revenue||0)/maxRev)*46) + 'px';
            html += `<div class="bar-col">
                <div class="bar" style="height:${h};" title="${currency}${fmt(r.revenue)}"></div>
                <div class="bar-lbl">${r.month.slice(5)}</div>
            </div>`;
        });
        html += `</div>`;
    }

    html += `<div class="erp-two-col">`;

    // Recent Sales Invoices
    if (d.recent_sales_invoices && d.recent_sales_invoices.length) {
        html += `<div>
        <div class="erp-section-title">Recent Sales Invoices</div>
        <table class="erp-table"><thead><tr><th>Invoice</th><th>Customer</th><th>Amount</th><th>Status</th></tr></thead><tbody>`;
        d.recent_sales_invoices.forEach(r => {
            html += `<tr>
                <td style="font-family:monospace;color:#a78bfa;">${r.invoice_number||'—'}</td>
                <td>${r.customer_name||'—'}</td>
                <td>${currency}${fmt(r.total_amount)}</td>
                <td>${statusBadge(r.status)}</td>
            </tr>`;
        });
        html += `</tbody></table></div>`;
    }

    // Recent Purchase Orders
    if (d.recent_purchase_orders && d.recent_purchase_orders.length) {
        html += `<div>
        <div class="erp-section-title">Recent Purchase Orders</div>
        <table class="erp-table"><thead><tr><th>PO #</th><th>Vendor</th><th>Amount</th><th>Status</th></tr></thead><tbody>`;
        d.recent_purchase_orders.forEach(r => {
            html += `<tr>
                <td style="font-family:monospace;color:#38bdf8;">${r.po_number||'—'}</td>
                <td>${r.vendor_name||'—'}</td>
                <td>${currency}${fmt(r.total_amount)}</td>
                <td>${statusBadge(r.status)}</td>
            </tr>`;
        });
        html += `</tbody></table></div>`;
    }

    html += `</div>`;

    // Low Stock
    if (d.low_stock && d.low_stock.length) {
        html += `<div class="erp-section-title" style="color:#fbbf24;">⚠ Low Stock Items</div>
        <table class="erp-table"><thead><tr><th>Item</th><th>On Hand</th><th>Reorder At</th><th>Location</th></tr></thead><tbody>`;
        d.low_stock.forEach(r => {
            html += `<tr>
                <td><span style="color:#e5e7eb;font-weight:600;">${r.item_name||'—'}</span><div style="font-size:0.65rem;color:#6b7280;">${r.item_code}</div></td>
                <td style="color:#f87171;font-weight:700;">${r.quantity_on_hand} ${r.unit_of_measure||''}</td>
                <td style="color:#fbbf24;">${r.reorder_point}</td>
                <td style="color:#9ca3af;">${r.location_name||'—'}</td>
            </tr>`;
        });
        html += `</tbody></table>`;
    }

    // Recent Activity
    if (d.recent_activity && d.recent_activity.length) {
        html += `<div class="erp-section-title">Recent Activity</div>
        <div style="display:flex;flex-direction:column;gap:0.25rem;">`;
        d.recent_activity.forEach(a => {
            const who = [a.first_name, a.last_name].filter(Boolean).join(' ') || 'System';
            const time = a.created_at ? a.created_at.slice(5,16) : '';
            const col = a.action_type==='INSERT'?'#34d399':a.action_type==='DELETE'?'#f87171':'#9ca3af';
            html += `<div style="display:flex;gap:0.6rem;font-size:0.74rem;padding:0.2rem 0;border-bottom:1px solid rgba(255,255,255,0.04);">
                <span style="color:${col};font-weight:700;min-width:48px;">${a.action_type||''}</span>
                <span style="color:#9ca3af;">${a.table_name||''}</span>
                <span style="color:#6b7280;margin-left:auto;">${who} · ${time}</span>
            </div>`;
        });
        html += `</div>`;
    }

    return html;
}
document.addEventListener('DOMContentLoaded', function() { loadErp(<?= (int)$selected_company ?>); });
</script>
</body>
</html>
