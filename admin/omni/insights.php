<?php
/**
 * admin/omni/insights.php
 * "Today's Briefing" panel — self-contained analytics narrative.
 *
 * Expects from calling context:
 *   $argonar_pdo, $active_site, $site_cond, $date_range,
 *   $page_file, $date_cond
 * Helper functions: short_url(), country_flag(), pct_change(), trend_badge()
 */

// ══════════════════════════════════════════════
// A. SITUATION SUMMARY
// ══════════════════════════════════════════════

// Today vs yesterday pageviews
$today_pv     = 0;
$yesterday_pv = 0;
try {
    $today_pv = (int)$argonar_pdo->query(
        "SELECT COUNT(*) FROM activity_logs
         WHERE event_type='pageview' AND created_at >= CURDATE() $site_cond"
    )->fetchColumn();

    $yesterday_pv = (int)$argonar_pdo->query(
        "SELECT COUNT(*) FROM activity_logs
         WHERE event_type='pageview'
           AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
           AND created_at < CURDATE() $site_cond"
    )->fetchColumn();
} catch (Exception $e) {}

// This week vs last week sessions
$this_week_sess = 0;
$last_week_sess = 0;
try {
    $this_week_sess = (int)$argonar_pdo->query(
        "SELECT COUNT(DISTINCT session_id) FROM activity_logs
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) $site_cond"
    )->fetchColumn();

    $last_week_sess = (int)$argonar_pdo->query(
        "SELECT COUNT(DISTINCT session_id) FROM activity_logs
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
           AND created_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY) $site_cond"
    )->fetchColumn();
} catch (Exception $e) {}

// This month vs last month sessions
$this_month_sess = 0;
$last_month_sess = 0;
try {
    $this_month_sess = (int)$argonar_pdo->query(
        "SELECT COUNT(DISTINCT session_id) FROM activity_logs
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) $site_cond"
    )->fetchColumn();

    $last_month_sess = (int)$argonar_pdo->query(
        "SELECT COUNT(DISTINCT session_id) FROM activity_logs
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
           AND created_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY) $site_cond"
    )->fetchColumn();
} catch (Exception $e) {}

// Top page today
$top_page_today     = null;
$top_page_today_cnt = 0;
try {
    $tp = $argonar_pdo->query(
        "SELECT page_url, COUNT(*) AS c FROM activity_logs
         WHERE event_type='pageview' AND created_at >= CURDATE() $site_cond
         GROUP BY page_url ORDER BY c DESC LIMIT 1"
    )->fetch();
    if ($tp) { $top_page_today = $tp['page_url']; $top_page_today_cnt = (int)$tp['c']; }
} catch (Exception $e) {}

// Top referrer today
$top_referrer_today = null;
try {
    $ref = $argonar_pdo->query(
        "SELECT
            CASE WHEN referrer IS NULL OR referrer='' THEN 'Direct / None'
                 ELSE REGEXP_REPLACE(referrer, '^https?://(www\\.)?([^/]+).*$', '\\\\2') END AS src,
            COUNT(*) AS c
         FROM activity_logs
         WHERE event_type='pageview' AND created_at >= CURDATE() $site_cond
         GROUP BY src ORDER BY c DESC LIMIT 1"
    )->fetch();
    if ($ref) $top_referrer_today = $ref['src'];
} catch (Exception $e) {}

// Build situation bullets
$sit_bullets = [];

if ($today_pv === 0) {
    $sit_bullets[] = 'No traffic recorded yet today.';
} elseif ($yesterday_pv === 0) {
    $sit_bullets[] = number_format($today_pv) . ' pageviews today (no data for yesterday).';
} else {
    $pv_pct = round(($today_pv - $yesterday_pv) / max(1, $yesterday_pv) * 100);
    $dir    = $pv_pct >= 0 ? 'up' : 'down';
    $col    = $pv_pct >= 0 ? '#34d399' : '#f87171';
    $sit_bullets[] = 'Traffic is <strong style="color:' . $col . '">' . $dir . ' ' . abs($pv_pct) . '%</strong> today vs yesterday (' . number_format($yesterday_pv) . ' → ' . number_format($today_pv) . ' pageviews).';
}

