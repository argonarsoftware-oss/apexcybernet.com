<?php
/**
 * panels/goals.php
 * Goal / Conversion Tracking panel HTML.
 * Expects (already set by analytics.php):
 *   $goals_data, $goals_list, $total_goal_completions, $total_conv_rate,
 *   $active_site, $date_range, $page_file
 */
?>

<div class="palantir-section" id="pal-goals">
    <div class="palantir-header" onclick="palToggle('pal-goals')">
        <i class="bi bi-bullseye pal-icon"></i> Goal Conversions
        <span class="pal-badge"><?= count($goals_list) ?> goal<?= count($goals_list) !== 1 ? 's' : '' ?></span>
        <?php if ($total_goal_completions > 0): ?>
        <span style="font-size:0.67rem; padding:0.1rem 0.5rem; border-radius:6px; background:rgba(52,211,153,0.15); color:#34d399; font-weight:700;"><?= $total_goal_completions ?> completions · <?= $total_conv_rate ?>% conv.</span>
        <?php endif; ?>
        <i class="bi bi-chevron-down pal-toggle"></i>
    </div>
    <div class="palantir-body">
        <?php if (empty($goals_list)): ?>
        <div style="color:#6b7280; font-size:0.78rem; margin-bottom:1rem;">No goals defined for <strong><?= htmlspecialchars($active_site) ?></strong>. Add one below.</div>
        <?php else: ?>
        <div style="overflow-x:auto; margin-bottom:1.25rem;">
        <table class="log-table">
            <thead><tr>
                <th>Goal</th>
                <th>URL Pattern</th>
                <th>Completions</th>
                <th>Conv. Rate</th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($goals_data as $gd): ?>
            <tr>
                <td style="font-weight:700; color:#e5e7eb;"><?= htmlspecialchars($gd['name']) ?></td>
                <td><code style="font-size:0.7rem; color:#a78bfa;"><?= htmlspecialchars($gd['url_pattern']) ?></code></td>
                <td><span style="font-weight:800; color:#34d399;"><?= number_format($gd['completions']) ?></span></td>
                <td>
                    <?php $cr = $gd['conv_rate'];
                    $cr_col = $cr >= 10 ? '#34d399' : ($cr >= 3 ? '#fbbf24' : '#9ca3af'); ?>
                    <span style="font-weight:700; color:<?= $cr_col ?>;"><?= $cr ?>%</span>
                </td>
                <td>
                    <a href="<?= htmlspecialchars($page_file) ?>?dr=<?= $date_range ?>&action=del_goal&id=<?= $gd['id'] ?>#pal-goals"
                       style="font-size:0.68rem; color:#f87171; text-decoration:none;"
                       onclick="return confirm('Delete this goal?')"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>

        <!-- Add goal form -->
        <div style="background:var(--surface2); border-radius:10px; padding:0.85rem 1rem;">
            <div style="font-size:0.72rem; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:0.65rem;"><i class="bi bi-plus-circle"></i> New Goal</div>
            <form method="POST" action="<?= htmlspecialchars($page_file) ?>?dr=<?= $date_range ?>#pal-goals" style="display:flex; gap:0.6rem; flex-wrap:wrap; align-items:flex-end;">
                <input type="hidden" name="action" value="add_goal">
                <input type="hidden" name="goal_site" value="<?= htmlspecialchars($active_site) ?>">
                <div style="display:flex; flex-direction:column; gap:0.2rem;">
                    <label style="font-size:0.67rem; color:#6b7280;">Goal Name</label>
                    <input type="text" name="goal_name" placeholder="e.g. Signup Complete" required
                           style="background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:6px; padding:0.3rem 0.6rem; font-size:0.78rem; width:180px;">
                </div>
                <div style="display:flex; flex-direction:column; gap:0.2rem;">
                    <label style="font-size:0.67rem; color:#6b7280;">URL Pattern (LIKE)</label>
                    <input type="text" name="goal_pattern" placeholder="e.g. /register/success" required
                           style="background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:6px; padding:0.3rem 0.6rem; font-size:0.78rem; width:200px;">
                </div>
                <button type="submit" style="background:rgba(52,211,153,0.12); border:1px solid rgba(52,211,153,0.3); color:#34d399; border-radius:7px; padding:0.3rem 0.8rem; font-size:0.76rem; cursor:pointer; font-weight:700;">
                    <i class="bi bi-plus"></i> Add Goal
                </button>
            </form>
        </div>
    </div>
</div>
