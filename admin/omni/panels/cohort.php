<?php
/**
 * panels/cohort.php
 * Cohort Retention (Weekly) panel.
 * Expects: $argonar_pdo, $active_site
 */

$cohort_data = [];
$cohort_error = null;
try {
    // Self-join approach — no window functions, works on MySQL 5.7+
    $st = $argonar_pdo->prepare("
        SELECT
            YEARWEEK(base.first_seen, 1) AS cohort_week,
            COUNT(DISTINCT base.uid) AS cohort_size,
            COUNT(DISTINCT CASE WHEN YEARWEEK(ug.last_seen,1) = YEARWEEK(base.first_seen,1)+1 THEN base.uid END) AS w1,
            COUNT(DISTINCT CASE WHEN YEARWEEK(ug.last_seen,1) = YEARWEEK(base.first_seen,1)+2 THEN base.uid END) AS w2,
            COUNT(DISTINCT CASE WHEN YEARWEEK(ug.last_seen,1) = YEARWEEK(base.first_seen,1)+3 THEN base.uid END) AS w3,
            COUNT(DISTINCT CASE WHEN YEARWEEK(ug.last_seen,1) = YEARWEEK(base.first_seen,1)+4 THEN base.uid END) AS w4
        FROM (
            SELECT uid, MIN(first_seen) AS first_seen
            FROM user_graph
            WHERE site = ? AND uid IS NOT NULL
            GROUP BY uid
        ) base
        JOIN user_graph ug ON ug.uid = base.uid AND ug.site = ?
        GROUP BY cohort_week
        ORDER BY cohort_week DESC
        LIMIT 8
    ");
    $st->execute([$active_site, $active_site]);
    $cohort_data = $st->fetchAll();
} catch (Exception $e) {
    $cohort_error = $e->getMessage();
}

// Helper: color cell by retention %
function cohort_cell_color(float $pct): string {
    if ($pct >= 40) return '#34d399';
    if ($pct >= 20) return '#fbbf24';
    return '#f87171';
}

// Format cohort_week (YYYYWW) to readable label
function cohort_week_label(string $yw): string {
    $year = substr($yw, 0, 4);
    $week = (int)substr($yw, 4);
    return "W{$week} '{$year}";
}
?>

<!-- Cohort Retention Palantir Panel -->
<div class="palantir-section" id="pal-cohort">
    <div class="palantir-header" onclick="palToggle('pal-cohort')">
        <i class="bi bi-arrow-repeat pal-icon"></i> Cohort Retention
        <span class="pal-badge">Weekly</span>
        <i class="bi bi-chevron-down pal-toggle"></i>
    </div>
    <div class="palantir-body">
        <?php if ($cohort_error !== null): ?>
        <div style="color:#fbbf24; font-size:0.78rem; padding:0.4rem 0;"><i class="bi bi-exclamation-triangle"></i> Query error: <code style="font-size:0.68rem;"><?= htmlspecialchars(substr($cohort_error, 0, 120)) ?></code></div>
        <?php elseif (empty($cohort_data)): ?>
        <div style="color:#6b7280; font-size:0.78rem;">Need more return visitors to build cohort data.</div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="log-table">
            <thead><tr>
                <th>Week</th>
                <th>Cohort Size</th>
                <th>W+1</th>
                <th>W+2</th>
                <th>W+3</th>
                <th>W+4</th>
            </tr></thead>
            <tbody>
            <?php foreach ($cohort_data as $row):
                $size = max(1, (int)$row['cohort_size']);
                $w1 = (int)($row['w1'] ?? 0);
                $w2 = (int)($row['w2'] ?? 0);
                $w3 = (int)($row['w3'] ?? 0);
                $w4 = (int)($row['w4'] ?? 0);
                $p1 = round($w1 / $size * 100, 1);
                $p2 = round($w2 / $size * 100, 1);
                $p3 = round($w3 / $size * 100, 1);
                $p4 = round($w4 / $size * 100, 1);
            ?>
            <tr>
                <td style="font-weight:700; color:#e5e7eb;"><?= htmlspecialchars(cohort_week_label((string)$row['cohort_week'])) ?></td>
                <td><span style="font-weight:700; color:#9ca3af;"><?= number_format($size) ?></span></td>
                <?php foreach ([[$p1,$w1],[$p2,$w2],[$p3,$w3],[$p4,$w4]] as [$pct, $cnt]): ?>
                <td>
                    <span style="font-weight:800; color:<?= cohort_cell_color($pct) ?>;"><?= $pct ?>%</span>
                    <span style="font-size:0.65rem; color:#4b5563; margin-left:3px;">(<?= $cnt ?>)</span>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div style="font-size:0.7rem; color:#4b5563; margin-top:0.5rem;">
            <span style="color:#34d399;">&#9646;</span> ≥40%&nbsp;
            <span style="color:#fbbf24;">&#9646;</span> ≥20%&nbsp;
            <span style="color:#f87171;">&#9646;</span> &lt;20%
        </div>
        <?php endif; ?>
    </div>
</div>