if ($this_week_sess > 0 || $last_week_sess > 0) {
    if ($last_week_sess === 0) {
        $sit_bullets[] = number_format($this_week_sess) . ' sessions this week (no prior week data).';
    } else {
        $w_pct = round(($this_week_sess - $last_week_sess) / max(1, $last_week_sess) * 100);
        $w_dir = $w_pct >= 0 ? 'more' : 'fewer';
        $w_col = $w_pct >= 0 ? '#34d399' : '#f87171';
        $sit_bullets[] = 'This week you have <strong style="color:' . $w_col . '">' . abs($w_pct) . '% ' . $w_dir . ' sessions</strong> than last week (' . number_format($last_week_sess) . ' → ' . number_format($this_week_sess) . ').';
    }
}

if ($top_page_today) {
    $short = function_exists('short_url') ? short_url($top_page_today) : htmlspecialchars($top_page_today);
    $sit_bullets[] = 'Your top page today is <strong>' . $short . '</strong> with ' . number_format($top_page_today_cnt) . ' views.';
}

if ($top_referrer_today) {
    $sit_bullets[] = 'Most traffic arrives from <strong>' . htmlspecialchars($top_referrer_today) . '</strong> today.';
}

// ══════════════════════════════════════════════
// B. ACTION ITEMS
// ══════════════════════════════════════════════

$action_items = [];

// 1. High-bounce pages
try {
    $bounce_pages = $argonar_pdo->query(
        "SELECT page_url,
            COUNT(DISTINCT session_id) AS sessions,
            SUM(CASE WHEN single_event=1 THEN 1 ELSE 0 END) AS bounced
         FROM (
            SELECT session_id, page_url,
                   (COUNT(*) OVER (PARTITION BY session_id) = 1) AS single_event
            FROM activity_logs
            WHERE event_type='pageview' $date_cond $site_cond
         ) t
         GROUP BY page_url
         HAVING sessions >= 5 AND bounced/sessions > 0.8
         ORDER BY sessions DESC LIMIT 5"
    )->fetchAll();

    foreach (array_slice($bounce_pages, 0, 1) as $bp) {
        $bpct = round($bp['bounced'] / max(1, $bp['sessions']) * 100);
        $short = function_exists('short_url') ? short_url($bp['page_url']) : htmlspecialchars($bp['page_url']);
        $action_items[] = [
            'sev'  => 'red',
            'icon' => 'bi-arrow-bar-right',
            'msg'  => $short . ' has <strong>' . $bpct . '% bounce rate</strong> — users leave immediately (' . number_format($bp['sessions']) . ' sessions).',
        ];
    }
} catch (Exception $e) {}

// 2. JS errors today
try {
    $js_err_count = (int)$argonar_pdo->query(
        "SELECT COUNT(DISTINCT action_label) FROM activity_logs
         WHERE event_type='error' AND created_at >= CURDATE() $site_cond"
    )->fetchColumn();

    if ($js_err_count > 0) {
        $js_err_page = null;
        try {
            $jep = $argonar_pdo->query(
                "SELECT page_url, COUNT(*) AS c FROM activity_logs
                 WHERE event_type='error' AND created_at >= CURDATE() $site_cond
                 GROUP BY page_url ORDER BY c DESC LIMIT 1"
            )->fetch();
            if ($jep) $js_err_page = $jep['page_url'];
        } catch (Exception $e) {}

        $err_msg = $js_err_count . ' JS ' . ($js_err_count === 1 ? 'error' : 'errors') . ' detected today';
        if ($js_err_page) {
            $short = function_exists('short_url') ? short_url($js_err_page) : htmlspecialchars($js_err_page);
            $err_msg .= ' on ' . $short;
        }
        $err_msg .= '.';
        $action_items[] = ['sev' => 'red', 'icon' => 'bi-bug', 'msg' => $err_msg];
    }
} catch (Exception $e) {}

// 3. 404 pages in last 7 days
try {
    $not_found_count = (int)$argonar_pdo->query(
        "SELECT COUNT(DISTINCT page_url) FROM activity_logs
         WHERE event_type='pageview'
           AND (page_url LIKE '%404%' OR page_url LIKE '%not-found%' OR page_url LIKE '%notfound%' OR page_url LIKE '%error%')
           AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) $site_cond"
    )->fetchColumn();

    if ($not_found_count > 0) {
        $action_items[] = [
            'sev'  => 'red',
            'icon' => 'bi-exclamation-triangle',
            'msg'  => '<strong>' . number_format($not_found_count) . ' broken ' . ($not_found_count === 1 ? 'page' : 'pages') . '</strong> getting traffic this week.',
        ];
    }
} catch (Exception $e) {}

// 4. Traffic drop — page with biggest % drop today vs yesterday
try {
    $drop_rows = $argonar_pdo->query(
        "SELECT t.page_url, t.today_c, y.yesterday_c,
                ROUND((t.today_c - y.yesterday_c) / y.yesterday_c * 100) AS pct_change
         FROM (
            SELECT page_url, COUNT(*) AS today_c
            FROM activity_logs
            WHERE event_type='pageview' AND created_at >= CURDATE() $site_cond
            GROUP BY page_url
         ) t
         JOIN (
            SELECT page_url, COUNT(*) AS yesterday_c
            FROM activity_logs
            WHERE event_type='pageview'
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
              AND created_at < CURDATE() $site_cond
            GROUP BY page_url
            HAVING COUNT(*) >= 10
         ) y ON t.page_url = y.page_url
         WHERE t.today_c < y.yesterday_c
         ORDER BY pct_change ASC LIMIT 1"
    )->fetch();

    if ($drop_rows && $drop_rows['pct_change'] <= -20) {
        $short = function_exists('short_url') ? short_url($drop_rows['page_url']) : htmlspecialchars($drop_rows['page_url']);
        $action_items[] = [
            'sev'  => 'yellow',
            'icon' => 'bi-graph-down-arrow',
            'msg'  => $short . ' lost <strong>' . abs($drop_rows['pct_change']) . '% of its traffic</strong> today vs yesterday.',
        ];
    }
} catch (Exception $e) {}

// 5. Lapsed users (active 7–30 days ago, nothing in last 7 days)
try {
    $lapsed_count = (int)$argonar_pdo->query(
        "SELECT COUNT(DISTINCT account_id) FROM activity_logs
         WHERE account_id IS NOT NULL $site_cond
           AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
           AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
           AND account_id NOT IN (
               SELECT DISTINCT account_id FROM activity_logs
               WHERE account_id IS NOT NULL $site_cond
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
           )"
    )->fetchColumn();

    if ($lapsed_count > 3) {
        $action_items[] = [
            'sev'  => 'yellow',
            'icon' => 'bi-person-x',
            'msg'  => '<strong>' . number_format($lapsed_count) . ' users</strong> haven\'t returned in 7–30 days.',
        ];
    }
} catch (Exception $e) {}

// 6. Good signal — traffic growing and bounce OK
if (count($action_items) === 0 || (isset($pv_pct) && $pv_pct > 20)) {
    if (isset($pv_pct) && $pv_pct > 20) {
        $action_items[] = [
            'sev'  => 'green',
            'icon' => 'bi-rocket-takeoff',
            'msg'  => 'Traffic is <strong>growing ' . $pv_pct . '%</strong> today. Keep it up.',
        ];
    }
}

// Cap at 6
$action_items = array_slice($action_items, 0, 6);

// ══════════════════════════════════════════════
// C. GROWTH NARRATIVE
// ══════════════════════════════════════════════

// Sessions: this week vs last week
$g_sessions_now  = $this_week_sess;
$g_sessions_prev = $last_week_sess;

// Unique users: this 30 days vs prev 30 days
$g_users_now  = 0;
$g_users_prev = 0;
try {
    $g_users_now = (int)$argonar_pdo->query(
        "SELECT COUNT(DISTINCT account_id) FROM activity_logs
         WHERE account_id IS NOT NULL
           AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) $site_cond"
    )->fetchColumn();

    $g_users_prev = (int)$argonar_pdo->query(
        "SELECT COUNT(DISTINCT account_id) FROM activity_logs
         WHERE account_id IS NOT NULL
           AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
           AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) $site_cond"
    )->fetchColumn();
} catch (Exception $e) {}

// Pageviews: this month vs last month
$g_pv_now  = 0;
$g_pv_prev = 0;
try {
    $g_pv_now = (int)$argonar_pdo->query(
        "SELECT COUNT(*) FROM activity_logs
         WHERE event_type='pageview'
           AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) $site_cond"
    )->fetchColumn();

    $g_pv_prev = (int)$argonar_pdo->query(
        "SELECT COUNT(*) FROM activity_logs
         WHERE event_type='pageview'
           AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
           AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) $site_cond"
    )->fetchColumn();
} catch (Exception $e) {}

function briefing_growth_pct($now, $prev) {
    if ($prev === 0) return null;
    return round(($now - $prev) / $prev * 100);
}

function briefing_trend_html($pct) {
    if ($pct === null) return '<span style="color:#6b7280">— no prev data</span>';
    $arrow = $pct >= 0 ? '↑' : '↓';
    $color = $pct >= 0 ? '#34d399' : '#f87171';
    return '<span style="color:' . $color . '">' . $arrow . abs($pct) . '%</span>';
}

// ══════════════════════════════════════════════
// D. USER JOURNEY DIGEST
// ══════════════════════════════════════════════

$journeys = [];
try {
    $journeys = $argonar_pdo->query(
        "SELECT e.page_url AS entry_url, x.page_url AS exit_url, COUNT(*) AS cnt
         FROM (
            SELECT session_id, MIN(id) AS first_id, MAX(id) AS last_id
            FROM activity_logs
            WHERE event_type='pageview' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            $site_cond
            GROUP BY session_id
         ) sess
         JOIN activity_logs e ON e.id = sess.first_id
         JOIN activity_logs x ON x.id = sess.last_id
         GROUP BY entry_url, exit_url
         ORDER BY cnt DESC
         LIMIT 7"
    )->fetchAll();
} catch (Exception $e) { $journeys = []; }

// ══════════════════════════════════════════════
// E. MOST VALUABLE USERS
// ══════════════════════════════════════════════

$top_users     = [];
$top_user_max  = 1;
try {
    $top_users = $argonar_pdo->query(
        "SELECT account_id, display_name,
                COUNT(DISTINCT session_id) AS sessions,
                COUNT(DISTINCT DATE(created_at)) AS active_days,
                COUNT(*) AS total_events,
                MAX(created_at) AS last_seen
         FROM activity_logs
         WHERE account_id IS NOT NULL $site_cond
           AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY account_id, display_name
         ORDER BY (COUNT(DISTINCT session_id)*3 + COUNT(DISTINCT DATE(created_at))*2) DESC
         LIMIT 8"
    )->fetchAll();

    foreach ($top_users as $u) {
        $score = $u['sessions'] * 3 + $u['active_days'] * 2 + $u['total_events'] / 5;
        $top_user_max = max($top_user_max, $score);
    }
} catch (Exception $e) { $top_users = []; }

function briefing_time_ago($ts) {
    if (!$ts) return 'never';
    $diff = time() - strtotime($ts);
    if ($diff < 60)      return 'just now';
    if ($diff < 3600)    return floor($diff / 60) . 'm ago';
    if ($diff < 86400)   return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

?>
<style>
.briefing-panel {
  background: linear-gradient(135deg, rgba(124,58,237,0.06) 0%, rgba(10,10,15,0) 60%);
  border: 1px solid rgba(124,58,237,0.2);
  border-radius: 14px;
  margin-bottom: 1.5rem;
  overflow: hidden;
}
.briefing-panel.collapsed .briefing-body { display: none; }
.briefing-header {
  display: flex; align-items: center; gap: 0.75rem;
  padding: 0.85rem 1.25rem;
  cursor: pointer; user-select: none;
  font-size: 0.88rem; font-weight: 700; color: #e5e7eb;
  border-bottom: 1px solid rgba(124,58,237,0.15);
}
.briefing-header:hover { background: rgba(124,58,237,0.06); }
.briefing-meta { font-size: 0.68rem; color: #6b7280; font-weight: 400; margin-left: 0.5rem; }
.briefing-toggle { margin-left: auto; color: #6b7280; transition: transform 0.2s; }
.briefing-panel.collapsed .briefing-toggle { transform: rotate(-90deg); }
.briefing-body { padding: 1.25rem; display: flex; flex-direction: column; gap: 1.25rem; }
.briefing-top { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
.briefing-bottom { display: grid; grid-template-columns: 1fr 1.4fr 1.6fr; gap: 1.25rem; }
.brief-card {
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.07);
  border-radius: 10px; padding: 1rem 1.1rem;
}
.brief-card-title {
  font-size: 0.67rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.08em; color: #6b7280; margin-bottom: 0.7rem;
}
/* Situation */
.brief-situation ul { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.5rem; }
.brief-situation li { font-size: 0.81rem; color: #d1d5db; line-height: 1.5; padding-left: 1rem; position: relative; }
.brief-situation li::before { content: '·'; position: absolute; left: 0; color: #7c3aed; font-weight: 900; }
.brief-situation strong { color: #e5e7eb; }
/* Action items */
.action-item { display: flex; align-items: flex-start; gap: 0.5rem; padding: 0.35rem 0; border-bottom: 1px solid rgba(255,255,255,0.04); font-size: 0.77rem; color: #d1d5db; line-height: 1.4; }
.action-item:last-child { border-bottom: none; }
.action-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; margin-top: 4px; }
.action-dot.red { background: #f87171; box-shadow: 0 0 6px rgba(248,113,113,0.5); }
.action-dot.yellow { background: #fbbf24; box-shadow: 0 0 6px rgba(251,191,36,0.4); }
.action-dot.green { background: #34d399; box-shadow: 0 0 6px rgba(52,211,153,0.4); }
/* Growth */
.growth-stat { text-align: center; padding: 0.5rem 0; }
.growth-stat .g-val { font-size: 1.6rem; font-weight: 900; line-height: 1; }
.growth-stat .g-lbl { font-size: 0.65rem; color: #6b7280; margin-top: 2px; }
.growth-stat .g-trend { font-size: 0.72rem; font-weight: 700; margin-top: 2px; }
.growth-divider { border: none; border-top: 1px solid rgba(255,255,255,0.06); margin: 0.5rem 0; }
/* Journeys */
.journey-row { display: flex; align-items: center; gap: 0.4rem; padding: 0.3rem 0; border-bottom: 1px solid rgba(255,255,255,0.04); font-size: 0.75rem; }
.journey-row:last-child { border-bottom: none; }
.journey-from { color: #a78bfa; font-weight: 600; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 90px; flex-shrink: 0; }
.journey-arrow { color: #4b5563; flex-shrink: 0; }
.journey-to { color: #e5e7eb; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }
.journey-count { color: #6b7280; font-size: 0.68rem; flex-shrink: 0; margin-left: auto; }
/* Top Users */
.top-user-row { display: flex; align-items: center; gap: 0.6rem; padding: 0.35rem 0; border-bottom: 1px solid rgba(255,255,255,0.04); }
.top-user-row:last-child { border-bottom: none; }
.top-user-rank { font-size: 0.65rem; font-weight: 900; color: #4b5563; width: 14px; flex-shrink: 0; text-align: center; }
.top-user-name { font-size: 0.78rem; font-weight: 700; color: #e5e7eb; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.top-user-score-bar-wrap { height: 4px; background: rgba(255,255,255,0.07); border-radius: 2px; flex: 1; min-width: 30px; }
.top-user-score-bar { height: 4px; background: linear-gradient(90deg, #7c3aed, #a78bfa); border-radius: 2px; }
.top-user-meta { font-size: 0.65rem; color: #6b7280; flex-shrink: 0; }
@media (max-width: 900px) {
  .briefing-top, .briefing-bottom { grid-template-columns: 1fr; }
}
</style>

<div class="briefing-panel" id="briefing">
  <div class="briefing-header" onclick="this.parentElement.classList.toggle('collapsed')">
    <span><i class="bi bi-stars"></i> Today's Briefing</span>
    <span class="briefing-meta">Generated <?= date('H:i') ?></span>
    <i class="bi bi-chevron-down briefing-toggle"></i>
  </div>
  <div class="briefing-body">

    <!-- ── Top row: Situation + Action Items ── -->
    <div class="briefing-top">

      <!-- A. Situation Summary -->
      <div class="brief-card brief-situation">
        <div class="brief-card-title"><i class="bi bi-newspaper"></i> Situation Summary</div>
        <ul>
          <?php foreach ($sit_bullets as $bullet): ?>
          <li><?= $bullet ?></li>
          <?php endforeach; ?>
          <?php if (empty($sit_bullets)): ?>
          <li>No data available yet.</li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- B. Action Items -->
      <div class="brief-card brief-actions">
        <div class="brief-card-title"><i class="bi bi-lightning-charge"></i> Action Items</div>
        <?php if (empty($action_items)): ?>
        <div class="action-item" style="color:#34d399;">
          <div class="action-dot green"></div>
          <span>Nothing needs your attention right now.</span>
        </div>
        <?php else: ?>
          <?php foreach ($action_items as $ai): ?>
          <div class="action-item">
            <div class="action-dot <?= htmlspecialchars($ai['sev']) ?>"></div>
            <span><?= $ai['msg'] ?></span>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div><!-- /.briefing-top -->

    <!-- ── Bottom row: Growth | Journeys | Top Users ── -->
    <div class="briefing-bottom">

      <!-- C. Growth Narrative -->
      <div class="brief-card brief-growth">
        <div class="brief-card-title"><i class="bi bi-graph-up"></i> Growth</div>

        <?php
        $g_sess_pct = briefing_growth_pct($g_sessions_now, $g_sessions_prev);
        $g_usr_pct  = briefing_growth_pct($g_users_now,  $g_users_prev);
        $g_pv_pct   = briefing_growth_pct($g_pv_now,     $g_pv_prev);
        $g_sess_col = ($g_sess_pct === null || $g_sess_pct >= 0) ? '#34d399' : '#f87171';
        $g_usr_col  = ($g_usr_pct  === null || $g_usr_pct  >= 0) ? '#34d399' : '#f87171';
        $g_pv_col   = ($g_pv_pct   === null || $g_pv_pct   >= 0) ? '#34d399' : '#f87171';
        ?>

        <div class="growth-stat">
          <div class="g-val" style="color:<?= $g_sess_col ?>"><?= number_format($g_sessions_now) ?></div>
          <div class="g-lbl">Sessions</div>
          <div class="g-trend">vs last week <?= briefing_trend_html($g_sess_pct) ?></div>
        </div>
        <hr class="growth-divider">
        <div class="growth-stat">
          <div class="g-val" style="color:<?= $g_usr_col ?>"><?= number_format($g_users_now) ?></div>
          <div class="g-lbl">Unique Users</div>
          <div class="g-trend">vs prev 30 days <?= briefing_trend_html($g_usr_pct) ?></div>
        </div>
        <hr class="growth-divider">
        <div class="growth-stat">
          <div class="g-val" style="color:<?= $g_pv_col ?>"><?= number_format($g_pv_now) ?></div>
          <div class="g-lbl">Pageviews</div>
          <div class="g-trend">vs last month <?= briefing_trend_html($g_pv_pct) ?></div>
        </div>
      </div>

      <!-- D. User Journey Digest -->
      <div class="brief-card brief-journeys">
        <div class="brief-card-title"><i class="bi bi-diagram-3"></i> Top Journeys <span style="color:#4b5563;font-weight:400;font-size:0.62rem;text-transform:none;letter-spacing:0;">last 7 days</span></div>
        <?php if (empty($journeys)): ?>
        <div style="font-size:0.78rem;color:#4b5563;">No journey data yet.</div>
        <?php else: ?>
          <?php foreach ($journeys as $j): ?>
          <?php
            $from_short = function_exists('short_url') ? short_url($j['entry_url']) : htmlspecialchars($j['entry_url']);
            $to_short   = function_exists('short_url') ? short_url($j['exit_url'])  : htmlspecialchars($j['exit_url']);
            $is_bounce  = $j['entry_url'] === $j['exit_url'];
          ?>
          <div class="journey-row">
            <span class="journey-from" title="<?= htmlspecialchars($j['entry_url']) ?>"><?= $from_short ?></span>
            <span class="journey-arrow">→</span>
            <span class="journey-to" title="<?= htmlspecialchars($j['exit_url']) ?>"><?= $to_short ?><?php if ($is_bounce): ?> <span style="color:#4b5563;font-size:0.65rem;">(bounced)</span><?php endif; ?></span>
            <span class="journey-count"><?= number_format($j['cnt']) ?></span>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- E. Most Valuable Users -->
      <div class="brief-card brief-users">
        <div class="brief-card-title"><i class="bi bi-trophy"></i> Most Valuable Users <span style="color:#4b5563;font-weight:400;font-size:0.62rem;text-transform:none;letter-spacing:0;">last 30 days</span></div>
        <?php if (empty($top_users)): ?>
        <div style="font-size:0.78rem;color:#4b5563;">No logged-in user data yet.</div>
        <?php else: ?>
          <?php foreach ($top_users as $idx => $u):
            $score     = $u['sessions'] * 3 + $u['active_days'] * 2 + $u['total_events'] / 5;
            $bar_pct   = round($score / $top_user_max * 100);
            $name      = $u['display_name'] ?: ('User #' . $u['account_id']);
            $last_seen = briefing_time_ago($u['last_seen']);
          ?>
          <div class="top-user-row">
            <span class="top-user-rank"><?= $idx + 1 ?></span>
            <div style="flex:1;min-width:0;">
              <div style="display:flex;align-items:center;gap:0.4rem;margin-bottom:3px;">
                <span class="top-user-name" title="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></span>
                <span class="top-user-meta"><?= number_format($u['sessions']) ?> sess · <?= $u['active_days'] ?>d · <?= $last_seen ?></span>
              </div>
              <div class="top-user-score-bar-wrap">
                <div class="top-user-score-bar" style="width:<?= $bar_pct ?>%"></div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div><!-- /.briefing-bottom -->

  </div><!-- /.briefing-body -->
</div><!-- /.briefing-panel -->
