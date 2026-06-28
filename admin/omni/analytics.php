<?php
/**
 * omni/analytics.php
 * Core analytics include — runs all queries and outputs all shared HTML.
 *
 * Expects these variables already set by the calling page:
 *   $active_site, $apexcybernet_pdo, $date_range, $event_filter,
 *   $user_filter, $search, $page, $per_page, $page_file
 *
 * Also expects: $search_params, $search_cond, $date_cond, $prev_cond,
 *   $ev_cond, $uf_cond, $where already built by the calling page.
 */

// ── Site condition for activity_logs (apexcybernet_pdo / single DB) ──
if ($active_site === 'ocpd') {
    $site_cond = "AND site='ocpd'";
} elseif ($active_site === 'loan') {
    $site_cond = "AND site='loan'";
} else {
    $site_cond = "AND (site='apexcybernet' OR site IS NULL OR site='')";
}

// ── Current period stats ──
$s = [];
try {
    $st = $apexcybernet_pdo->query("SELECT
        COUNT(*)                                                          AS total,
        SUM(event_type='pageview')                                        AS pageviews,
        SUM(event_type='click')                                           AS clicks,
        COUNT(DISTINCT session_id)                                        AS sessions,
        COUNT(DISTINCT CASE WHEN account_id IS NOT NULL THEN account_id END) AS logged_in_users
        FROM activity_logs WHERE 1=1 $date_cond $uf_cond $site_cond");
    $s = $st->fetch() ?: [];
} catch (Exception $e) { $s = []; }

// ── Previous period stats ──
$sp = [];
try {
    $st = $apexcybernet_pdo->query("SELECT
        SUM(event_type='pageview')     AS pageviews,
        SUM(event_type='click')        AS clicks,
        COUNT(DISTINCT session_id)     AS sessions
        FROM activity_logs WHERE 1=1 $prev_cond $uf_cond $site_cond");
    $sp = $st->fetch() ?: [];
} catch (Exception $e) { $sp = []; }

// ── CTR & depth ──
$pv  = max(1, (int)($s['pageviews'] ?? 0));
$clk = (int)($s['clicks'] ?? 0);
$ses = max(1, (int)($s['sessions'] ?? 0));
$ctr       = $pv ? round($clk / $pv * 100, 1) : 0;
$avg_depth = round(($s['total'] ?? 0) / $ses, 1);

// ── Bounce rate (sessions with exactly 1 event) ──
$bounce_rate = 0;
try {
    $br = $apexcybernet_pdo->query("SELECT
        SUM(CASE WHEN ec=1 THEN 1 ELSE 0 END) AS bounced,
        COUNT(*) AS total_sess
        FROM (SELECT session_id, COUNT(*) AS ec FROM activity_logs WHERE 1=1 $date_cond $uf_cond $site_cond GROUP BY session_id) t")->fetch();
    $bounce_rate = $br['total_sess'] > 0 ? round($br['bounced'] / $br['total_sess'] * 100, 1) : 0;
} catch (Exception $e) {}

// ── Avg session duration ──
$avg_duration = '—';
try {
    $dur = $apexcybernet_pdo->query("SELECT AVG(TIMESTAMPDIFF(SECOND, first_ev, last_ev)) AS avg_sec
        FROM (SELECT session_id, MIN(created_at) AS first_ev, MAX(created_at) AS last_ev
              FROM activity_logs WHERE 1=1 $date_cond $uf_cond $site_cond
              GROUP BY session_id HAVING COUNT(*) > 1) t")->fetchColumn();
    if ($dur > 0) {
        $mins = floor($dur / 60);
        $secs = round($dur % 60);
        $avg_duration = $mins > 0 ? "{$mins}m {$secs}s" : "{$secs}s";
    }
} catch (Exception $e) {}

// ── Chart data ──
$chart_labels = [];
$chart_pv     = [];
$chart_cl     = [];
try {
    if ($date_range === 'today') {
        $rows_chart = $apexcybernet_pdo->query("SELECT HOUR(created_at) AS period,
            SUM(event_type='pageview') AS pv, SUM(event_type='click') AS cl
            FROM activity_logs WHERE created_at >= CURDATE() $uf_cond $site_cond
            GROUP BY HOUR(created_at) ORDER BY period ASC")->fetchAll();
        $map = [];
        foreach ($rows_chart as $r) $map[(int)$r['period']] = $r;
        for ($h = 0; $h <= 23; $h++) {
            $chart_labels[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
            $chart_pv[]     = (int)($map[$h]['pv'] ?? 0);
            $chart_cl[]     = (int)($map[$h]['cl'] ?? 0);
        }
    } else {
        $interval = $date_range === '30d' ? 30 : ($date_range === '7d' ? 7 : 90);
        $rows_chart = $apexcybernet_pdo->query("SELECT DATE(created_at) AS period,
            SUM(event_type='pageview') AS pv, SUM(event_type='click') AS cl
            FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL $interval DAY) $uf_cond $site_cond
            GROUP BY DATE(created_at) ORDER BY period ASC")->fetchAll();
        foreach ($rows_chart as $r) {
            $chart_labels[] = date('M j', strtotime($r['period']));
            $chart_pv[]     = (int)$r['pv'];
            $chart_cl[]     = (int)$r['cl'];
        }
    }
} catch (Exception $e) {}

// ── Hourly heatmap ──
$hourly_totals = array_fill(0, 24, 0);
try {
    $hrows = $apexcybernet_pdo->query("SELECT HOUR(created_at) AS h, COUNT(*) AS c
        FROM activity_logs WHERE created_at >= CURDATE() $uf_cond $site_cond
        GROUP BY h")->fetchAll();
    foreach ($hrows as $r) $hourly_totals[(int)$r['h']] = (int)$r['c'];
} catch (Exception $e) {}
$hourly_max = max(1, max($hourly_totals));

// ── Top referrers ──
$top_refs = [];
try {
    $tr = $apexcybernet_pdo->query("SELECT
        CASE WHEN referrer IS NULL OR referrer='' THEN 'Direct / None'
             ELSE REGEXP_REPLACE(referrer, '^https?://(www\\.)?([^/]+).*$', '\\\\2') END AS src,
        COUNT(*) AS hits
        FROM activity_logs WHERE event_type='pageview' $date_cond $uf_cond $site_cond
        GROUP BY src ORDER BY hits DESC LIMIT 8")->fetchAll();
    $top_refs = $tr;
} catch (Exception $e) {}

// ── Device breakdown ──
$devices = ['Mobile' => 0, 'Tablet' => 0, 'Desktop' => 0, 'Unknown' => 0];
try {
    $drows = $apexcybernet_pdo->query("SELECT screen_w, COUNT(DISTINCT session_id) AS cnt
        FROM activity_logs WHERE 1=1 $date_cond $uf_cond $site_cond AND screen_w IS NOT NULL
        GROUP BY screen_w")->fetchAll();
    foreach ($drows as $r) {
        $w = (int)$r['cnt'];
        $sw = (int)$r['screen_w'];
        if ($sw < 768)       $devices['Mobile']  += $w;
        elseif ($sw < 1024)  $devices['Tablet']  += $w;
        else                 $devices['Desktop'] += $w;
    }
    $unk = $apexcybernet_pdo->query("SELECT COUNT(DISTINCT session_id) FROM activity_logs
        WHERE 1=1 $date_cond $uf_cond $site_cond AND screen_w IS NULL")->fetchColumn();
    $devices['Unknown'] = (int)$unk;
} catch (Exception $e) {}
$device_total = max(1, array_sum($devices));

// ── New vs returning ──
$new_sessions = 0; $ret_sessions = 0;
try {
    if ($date_range !== 'all') {
        $cutoff = match($date_range) {
            'today' => "CURDATE()",
            '7d'    => "DATE_SUB(NOW(), INTERVAL 7 DAY)",
            '30d'   => "DATE_SUB(NOW(), INTERVAL 30 DAY)",
            default => "CURDATE()"
        };
        $nr = $apexcybernet_pdo->query("SELECT
            SUM(CASE WHEN first_seen >= $cutoff THEN 1 ELSE 0 END) AS new_s,
            SUM(CASE WHEN first_seen < $cutoff THEN 1 ELSE 0 END) AS ret_s
            FROM (
                SELECT session_id, MIN(created_at) AS first_seen
                FROM activity_logs WHERE 1=1 $date_cond $uf_cond $site_cond
                GROUP BY session_id
            ) AS sess")->fetch();
        $new_sessions = (int)($nr['new_s'] ?? 0);
        $ret_sessions = (int)($nr['ret_s'] ?? 0);
    }
} catch (Exception $e) {}

// ── Top pages ──
$top_pages = [];
try {
    $tp_st = $apexcybernet_pdo->prepare("SELECT page_url, COUNT(*) AS views FROM activity_logs
        WHERE event_type='pageview' $date_cond $uf_cond $site_cond $search_cond
        GROUP BY page_url ORDER BY views DESC LIMIT 10");
    $tp_st->execute($search_params);
    $top_pages = $tp_st->fetchAll();
} catch (Exception $e) {}

// ── Top clicked elements ──
$top_clicks = [];
try {
    $tc = $apexcybernet_pdo->query("SELECT
        COALESCE(NULLIF(element_text,''), element_id, element_tag, '?') AS label,
        element_href, COUNT(*) AS clicks
        FROM activity_logs WHERE event_type='click' $date_cond $uf_cond $site_cond
        GROUP BY label, element_href ORDER BY clicks DESC LIMIT 10")->fetchAll();
    $top_clicks = $tc;
} catch (Exception $e) {}

// ── Chat messages analytics ──
// Pulls from the real chat tables (cafe_comments = homepage live chat,
// chat_messages = DMs + groups) rather than activity_logs, since those tables
// never get mirrored into the tracker. Chat is inherently apexcybernet-scoped so
// the $site_cond filter is intentionally ignored here.
$chat_stats     = ['total' => 0, 'senders' => 0, 'conversations' => 0, 'avg_len' => 0];
$chat_top       = [];
$chat_recent    = [];
$chat_wordcloud = [];
try {
    // Unified pull — one query, compute in PHP
    $union_sql = "
        SELECT x.* FROM (
            SELECT id, account_id, display_name, message,
                   'live-chat' AS conv_key, 'live' AS kind,
                   created_at
            FROM cafe_comments
            WHERE 1=1 " . str_replace('created_at', 'created_at', $date_cond) . "
            UNION ALL
            SELECT m.id, m.sender_id AS account_id,
                   COALESCE(a.display_name, CONCAT('user#', m.sender_id)) AS display_name,
                   m.content AS message,
                   CASE
                     WHEN m.group_id IS NOT NULL THEN CONCAT('g-', m.group_id)
                     WHEN m.recipient_id IS NOT NULL THEN CONCAT('d-', LEAST(m.sender_id, m.recipient_id), '-', GREATEST(m.sender_id, m.recipient_id))
                     ELSE CONCAT('m-', m.id)
                   END AS conv_key,
                   CASE WHEN m.group_id IS NOT NULL THEN 'group' ELSE 'dm' END AS kind,
                   m.created_at
            FROM chat_messages m
            LEFT JOIN accounts a ON a.id = m.sender_id
            WHERE 1=1 " . str_replace('created_at', 'm.created_at', $date_cond) . "
        ) x
        ORDER BY x.created_at DESC
    ";
    $all = $apexcybernet_pdo->query($union_sql)->fetchAll(PDO::FETCH_ASSOC);

    $total      = count($all);
    $sender_set = [];
    $conv_set   = [];
    $len_sum    = 0;
    $len_count  = 0;
    $by_sender  = []; // account_id => [display_name, msgs, last_at]
    foreach ($all as $r) {
        $aid = (int)($r['account_id'] ?? 0);
        if ($aid) $sender_set[$aid] = true;
        if (!empty($r['conv_key'])) $conv_set[$r['conv_key']] = true;
        $msg = (string)($r['message'] ?? '');
        $len_sum += mb_strlen($msg);
        if ($msg !== '') $len_count++;
        $key = $aid ?: ('anon-' . ($r['display_name'] ?? ''));
        if (!isset($by_sender[$key])) {
            $by_sender[$key] = ['display_name' => $r['display_name'] ?? 'Anon', 'account_id' => $aid, 'msgs' => 0, 'last_at' => $r['created_at']];
        }
        $by_sender[$key]['msgs']++;
        if ($r['created_at'] > $by_sender[$key]['last_at']) $by_sender[$key]['last_at'] = $r['created_at'];
    }
    $chat_stats = [
        'total'         => $total,
        'senders'       => count($sender_set),
        'conversations' => count($conv_set),
        'avg_len'       => $len_count ? (int)round($len_sum / $len_count) : 0,
    ];
    usort($by_sender, function($a,$b){ return $b['msgs'] <=> $a['msgs']; });
    $chat_top    = array_slice(array_values($by_sender), 0, 10);
    $chat_recent = array_slice($all, 0, 25);

    $stop = ['the','a','an','to','of','in','is','it','you','me','my','your','we','us','are','and','or','but','for','on','at','be','this','that','not','will','so','do','did','have','has','had','as','with','if','from','by','up','out','just','can','get','like','what','when','where','how','why','hi','hey','yo','ok','okay','yes','no','lol','haha','pls','pa','na','mo','ba','ng','sa','ka','ko','si','nga','lang','yung','ito','yan','po','opo','wala','meron','may','din','rin','pero','kasi','talaga','lang','daw','raw'];
    $wc = [];
    $sample = array_slice($all, 0, 2000);
    foreach ($sample as $r) {
        $toks = preg_split('/[^a-zA-Z0-9]+/u', mb_strtolower((string)($r['message'] ?? '')));
        foreach ($toks as $t) {
            if (mb_strlen($t) < 3) continue;
            if (in_array($t, $stop, true)) continue;
            $wc[$t] = ($wc[$t] ?? 0) + 1;
        }
    }
    arsort($wc);
    $chat_wordcloud = array_slice($wc, 0, 20, true);
} catch (Exception $e) {}

// ── Top users ──
$top_users = [];
try {
    $tu_st = $apexcybernet_pdo->prepare("SELECT l.display_name, l.account_id, a.email,
        COUNT(*) AS events, MAX(l.created_at) AS last_seen
        FROM activity_logs l LEFT JOIN accounts a ON a.id = l.account_id
        WHERE l.account_id IS NOT NULL $date_cond $site_cond $search_cond
        GROUP BY l.account_id, l.display_name, a.email ORDER BY events DESC LIMIT 10");
    $tu_st->execute($search_params);
    $top_users = $tu_st->fetchAll();
} catch (Exception $e) {}

// ── Log rows ──
$where_with_site = "WHERE 1=1 $date_cond $ev_cond $uf_cond $site_cond $search_cond";
$count_sql = "SELECT COUNT(*) FROM activity_logs $where_with_site";
try {
    $cnt_st = $apexcybernet_pdo->prepare($count_sql);
    $cnt_st->execute($search_params);
    $total_rows = (int)$cnt_st->fetchColumn();
} catch (Exception $e) { $total_rows = 0; }
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page   = min($page, $total_pages);
$offset = ($page - 1) * $per_page;
$rows   = [];
try {
    $data_st = $apexcybernet_pdo->prepare("SELECT id, session_id, account_id, display_name, event_type,
        page_url, page_title, element_tag, element_text, element_href, element_id,
        referrer, ip, screen_w, created_at,
        country, city, browser, os, device_type, scroll_depth, time_on_page, load_time
        FROM activity_logs $where_with_site ORDER BY id DESC LIMIT $per_page OFFSET $offset");
    $data_st->execute($search_params);
    $rows = $data_st->fetchAll();
} catch (Exception $e) { $rows = []; }

// ── Max log ID for live feed baseline ──
$max_id = 0;
try { $max_id = (int)$apexcybernet_pdo->query("SELECT MAX(id) FROM activity_logs")->fetchColumn(); } catch(Exception $e){}

// ── Live now (active in last 5 min) ──
$live_now = 0;
try { $live_now = (int)$apexcybernet_pdo->query("SELECT COUNT(DISTINCT session_id) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) $site_cond")->fetchColumn(); } catch(Exception $e){}

// ── Avg scroll depth ──
$avg_scroll = null;
try { $r = $apexcybernet_pdo->query("SELECT ROUND(AVG(scroll_depth)) FROM activity_logs WHERE event_type='scroll' $date_cond $site_cond")->fetchColumn(); $avg_scroll = ($r !== false && $r !== null) ? (int)$r : null; } catch(Exception $e){}

// ── Avg load time (ms) ──
$avg_load_time = null;
try { $r = $apexcybernet_pdo->query("SELECT ROUND(AVG(load_time)) FROM activity_logs WHERE event_type='pageview' AND load_time IS NOT NULL AND load_time > 0 $date_cond $site_cond")->fetchColumn(); $avg_load_time = ($r !== false && $r !== null) ? (int)$r : null; } catch(Exception $e){}

// ── Top countries ──
$top_countries = [];
try {
    $top_countries = $apexcybernet_pdo->query("SELECT country, COUNT(DISTINCT session_id) AS sessions
        FROM activity_logs WHERE country IS NOT NULL $date_cond $uf_cond $site_cond
        GROUP BY country ORDER BY sessions DESC LIMIT 12")->fetchAll();
} catch(Exception $e){}

// ── Top browsers ──
$top_browsers = [];
try {
    $top_browsers = $apexcybernet_pdo->query("SELECT browser, COUNT(DISTINCT session_id) AS sessions
        FROM activity_logs WHERE browser IS NOT NULL AND browser NOT IN ('Bot','Unknown') $date_cond $uf_cond $site_cond
        GROUP BY browser ORDER BY sessions DESC LIMIT 8")->fetchAll();
} catch(Exception $e){}

// ── OS breakdown ──
$top_os = [];
try {
    $top_os = $apexcybernet_pdo->query("SELECT os, COUNT(DISTINCT session_id) AS sessions
        FROM activity_logs WHERE os IS NOT NULL AND os NOT IN ('Bot','Unknown') $date_cond $uf_cond $site_cond
        GROUP BY os ORDER BY sessions DESC LIMIT 6")->fetchAll();
} catch(Exception $e){}

// ── UTM sources ──
$top_utms = [];
try {
    $top_utms = $apexcybernet_pdo->query("SELECT utm_source, utm_medium, utm_campaign, COUNT(DISTINCT session_id) AS sessions
        FROM activity_logs WHERE utm_source IS NOT NULL $date_cond $site_cond
        GROUP BY utm_source, utm_medium, utm_campaign ORDER BY sessions DESC LIMIT 10")->fetchAll();
} catch(Exception $e){}

// ── Scroll depth per page ──
$scroll_pages = [];
try {
    $scroll_pages = $apexcybernet_pdo->query("SELECT page_url, ROUND(AVG(scroll_depth)) AS avg_depth, COUNT(*) AS cnt
        FROM activity_logs WHERE event_type='scroll' $date_cond $site_cond
        GROUP BY page_url ORDER BY cnt DESC LIMIT 8")->fetchAll();
} catch(Exception $e){}

// ── Recent JS errors ──
$js_errors = [];
try {
    $js_errors = $apexcybernet_pdo->query("SELECT element_text AS msg, COUNT(*) AS cnt, MAX(created_at) AS last_seen, page_url
        FROM activity_logs WHERE event_type='error' $date_cond $site_cond
        GROUP BY element_text, page_url ORDER BY cnt DESC LIMIT 10")->fetchAll();
} catch(Exception $e){}

// ── Auto-create chart_annotations table ──
try {
    $apexcybernet_pdo->exec("CREATE TABLE IF NOT EXISTS chart_annotations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        site VARCHAR(32) NOT NULL,
        annotation_date DATE NOT NULL,
        label VARCHAR(120) NOT NULL,
        color VARCHAR(20) DEFAULT '#fbbf24',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (site, annotation_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// ── Auto-create goals table ──
try {
    $apexcybernet_pdo->exec("CREATE TABLE IF NOT EXISTS goals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        site VARCHAR(32) NOT NULL,
        name VARCHAR(120) NOT NULL,
        url_pattern VARCHAR(255) NOT NULL,
        goal_type VARCHAR(32) DEFAULT 'pageview',
        active TINYINT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// ── Auto-create alert_rules table ──
try {
    $apexcybernet_pdo->exec("CREATE TABLE IF NOT EXISTS alert_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        site VARCHAR(32) NOT NULL,
        alert_type VARCHAR(32) NOT NULL,
        name VARCHAR(120) DEFAULT NULL,
        threshold_pct INT DEFAULT 50,
        window_minutes INT DEFAULT 30,
        active TINYINT DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// ── Handle add_alert_rule POST ──
if (isset($_POST['action']) && $_POST['action'] === 'add_alert_rule') {
    $ar_name    = trim($_POST['rule_name'] ?? '');
    $ar_type    = trim($_POST['alert_type'] ?? 'traffic_drop');
    $ar_site    = trim($_POST['rule_site'] ?? $active_site);
    $ar_thresh  = max(1, (int)($_POST['threshold_pct'] ?? 50));
    $ar_window  = max(1, (int)($_POST['window_minutes'] ?? 30));
    $allowed_types = ['traffic_drop','error_spike','no_traffic'];
    if (!in_array($ar_type, $allowed_types)) $ar_type = 'traffic_drop';
    try {
        $st = $apexcybernet_pdo->prepare("INSERT INTO alert_rules (site, alert_type, name, threshold_pct, window_minutes, active) VALUES (?,?,?,?,?,1)");
        $st->execute([$ar_site, $ar_type, $ar_name ?: null, $ar_thresh, $ar_window]);
    } catch (Exception $e) {}
    header('Location: ' . $page_file . '?dr=' . $date_range . '#pal-alerts'); exit;
}

// ── Handle del_alert_rule GET ──
if (isset($_GET['action']) && $_GET['action'] === 'del_alert_rule' && isset($_GET['id'])) {
    $del_id = (int)$_GET['id'];
    try { $apexcybernet_pdo->prepare("DELETE FROM alert_rules WHERE id = ?")->execute([$del_id]); } catch (Exception $e) {}
    header('Location: ' . $page_file . '?dr=' . $date_range . '#pal-alerts'); exit;
}

// ── Toggle alert rule ──
if (isset($_GET['toggle_alert'])) {
    $rule_id = (int)$_GET['toggle_alert'];
    try { $apexcybernet_pdo->prepare("UPDATE alert_rules SET active = 1 - active WHERE id = ?")->execute([$rule_id]); } catch (Exception $e) {}
    header('Location: ' . $page_file . '?dr=' . $date_range . '#pal-alerts'); exit;
}

// ── Goals: handle add/del actions ──
if (isset($_POST['action']) && $_POST['action'] === 'add_goal') {
    $g_name    = trim($_POST['goal_name'] ?? '');
    $g_pattern = trim($_POST['goal_pattern'] ?? '');
    $g_site    = trim($_POST['goal_site'] ?? $active_site);
    if ($g_name !== '' && $g_pattern !== '') {
        try {
            $st = $apexcybernet_pdo->prepare("INSERT INTO goals (site, name, url_pattern) VALUES (?,?,?)");
            $st->execute([$g_site, $g_name, $g_pattern]);
        } catch (Exception $e) {}
    }
    header('Location: ' . $page_file . '?dr=' . $date_range . '#pal-goals'); exit;
}
if (isset($_GET['action']) && $_GET['action'] === 'del_goal' && isset($_GET['id'])) {
    $del_id = (int)$_GET['id'];
    try { $apexcybernet_pdo->prepare("DELETE FROM goals WHERE id = ?")->execute([$del_id]); } catch (Exception $e) {}
    header('Location: ' . $page_file . '?dr=' . $date_range . '#pal-goals'); exit;
}

// ── Goals: query ──
$goals_list = [];
try {
    $st = $apexcybernet_pdo->prepare("SELECT * FROM goals WHERE site = ? ORDER BY id");
    $st->execute([$active_site]);
    $goals_list = $st->fetchAll();
} catch (Exception $e) {}

$goals_data = [];
$total_goal_completions = 0;
foreach ($goals_list as $g) {
    $g_pat = '%' . str_replace(['%','_'], ['\%','\_'], $g['url_pattern']) . '%';
    $g_completions = 0;
    try {
        $st = $apexcybernet_pdo->prepare("SELECT COUNT(DISTINCT session_id) FROM activity_logs
            WHERE event_type='pageview' AND page_url LIKE ? $date_cond $site_cond");
        $st->execute([$g_pat]);
        $g_completions = (int)$st->fetchColumn();
    } catch (Exception $e) {}
    $g_rate = $ses > 0 ? round($g_completions / $ses * 100, 1) : 0;
    $goals_data[] = [
        'id'          => $g['id'],
        'name'        => $g['name'],
        'url_pattern' => $g['url_pattern'],
        'completions' => $g_completions,
        'conv_rate'   => $g_rate,
    ];
    $total_goal_completions += $g_completions;
}
$total_conv_rate = $ses > 0 ? round($total_goal_completions / $ses * 100, 1) : 0;

// ── Annotations: handle add/del actions ──
if (isset($_POST['action']) && $_POST['action'] === 'add_annotation') {
    $ann_date  = trim($_POST['ann_date'] ?? '');
    $ann_label = trim($_POST['ann_label'] ?? '');
    $ann_color = trim($_POST['ann_color'] ?? '#fbbf24');
    $allowed_ann_colors = ['#fbbf24','#60a5fa','#34d399','#f87171'];
    if (!in_array($ann_color, $allowed_ann_colors)) $ann_color = '#fbbf24';
    if ($ann_date !== '' && $ann_label !== '') {
        try {
            $st = $apexcybernet_pdo->prepare("INSERT INTO chart_annotations (site, annotation_date, label, color) VALUES (?,?,?,?)");
            $st->execute([$active_site, $ann_date, $ann_label, $ann_color]);
        } catch (Exception $e) {}
    }
    header('Location: ' . $page_file . '?dr=' . $date_range . '#annotations'); exit;
}
if (isset($_GET['action']) && $_GET['action'] === 'del_annotation' && isset($_GET['id'])) {
    $del_id = (int)$_GET['id'];
    try { $apexcybernet_pdo->prepare("DELETE FROM chart_annotations WHERE id = ? AND site = ?")->execute([$del_id, $active_site]); } catch (Exception $e) {}
    header('Location: ' . $page_file . '?dr=' . $date_range . '#annotations'); exit;
}

// ── Annotations: fetch for chart ──
$annotations = [];
try {
    $ann_from = match($date_range) {
        'today' => date('Y-m-d'),
        '7d'    => date('Y-m-d', strtotime('-7 days')),
        '30d'   => date('Y-m-d', strtotime('-30 days')),
        '90d'   => date('Y-m-d', strtotime('-90 days')),
        default => '2000-01-01',
    };
    $st = $apexcybernet_pdo->prepare("SELECT * FROM chart_annotations WHERE site = ? AND annotation_date >= ? ORDER BY annotation_date ASC");
    $st->execute([$active_site, $ann_from]);
    $annotations = $st->fetchAll();
} catch (Exception $e) {}
$annotations_json = json_encode(array_map(fn($a) => [
    'date'  => $a['annotation_date'],
    'label' => $a['label'],
    'color' => $a['color'],
], $annotations));

// ── Search queries ──
$top_searches = [];
try {
    $top_searches = $apexcybernet_pdo->query("SELECT element_text AS query, COUNT(*) AS cnt
        FROM activity_logs
        WHERE event_type='search' $date_cond $site_cond AND element_text IS NOT NULL
        GROUP BY element_text ORDER BY cnt DESC LIMIT 20")->fetchAll();
} catch (Exception $e) {}

// ── Entry & Exit pages ──
$entry_pages = [];
try {
    $entry_pages = $apexcybernet_pdo->query("SELECT page_url, COUNT(*) AS entries
        FROM activity_logs WHERE id IN (
            SELECT MIN(id) FROM activity_logs WHERE event_type='pageview' $date_cond $site_cond GROUP BY session_id
        ) GROUP BY page_url ORDER BY entries DESC LIMIT 10")->fetchAll();
} catch (Exception $e) {}

$exit_pages = [];
try {
    $exit_pages = $apexcybernet_pdo->query("SELECT page_url, COUNT(*) AS exits
        FROM activity_logs WHERE id IN (
            SELECT MAX(id) FROM activity_logs WHERE event_type='pageview' $date_cond $site_cond GROUP BY session_id
        ) GROUP BY page_url ORDER BY exits DESC LIMIT 10")->fetchAll();
} catch (Exception $e) {}

// ── Click heatmap ──
$click_heatmap = [];
try {
    $pages_with_clicks = $apexcybernet_pdo->query("SELECT page_url, COUNT(*) AS total_clicks
        FROM activity_logs WHERE event_type='click' $date_cond $site_cond
        GROUP BY page_url ORDER BY total_clicks DESC LIMIT 8")->fetchAll();
    foreach ($pages_with_clicks as $pwc) {
        $st = $apexcybernet_pdo->prepare("SELECT COALESCE(NULLIF(element_text,''), element_id, element_tag, '?') AS label,
            COUNT(*) AS cnt FROM activity_logs
            WHERE event_type='click' AND page_url=? $date_cond $site_cond
            GROUP BY label ORDER BY cnt DESC LIMIT 3");
        $st->execute([$pwc['page_url']]);
        $click_heatmap[] = ['page' => $pwc['page_url'], 'total' => $pwc['total_clicks'], 'elements' => $st->fetchAll()];
    }
} catch (Exception $e) {}

// ── 404 / error pages ──
$pages_404 = [];
try {
    $pages_404 = $apexcybernet_pdo->query("SELECT page_url, page_title, COUNT(*) AS hits, COUNT(DISTINCT session_id) AS sessions, MAX(created_at) AS last_seen
        FROM activity_logs
        WHERE event_type='pageview' $date_cond $site_cond
        AND (page_title LIKE '%404%' OR page_title LIKE '%not found%' OR page_title LIKE '%Not Found%' OR page_url LIKE '%404%')
        GROUP BY page_url, page_title ORDER BY hits DESC LIMIT 15")->fetchAll();
} catch (Exception $e) {}

// ── PALANTIR: Identity Graph ──
$identity_stats  = ['total' => 0, 'known' => 0, 'anon' => 0];
$top_users_graph = [];
$cross_site_users = [];
try {
    $ig_site = $apexcybernet_pdo->prepare("SELECT
        COUNT(*) as total,
        SUM(uid IS NOT NULL) as known,
        SUM(uid IS NULL) as anon
        FROM user_graph WHERE site = ? AND last_seen > DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $ig_site->execute([$active_site]);
    $identity_stats = $ig_site->fetch() ?: $identity_stats;

    if ($active_site === 'apexcybernet') {
        $tug = $apexcybernet_pdo->prepare("SELECT g.uid, g.display_name,
            COUNT(DISTINCT g.session_id) as sessions,
            SUM(g.page_count) as pages,
            g.country, g.device_type, g.browser,
            MAX(g.last_seen) as last_seen,
            a.email,
            CASE WHEN a.is_merchant = 1 THEN 'merchant' ELSE 'user' END as role
            FROM user_graph g
            LEFT JOIN accounts a ON a.id = g.uid
            WHERE g.site = ? AND g.uid IS NOT NULL AND g.last_seen > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY g.uid, g.display_name, g.country, g.device_type, g.browser, a.email, a.is_merchant
            ORDER BY sessions DESC LIMIT 10");
        $tug->execute([$active_site]);
        $top_users_graph = $tug->fetchAll();
    } else {
        $tug = $apexcybernet_pdo->prepare("SELECT uid, display_name,
            COUNT(DISTINCT session_id) as sessions,
            SUM(page_count) as pages,
            country, device_type, browser,
            MAX(last_seen) as last_seen
            FROM user_graph
            WHERE site = ? AND uid IS NOT NULL AND last_seen > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY uid, display_name, country, device_type, browser
            ORDER BY sessions DESC LIMIT 10");
        $tug->execute([$active_site]);
        $top_users_graph = $tug->fetchAll();
    }

    $cross_site_users = $apexcybernet_pdo->query("SELECT uid, display_name,
        COUNT(DISTINCT site) as site_count,
        GROUP_CONCAT(DISTINCT site ORDER BY site SEPARATOR ',') as sites,
        SUM(page_count) as total_pages
        FROM user_graph WHERE uid IS NOT NULL
        GROUP BY uid, display_name
        HAVING site_count > 1
        ORDER BY total_pages DESC LIMIT 5")->fetchAll();
} catch (Exception $e) {}

// ── PALANTIR: Funnels ──
$funnels_data = [];
try {
    $funnel_rows = $apexcybernet_pdo->prepare("SELECT * FROM funnels WHERE site = ? AND active = 1 ORDER BY id");
    $funnel_rows->execute([$active_site]);
    foreach ($funnel_rows->fetchAll() as $f) {
        $steps = json_decode($f['steps'], true) ?: [];
        $step_counts = [];
        foreach ($steps as $step) {
            $pat = '%' . str_replace(['%','_'], ['\%','\_'], $step['pattern']) . '%';
            try {
                $sc = $apexcybernet_pdo->prepare("SELECT COUNT(DISTINCT session_id) FROM activity_logs
                    WHERE event_type='pageview' AND page_url LIKE ? $date_cond $site_cond");
                $sc->execute([$pat]);
                $step_counts[] = ['label' => $step['label'], 'pattern' => $step['pattern'], 'count' => (int)$sc->fetchColumn()];
            } catch (Exception $e) {
                $step_counts[] = ['label' => $step['label'], 'pattern' => $step['pattern'], 'count' => 0];
            }
        }
        $funnels_data[] = ['id' => $f['id'], 'name' => $f['name'], 'steps' => $step_counts];
    }
} catch (Exception $e) {}

// ── PALANTIR: Segment Explorer ──
$seg_country  = trim($_GET['seg_country'] ?? '');
$seg_device   = trim($_GET['seg_device']  ?? '');
$seg_utm      = trim($_GET['seg_utm']     ?? '');
$seg_pages    = max(0, (int)($_GET['seg_pages'] ?? 0));
$seg_results  = [];
$seg_countries_list = [];
try {
    $scl = $apexcybernet_pdo->prepare("SELECT DISTINCT country FROM user_graph WHERE site=? AND country IS NOT NULL ORDER BY country");
    $scl->execute([$active_site]);
    $seg_countries_list = $scl->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
if ($seg_country || $seg_device || $seg_utm || $seg_pages > 0) {
    $sw = ['site = ?']; $sw_params = [$active_site];
    if ($seg_country) { $sw[] = 'country = ?';       $sw_params[] = $seg_country; }
    if ($seg_device)  { $sw[] = 'device_type = ?';   $sw_params[] = $seg_device; }
    if ($seg_utm)     { $sw[] = 'utm_source LIKE ?';  $sw_params[] = '%' . $seg_utm . '%'; }
    if ($seg_pages)   { $sw[] = 'page_count >= ?';    $sw_params[] = $seg_pages; }
    try {
        $st = $apexcybernet_pdo->prepare("SELECT session_id, uid, display_name, country, city,
            device_type, browser, utm_source, utm_campaign, page_count, event_count, first_seen, last_seen
            FROM user_graph WHERE " . implode(' AND ', $sw) . " ORDER BY last_seen DESC LIMIT 50");
        $st->execute($sw_params);
        $seg_results = $st->fetchAll();
    } catch (Exception $e) {}
}

// ── PALANTIR: Alerts ──
$alert_rules_list = [];
$recent_alert_log = [];
try {
    $alert_rules_list = $apexcybernet_pdo->query("SELECT * FROM alert_rules ORDER BY active DESC, site, alert_type")->fetchAll();
    $recent_alert_log = $apexcybernet_pdo->query("SELECT * FROM alert_log ORDER BY fired_at DESC LIMIT 8")->fetchAll();
} catch (Exception $e) {}

$export_qs = http_build_query(array_filter([
    'export' => 'csv',
    'dr'     => $date_range,
    'ev'     => $event_filter !== 'all' ? $event_filter : null,
    'uf'     => $user_filter !== 'all' ? $user_filter : null,
    'q'      => $search ?: null,
]));

// ════════════════════════════════════════════════════════
// HTML OUTPUT STARTS HERE
// ════════════════════════════════════════════════════════
?>

<!-- ══ TOPBAR ══ -->
<div class="topbar">
    <a href="<?= base_url('admin/') ?>" class="btn-back-site" style="border-color:rgba(107,114,128,0.35); color:#9ca3af; font-size:0.75rem; padding:0.3rem 0.65rem; text-decoration:none; display:inline-flex; align-items:center; gap:0.3rem;"><i class="bi bi-arrow-left"></i> Admin</a>
    <h1 style="font-size:0.95rem;">
        <?php if ($active_site==='ocpd'): ?>
        <span style="color:#38bdf8;"><i class="bi bi-graph-up-arrow"></i> OCPD Analytics</span>
        <?php elseif ($active_site==='loan'): ?>
        <span style="color:#c4b5fd;"><i class="bi bi-graph-up-arrow"></i> Loan Analytics</span>
        <?php elseif ($active_site==='alrisha'): ?>
        <span style="color:#34d399;"><i class="bi bi-graph-up-arrow"></i> Alrisha ERP</span>
        <?php else: ?>
        <span style="color:#a78bfa;"><i class="bi bi-graph-up-arrow"></i> Apex Cybernet Analytics</span>
        <?php endif; ?>
    </h1>
    <?php if ($live_now > 0): ?>
    <span class="live-now-badge"><span class="live-dot-sm"></span><?= $live_now ?> live now</span>
    <?php endif; ?>
    <span style="margin-left:auto; font-size:0.72rem; color:#6b7280;" id="lastRefresh"></span>
    <div class="auto-refresh-wrap">
        <input type="checkbox" id="autoRefresh"> <label for="autoRefresh">Auto-refresh (30s)</label>
    </div>
</div>

<div class="wrap">

<?php require __DIR__ . '/insights.php'; ?>

<!-- Date tabs -->
<div class="date-tabs">
    <?php foreach (['today'=>'Today','7d'=>'Last 7 days','30d'=>'Last 30 days','all'=>'All time'] as $v=>$l): ?>
    <a href="<?= $page_file ?>?dr=<?= $v ?>&ev=<?= $event_filter ?>&uf=<?= $user_filter ?>&q=<?= urlencode($search) ?>"
       class="date-tab <?= $date_range===$v?'active':'' ?>"><?= $l ?></a>
    <?php endforeach; ?>
</div>

<!-- KPI cards -->
<div class="kpi-grid">
    <div class="kpi" style="--kpi-color:var(--blue); --kpi-glow:radial-gradient(ellipse at 0% 0%, rgba(96,165,250,0.07) 0%, transparent 70%);">
        <div class="kpi-label"><i class="bi bi-eye"></i> Pageviews</div>
        <div class="kpi-val"><?= number_format($s['pageviews'] ?? 0) ?></div>
        <div class="kpi-sub">vs prev period <?= trend_badge($s['pageviews']??0, $sp['pageviews']??0) ?></div>
    </div>
    <div class="kpi" style="--kpi-color:var(--accent-light); --kpi-glow:radial-gradient(ellipse at 0% 0%, rgba(167,139,250,0.07) 0%, transparent 70%);">
        <div class="kpi-label"><i class="bi bi-cursor"></i> Clicks</div>
        <div class="kpi-val"><?= number_format($s['clicks'] ?? 0) ?></div>
        <div class="kpi-sub">vs prev period <?= trend_badge($s['clicks']??0, $sp['clicks']??0) ?></div>
    </div>
    <div class="kpi" style="--kpi-color:var(--green); --kpi-glow:radial-gradient(ellipse at 0% 0%, rgba(52,211,153,0.07) 0%, transparent 70%);">
        <div class="kpi-label"><i class="bi bi-people"></i> Sessions</div>
        <div class="kpi-val"><?= number_format($s['sessions'] ?? 0) ?></div>
        <div class="kpi-sub">vs prev period <?= trend_badge($s['sessions']??0, $sp['sessions']??0) ?></div>
    </div>
    <div class="kpi" style="--kpi-color:var(--yellow); --kpi-glow:radial-gradient(ellipse at 0% 0%, rgba(251,191,36,0.07) 0%, transparent 70%);">
        <div class="kpi-label"><i class="bi bi-person-check"></i> Logged-in Users</div>
        <div class="kpi-val"><?= number_format($s['logged_in_users'] ?? 0) ?></div>
        <div class="kpi-sub">unique accounts</div>
    </div>
    <div class="kpi" style="--kpi-color:var(--orange); --kpi-glow:radial-gradient(ellipse at 0% 0%, rgba(251,146,60,0.07) 0%, transparent 70%);">
        <div class="kpi-label"><i class="bi bi-percent"></i> CTR</div>
        <div class="kpi-val"><?= $ctr ?>%</div>
        <div class="kpi-sub">clicks ÷ pageviews</div>
    </div>
    <div class="kpi" style="--kpi-color:var(--red); --kpi-glow:radial-gradient(ellipse at 0% 0%, rgba(248,113,113,0.07) 0%, transparent 70%);">
        <div class="kpi-label"><i class="bi bi-box-arrow-left"></i> Bounce Rate</div>
        <div class="kpi-val"><?= $bounce_rate ?>%</div>
        <div class="kpi-sub">1-event sessions</div>
    </div>
    <div class="kpi" style="--kpi-color:#e5e7eb;">
        <div class="kpi-label"><i class="bi bi-clock-history"></i> Avg Duration</div>
        <div class="kpi-val" style="font-size:1.5rem;"><?= $avg_duration ?></div>
        <div class="kpi-sub">multi-event sessions</div>
    </div>
    <div class="kpi" style="--kpi-color:#e5e7eb;">
        <div class="kpi-label"><i class="bi bi-layers"></i> Avg Depth</div>
        <div class="kpi-val"><?= $avg_depth ?></div>
        <div class="kpi-sub">events per session</div>
    </div>
    <div class="kpi" style="--kpi-color:var(--green); --kpi-glow:radial-gradient(ellipse at 0% 0%, rgba(52,211,153,0.07) 0%, transparent 70%);">
        <div class="kpi-label"><i class="bi bi-arrow-down-square"></i> Avg Scroll</div>
        <div class="kpi-val"><?= $avg_scroll !== null ? $avg_scroll . '%' : '—' ?></div>
        <div class="kpi-sub">page read depth</div>
    </div>
    <div class="kpi" style="--kpi-color:var(--blue);">
        <div class="kpi-label"><i class="bi bi-lightning"></i> Avg Load</div>
        <div class="kpi-val" style="font-size:1.5rem;"><?= $avg_load_time !== null ? ($avg_load_time >= 1000 ? round($avg_load_time/1000,1).'s' : $avg_load_time.'ms') : '—' ?></div>
        <div class="kpi-sub">page load time</div>
    </div>
    <div class="kpi" style="--kpi-color:var(--green); --kpi-glow:radial-gradient(ellipse at 0% 0%, rgba(52,211,153,0.08) 0%, transparent 70%);">
        <div class="kpi-label"><i class="bi bi-bullseye"></i> Goal Completions</div>
        <div class="kpi-val" style="color:#34d399;"><?= number_format($total_goal_completions) ?></div>
        <div class="kpi-sub">conv. rate <?= $total_conv_rate ?>% of sessions</div>
    </div>
</div>

<!-- Chart -->
<div class="chart-card">
    <div class="card-title">
        <span><i class="bi bi-bar-chart-line" style="color:var(--accent-light)"></i> &nbsp;Pageviews &amp; Clicks Over Time</span>
        <span style="font-size:0.7rem; color:#6b7280;"><?= ['today'=>'Hourly','7d'=>'Daily (7d)','30d'=>'Daily (30d)','all'=>'Daily (all)'][$date_range] ?></span>
    </div>
    <div class="chart-wrap">
        <canvas id="mainChart"></canvas>
    </div>
</div>

<?php require __DIR__ . '/panels/annotations.php'; ?>

<!-- Insights row -->
<div class="insights-row">

    <!-- Hourly heatmap -->
    <div class="ins-card" style="grid-column: span 2;">
        <div class="ins-title"><i class="bi bi-clock"></i> Activity by Hour (Today)</div>
        <div class="heatmap">
            <?php foreach ($hourly_totals as $h => $cnt):
                $intensity = $hourly_max > 0 ? $cnt / $hourly_max : 0;
                $alpha = round(0.08 + $intensity * 0.82, 2);
                $bg = $cnt > 0 ? "rgba(124,58,237,{$alpha})" : 'rgba(255,255,255,0.04)';
            ?>
            <div>
                <div class="hm-cell" style="background:<?= $bg ?>;" data-tip="<?= str_pad($h,2,'0',STR_PAD_LEFT) ?>:00 — <?= $cnt ?> events"></div>
                <div class="hm-hour"><?= $h % 3 === 0 ? str_pad($h,2,'0',STR_PAD_LEFT) : '' ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Device breakdown -->
    <div class="ins-card">
        <div class="ins-title"><i class="bi bi-display"></i> Device Type</div>
        <?php $dcolors = ['Mobile'=>'#60a5fa','Tablet'=>'#a78bfa','Desktop'=>'#34d399','Unknown'=>'#4b5563']; ?>
        <div class="dev-bar">
            <?php foreach ($devices as $dtype => $dcnt): if (!$dcnt) continue; ?>
            <div class="dev-seg" style="width:<?= round($dcnt/$device_total*100,1) ?>%; background:<?= $dcolors[$dtype] ?>;"></div>
            <?php endforeach; ?>
        </div>
        <div class="dev-legend">
            <?php foreach ($devices as $dtype => $dcnt): if (!$dcnt) continue; ?>
            <div class="dev-item">
                <span class="dev-dot" style="background:<?= $dcolors[$dtype] ?>"></span>
                <?= $dtype ?> <strong style="color:#e5e7eb; margin-left:4px;"><?= round($dcnt/$device_total*100) ?>%</strong>
                <span style="margin-left:4px; color:#4b5563;">(<?= $dcnt ?>)</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- New vs Returning -->
    <div class="ins-card">
        <div class="ins-title"><i class="bi bi-arrow-repeat"></i> New vs Returning</div>
        <?php $nr_total = max(1, $new_sessions + $ret_sessions); ?>
        <div class="donut-wrap">
            <canvas id="nrChart" width="90" height="90" style="flex-shrink:0;"></canvas>
            <div class="donut-legend">
                <div class="donut-item"><span class="dev-dot" style="background:#34d399"></span> New &nbsp;<strong><?= $new_sessions ?></strong> <span style="margin-left:4px;">(<?= round($new_sessions/$nr_total*100) ?>%)</span></div>
                <div class="donut-item"><span class="dev-dot" style="background:#a78bfa"></span> Returning &nbsp;<strong><?= $ret_sessions ?></strong> <span style="margin-left:4px;">(<?= round($ret_sessions/$nr_total*100) ?>%)</span></div>
            </div>
        </div>
    </div>

    <!-- Top Referrers -->
    <div class="ins-card" style="grid-column: span 2;">
        <div class="ins-title"><i class="bi bi-link-45deg"></i> Traffic Sources</div>
        <?php $ref_max = max(1, (int)($top_refs[0]['hits'] ?? 1)); ?>
        <?php foreach ($top_refs as $ref): $pct = round($ref['hits'] / $ref_max * 100); ?>
        <div class="ref-item">
            <span class="ref-name" title="<?= htmlspecialchars($ref['src']) ?>"><?= htmlspecialchars($ref['src']) ?></span>
            <div class="ref-bar-wrap"><div class="ref-bar" style="width:<?= $pct ?>%;"></div></div>
            <span class="ref-count"><?= $ref['hits'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($top_refs)): ?><div style="color:#6b7280; font-size:0.78rem; padding: 0.5rem 0;">No referrer data yet.</div><?php endif; ?>
    </div>

    <!-- Top Clicked Elements -->
    <div class="ins-card" style="grid-column: span 2;">
        <div class="ins-title"><i class="bi bi-hand-index"></i> Most Clicked Elements</div>
        <?php if (empty($top_clicks)): ?>
        <div style="color:#6b7280; font-size:0.78rem; padding: 0.5rem 0;">No click data yet.</div>
        <?php else: $clk_max = max(1, (int)($top_clicks[0]['clicks'] ?? 1)); ?>
        <?php foreach ($top_clicks as $tc): $pct = round($tc['clicks'] / $clk_max * 100); ?>
        <div class="ref-item">
            <span class="ref-name" title="<?= htmlspecialchars($tc['label'].'  →  '.$tc['element_href']) ?>">
                <?= htmlspecialchars(mb_strimwidth($tc['label'], 0, 35, '…')) ?>
                <?php if ($tc['element_href']): ?>
                <span style="color:#6b7280; font-size:0.65rem; margin-left:4px;">→ <?= htmlspecialchars(short_url($tc['element_href'])) ?></span>
                <?php endif; ?>
            </span>
            <div class="ref-bar-wrap"><div class="ref-bar" style="width:<?= $pct ?>%; background:var(--accent-light);"></div></div>
            <span class="ref-count"><?= $tc['clicks'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- ── OMNISCIENT row: Countries · Browsers · UTM ── -->
<div class="omni-grid">

    <!-- Countries -->
    <div class="ins-card" style="grid-column: span 2;">
        <div class="ins-title"><i class="bi bi-globe2"></i> Visitor Countries</div>
        <?php if (empty($top_countries)): ?>
        <div style="color:#6b7280; font-size:0.78rem; padding:0.4rem 0;">No geo data yet — IPs will be enriched as visitors arrive.</div>
        <?php else: $cc_max = max(1, (int)($top_countries[0]['sessions'] ?? 1)); ?>
        <?php foreach ($top_countries as $cc): $cpct = round($cc['sessions'] / $cc_max * 100); ?>
        <div class="ref-item">
            <span class="ref-name"><span class="country-flag"><?= country_flag($cc['country'] ?? '') ?></span><?= htmlspecialchars($cc['country'] ?? '??') ?></span>
            <div class="ref-bar-wrap"><div class="ref-bar" style="width:<?= $cpct ?>%; background:#38bdf8;"></div></div>
            <span class="ref-count"><?= $cc['sessions'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Browsers -->
    <div class="ins-card">
        <div class="ins-title"><i class="bi bi-browser-chrome"></i> Browsers</div>
        <?php if (empty($top_browsers)): ?>
        <div style="color:#6b7280; font-size:0.78rem; padding:0.4rem 0;">No data yet.</div>
        <?php else: $br_max = max(1,(int)($top_browsers[0]['sessions']??1)); ?>
        <?php foreach ($top_browsers as $br): $bp = round($br['sessions']/$br_max*100); ?>
        <div class="ref-item">
            <span class="ref-name"><?= htmlspecialchars($br['browser'] ?? '?') ?></span>
            <div class="ref-bar-wrap"><div class="ref-bar" style="width:<?= $bp ?>%; background:#a78bfa;"></div></div>
            <span class="ref-count"><?= $br['sessions'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- OS -->
    <div class="ins-card">
        <div class="ins-title"><i class="bi bi-cpu"></i> Operating Systems</div>
        <?php if (empty($top_os)): ?>
        <div style="color:#6b7280; font-size:0.78rem; padding:0.4rem 0;">No data yet.</div>
        <?php else: $os_max = max(1,(int)($top_os[0]['sessions']??1)); ?>
        <?php foreach ($top_os as $osr): $op = round($osr['sessions']/$os_max*100); ?>
        <div class="ref-item">
            <span class="ref-name"><?= htmlspecialchars($osr['os'] ?? '?') ?></span>
            <div class="ref-bar-wrap"><div class="ref-bar" style="width:<?= $op ?>%; background:#fb923c;"></div></div>
            <span class="ref-count"><?= $osr['sessions'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- UTM Sources -->
    <div class="ins-card" style="grid-column: span 2;">
        <div class="ins-title"><i class="bi bi-megaphone"></i> Campaign Sources (UTM)</div>
        <?php if (empty($top_utms)): ?>
        <div style="color:#6b7280; font-size:0.78rem; padding:0.4rem 0;">No UTM-tagged traffic yet. Add ?utm_source=... to links.</div>
        <?php else: ?>
        <table style="width:100%; border-collapse:collapse; font-size:0.74rem;">
            <thead><tr>
                <th style="text-align:left; color:#6b7280; padding:0.3rem 0.5rem; border-bottom:1px solid var(--border);">Source</th>
                <th style="text-align:left; color:#6b7280; padding:0.3rem 0.5rem; border-bottom:1px solid var(--border);">Medium</th>
                <th style="text-align:left; color:#6b7280; padding:0.3rem 0.5rem; border-bottom:1px solid var(--border);">Campaign</th>
                <th style="text-align:right; color:#6b7280; padding:0.3rem 0.5rem; border-bottom:1px solid var(--border);">Sessions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($top_utms as $utm): ?>
            <tr style="border-bottom:1px solid rgba(255,255,255,0.04);">
                <td style="padding:0.3rem 0.5rem; color:#e5e7eb;"><?= htmlspecialchars($utm['utm_source'] ?? '—') ?></td>
                <td style="padding:0.3rem 0.5rem; color:#9ca3af;"><?= htmlspecialchars($utm['utm_medium'] ?? '—') ?></td>
                <td style="padding:0.3rem 0.5rem; color:#9ca3af;"><?= htmlspecialchars($utm['utm_campaign'] ?? '—') ?></td>
                <td style="padding:0.3rem 0.5rem; text-align:right;"><span class="count"><?= $utm['sessions'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Top Searches -->
    <div class="ins-card" style="grid-column: span 2;">
        <div class="ins-title"><i class="bi bi-search"></i> Top Searches</div>
        <?php if (empty($top_searches)): ?>
        <div style="color:#6b7280; font-size:0.78rem; padding:0.4rem 0;">No search events yet. Fire event_type='search' with element_text=query from your site tracker.</div>
        <?php else: $srch_max = max(1, (int)($top_searches[0]['cnt'] ?? 1)); ?>
        <?php foreach ($top_searches as $i => $srch): $spct = round($srch['cnt'] / $srch_max * 100); ?>
        <div class="ref-item">
            <span style="font-size:0.65rem; color:#4b5563; width:16px; flex-shrink:0; text-align:right;"><?= $i+1 ?></span>
            <span class="ref-name" title="<?= htmlspecialchars($srch['query']) ?>"><?= htmlspecialchars(mb_strimwidth($srch['query'], 0, 45, '…')) ?></span>
            <div class="ref-bar-wrap"><div class="ref-bar" style="width:<?= $spct ?>%; background:#38bdf8;"></div></div>
            <span class="ref-count"><?= $srch['cnt'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Entry & Exit Pages (2-column side by side) -->
    <div class="ins-card">
        <div class="ins-title"><i class="bi bi-box-arrow-in-right"></i> Entry Pages</div>
        <?php if (empty($entry_pages)): ?>
        <div style="color:#6b7280; font-size:0.78rem; padding:0.4rem 0;">No entry page data yet.</div>
        <?php else: $ep_max = max(1, (int)($entry_pages[0]['entries'] ?? 1)); ?>
        <?php foreach ($entry_pages as $ep): $epct = round($ep['entries'] / $ep_max * 100); ?>
        <div class="ref-item">
            <span class="ref-name" title="<?= htmlspecialchars($ep['page_url']) ?>"><?= htmlspecialchars(short_url($ep['page_url'])) ?></span>
            <div class="ref-bar-wrap"><div class="ref-bar" style="width:<?= $epct ?>%; background:#34d399;"></div></div>
            <span class="ref-count"><?= $ep['entries'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="ins-card">
        <div class="ins-title"><i class="bi bi-box-arrow-right"></i> Exit Pages</div>
        <?php if (empty($exit_pages)): ?>
        <div style="color:#6b7280; font-size:0.78rem; padding:0.4rem 0;">No exit page data yet.</div>
        <?php else: $xp_max = max(1, (int)($exit_pages[0]['exits'] ?? 1)); ?>
        <?php foreach ($exit_pages as $xp): $xpct = round($xp['exits'] / $xp_max * 100); ?>
        <div class="ref-item">
            <span class="ref-name" title="<?= htmlspecialchars($xp['page_url']) ?>"><?= htmlspecialchars(short_url($xp['page_url'])) ?></span>
            <div class="ref-bar-wrap"><div class="ref-bar" style="width:<?= $xpct ?>%; background:rgba(248,113,113,0.6);"></div></div>
            <span class="ref-count"><?= $xp['exits'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Click Heatmap -->
    <div class="ins-card" style="grid-column: span 2;">
        <div class="ins-title"><i class="bi bi-hand-index-fill"></i> Click Heatmap by Page</div>
        <?php if (empty($click_heatmap)): ?>
        <div style="color:#6b7280; font-size:0.78rem; padding:0.4rem 0;">No click data yet.</div>
        <?php else: ?>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:0.75rem;">
        <?php foreach ($click_heatmap as $chm): ?>
        <div style="background:var(--surface2); border-radius:9px; padding:0.65rem 0.8rem;">
            <div style="font-size:0.72rem; font-weight:700; color:#a78bfa; margin-bottom:0.4rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($chm['page']) ?>"><?= htmlspecialchars(short_url($chm['page'])) ?></div>
            <div style="font-size:0.65rem; color:#4b5563; margin-bottom:0.5rem;"><?= number_format($chm['total']) ?> clicks total</div>
            <div style="display:flex; flex-wrap:wrap; gap:0.3rem;">
            <?php foreach ($chm['elements'] as $el): ?>
            <span style="display:inline-flex; align-items:center; gap:0.2rem; background:rgba(167,139,250,0.1); border:1px solid rgba(167,139,250,0.2); border-radius:99px; padding:0.1rem 0.45rem; font-size:0.67rem; color:#d1d5db;">
                <?= htmlspecialchars(mb_strimwidth($el['label'] ?? '?', 0, 25, '…')) ?>
                <span style="color:#a78bfa; font-weight:700;"><?= $el['cnt'] ?></span>
            </span>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 404 / Not Found Pages -->
    <div class="ins-card" style="grid-column: span 2; border-color:rgba(248,113,113,0.25);">
        <div class="ins-title" style="color:#f87171;"><i class="bi bi-exclamation-diamond"></i> 404 / Not Found Pages</div>
        <?php if (empty($pages_404)): ?>
        <div style="color:#34d399; font-size:0.78rem; padding:0.4rem 0;"><i class="bi bi-check-circle"></i> No 404s detected — great!</div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="log-table">
            <thead><tr>
                <th>URL</th><th>Title</th><th>Hits</th><th>Sessions</th><th>Last Seen</th>
            </tr></thead>
            <tbody>
            <?php foreach ($pages_404 as $p4): ?>
            <tr>
                <td class="url-cell" title="<?= htmlspecialchars($p4['page_url']) ?>"><?= htmlspecialchars(short_url($p4['page_url'])) ?></td>
                <td style="color:#9ca3af; font-size:0.72rem;"><?= htmlspecialchars(mb_strimwidth($p4['page_title'] ?? '', 0, 50, '…')) ?></td>
                <td><span style="font-weight:700; color:#f87171;"><?= number_format($p4['hits']) ?></span></td>
                <td style="color:#9ca3af;"><?= number_format($p4['sessions']) ?></td>
                <td class="time-cell"><?= htmlspecialchars(substr($p4['last_seen'] ?? '', 0, 16)) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Scroll Depth by Page -->
    <div class="ins-card" style="grid-column: span 2;">
        <div class="ins-title"><i class="bi bi-arrow-down-up"></i> Scroll Depth by Page</div>
        <?php if (empty($scroll_pages)): ?>
        <div style="color:#6b7280; font-size:0.78rem; padding:0.4rem 0;">No scroll data yet.</div>
        <?php else: ?>
        <?php foreach ($scroll_pages as $sp_row):
            $d = (int)($sp_row['avg_depth'] ?? 0);
            $col = $d >= 75 ? '#34d399' : ($d >= 50 ? '#fbbf24' : ($d >= 25 ? '#fb923c' : '#f87171'));
        ?>
        <div class="ref-item" style="align-items:center;">
            <span class="ref-name" title="<?= htmlspecialchars($sp_row['page_url']) ?>"><?= htmlspecialchars(short_url($sp_row['page_url'])) ?></span>
            <div class="scroll-bar-wrap"><div class="scroll-bar" style="width:<?= $d ?>%; background:<?= $col ?>;"></div></div>
            <span style="font-size:0.72rem; font-weight:800; color:<?= $col ?>; width:36px; text-align:right; flex-shrink:0;"><?= $d ?>%</span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- JS Errors -->
    <?php if (!empty($js_errors)): ?>
    <div class="ins-card" style="grid-column: span 2; border-color:rgba(248,113,113,0.2);">
        <div class="ins-title" style="color:#f87171;"><i class="bi bi-exclamation-triangle"></i> JS Errors Detected</div>
        <?php foreach ($js_errors as $je): ?>
        <div class="err-row">
            <div class="err-msg"><?= htmlspecialchars($je['msg'] ?? '?') ?></div>
            <div style="display:flex; gap:0.75rem; margin-top:2px; font-size:0.68rem; color:#6b7280;">
                <span><i class="bi bi-arrow-repeat"></i> <?= $je['cnt'] ?>×</span>
                <span title="<?= htmlspecialchars($je['page_url']) ?>"><?= htmlspecialchars(short_url($je['page_url'])) ?></span>
                <span><?= htmlspecialchars(substr($je['last_seen'] ?? '', 0, 16)) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<!-- ══════════════════════════════════════════════════════════
     PALANTIR — 4 Intelligence Panels
     ══════════════════════════════════════════════════════════ -->

<!-- 0. Goals Conversion Tracking -->
<?php require __DIR__ . '/panels/goals.php'; ?>

<!-- 0b. Cohort Retention -->
<?php require __DIR__ . '/panels/cohort.php'; ?>

<!-- 1. Identity Graph -->
<div class="palantir-section" id="pal-identity">
    <div class="palantir-header" onclick="palToggle('pal-identity')">
        <i class="bi bi-diagram-3 pal-icon"></i> Identity Graph
        <span class="pal-badge"><?= number_format($identity_stats['known'] ?? 0) ?> known users</span>
        <i class="bi bi-chevron-down pal-toggle"></i>
    </div>
    <div class="palantir-body">
        <?php if ($active_site === 'apexcybernet'): ?>
        <!-- Live Users Now — injected by activity-apexcybernet.php -->
        <div id="live-users-panel" style="background:rgba(52,211,153,0.05); border:1px solid rgba(52,211,153,0.2); border-radius:10px; padding:0.85rem 1rem; margin-bottom:1.1rem;">
            <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.65rem;">
                <span class="live-dot-sm"></span>
                <span style="font-size:0.72rem; font-weight:700; color:#34d399; text-transform:uppercase; letter-spacing:0.06em;">Live Right Now</span>
                <span id="live-users-meta" style="font-size:0.7rem; color:#6b7280; margin-left:auto;">loading…</span>
            </div>
            <div id="live-users-list" style="display:flex; flex-direction:column; gap:0.35rem;">
                <div style="color:#6b7280; font-size:0.78rem;">Loading…</div>
            </div>
        </div>
        <?php endif; ?>
        <div class="ident-stats">
            <div class="ident-stat">
                <div class="val"><?= number_format($identity_stats['total'] ?? 0) ?></div>
                <div class="lbl">Sessions (7d)</div>
            </div>
            <div class="ident-stat">
                <div class="val" style="color:#34d399;"><?= number_format($identity_stats['known'] ?? 0) ?></div>
                <div class="lbl">Known Users</div>
            </div>
            <div class="ident-stat">
                <div class="val" style="color:#fbbf24;"><?= count($cross_site_users) ?></div>
                <div class="lbl">Cross-Site</div>
            </div>
        </div>
        <?php if ($top_users_graph): ?>
        <div style="overflow-x:auto; margin-bottom:1rem;">
        <table class="log-table">
            <thead><tr><th>User</th><?php if ($active_site==='apexcybernet'): ?><th>Role</th><?php endif; ?><th>Sessions</th><th>Pages</th><th>Device</th><th>Country</th><th>Last Seen</th></tr></thead>
            <tbody>
            <?php foreach ($top_users_graph as $ug): ?>
            <tr>
                <td>
                    <span class="user-tag"><?= htmlspecialchars($ug['display_name'] ?? '—') ?></span>
                    <?php if ($active_site==='apexcybernet' && !empty($ug['email'])): ?>
                    <div style="font-size:0.65rem; color:#6b7280; margin-top:1px;"><?= htmlspecialchars($ug['email']) ?></div>
                    <?php endif; ?>
                </td>
                <?php if ($active_site==='apexcybernet'): ?>
                <td>
                    <?php if (($ug['role'] ?? 'user') === 'merchant'): ?>
                    <span style="font-size:0.65rem; font-weight:700; padding:0.1rem 0.45rem; border-radius:99px; background:rgba(251,191,36,0.15); color:#fbbf24; border:1px solid rgba(251,191,36,0.3);">Merchant</span>
                    <?php else: ?>
                    <span style="font-size:0.65rem; padding:0.1rem 0.45rem; border-radius:99px; background:rgba(167,139,250,0.1); color:#a78bfa; border:1px solid rgba(167,139,250,0.2);">User</span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
                <td style="font-weight:700;"><?= $ug['sessions'] ?></td>
                <td style="color:#9ca3af;"><?= number_format($ug['pages'] ?? 0) ?></td>
                <td style="color:#9ca3af;"><?= htmlspecialchars($ug['device_type'] ?? '—') ?></td>
                <td><span class="country-flag"><?= country_flag($ug['country'] ?? '') ?></span><?= htmlspecialchars($ug['country'] ?? '—') ?></td>
                <td class="time-cell"><?= substr($ug['last_seen'] ?? '', 0, 16) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
        <?php if ($cross_site_users): ?>
        <div style="background:rgba(251,191,36,0.05); border:1px solid rgba(251,191,36,0.15); border-radius:10px; padding:0.75rem 1rem;">
            <div style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#fbbf24; margin-bottom:0.6rem;"><i class="bi bi-link-45deg"></i> Cross-Site Visitors</div>
            <?php foreach ($cross_site_users as $cu): ?>
            <div style="display:flex; align-items:center; gap:0.75rem; padding:0.3rem 0; border-bottom:1px solid rgba(255,255,255,0.04);">
                <span style="font-weight:700; color:#e5e7eb; font-size:0.82rem;"><?= htmlspecialchars($cu['display_name'] ?? 'User #' . $cu['uid']) ?></span>
                <span style="font-size:0.7rem; color:#9ca3af;"><?= htmlspecialchars($cu['sites'] ?? '') ?></span>
                <span style="margin-left:auto; font-size:0.7rem; color:#fbbf24;"><?= $cu['site_count'] ?> sites · <?= number_format($cu['total_pages'] ?? 0) ?> pages</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!$top_users_graph && !$cross_site_users): ?>
        <div style="color:#6b7280; font-size:0.78rem;">No identity data yet. Populates as users visit and log in.</div>
        <?php endif; ?>
    </div>
</div>

<!-- 2. Funnel Analyzer -->
<div class="palantir-section" id="pal-funnels">
    <div class="palantir-header" onclick="palToggle('pal-funnels')">
        <i class="bi bi-filter-circle pal-icon"></i> Funnel Analyzer
        <span class="pal-badge"><?= count($funnels_data) ?> funnel<?= count($funnels_data) !== 1 ? 's' : '' ?></span>
        <i class="bi bi-chevron-down pal-toggle"></i>
    </div>
    <div class="palantir-body">
        <?php if (empty($funnels_data)): ?>
        <div style="color:#6b7280; font-size:0.78rem;">No funnels defined for <strong><?= htmlspecialchars($active_site) ?></strong>. Add them to the <code>funnels</code> table.</div>
        <?php else: ?>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:1.25rem;">
        <?php foreach ($funnels_data as $f):
            $top_cnt = max(1, (int)($f['steps'][0]['count'] ?? 1));
        ?>
        <div style="background:var(--surface2); border-radius:12px; padding:1rem;">
            <div style="font-size:0.82rem; font-weight:800; color:#e5e7eb; margin-bottom:0.9rem;"><?= htmlspecialchars($f['name']) ?></div>
            <?php foreach ($f['steps'] as $i => $step):
                $pct  = $top_cnt > 0 ? round($step['count'] / $top_cnt * 100) : 0;
                $prev = $i > 0 ? max(1, $f['steps'][$i-1]['count']) : 0;
                $drop = ($i > 0 && $prev > 0) ? round((1 - $step['count'] / $prev) * 100) : 0;
                $col  = $pct >= 60 ? '#34d399' : ($pct >= 30 ? '#fbbf24' : '#f87171');
            ?>
            <?php if ($i > 0): ?>
            <div class="funnel-drop">▼ <?= $drop ?>% drop-off</div>
            <?php endif; ?>
            <div class="funnel-step-row">
                <span style="font-size:0.68rem; color:#6b7280; width:18px; text-align:center; flex-shrink:0;"><?= $i+1 ?></span>
                <span style="font-size:0.74rem; flex-shrink:0; width:85px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#d1d5db;" title="<?= htmlspecialchars($step['pattern']) ?>"><?= htmlspecialchars($step['label']) ?></span>
                <div class="funnel-bar-wrap"><div class="funnel-bar" style="width:<?= $pct ?>%; background:<?= $col ?>;"></div></div>
                <span style="font-size:0.7rem; font-weight:800; color:<?= $col ?>; width:30px; text-align:right; flex-shrink:0;"><?= $pct ?>%</span>
                <span style="font-size:0.66rem; color:#6b7280; width:38px; text-align:right; flex-shrink:0;"><?= number_format($step['count']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 3. Segment Explorer -->
<div class="palantir-section" id="pal-segments">
    <div class="palantir-header" onclick="palToggle('pal-segments')">
        <i class="bi bi-people-fill pal-icon"></i> Segment Explorer
        <?php if ($seg_results): ?>
        <span class="pal-badge"><?= count($seg_results) ?> sessions found</span>
        <?php endif; ?>
        <i class="bi bi-chevron-down pal-toggle"></i>
    </div>
    <div class="palantir-body">
        <form method="get" class="seg-filter-bar">
            <input type="hidden" name="dr" value="<?= $date_range ?>">
            <select name="seg_country">
                <option value="">Any country</option>
                <?php foreach ($seg_countries_list as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $seg_country === $c ? 'selected' : '' ?>><?= country_flag($c) ?> <?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="seg_device">
                <option value="">Any device</option>
                <?php foreach (['mobile','desktop','tablet','bot'] as $d): ?>
                <option value="<?= $d ?>" <?= $seg_device === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="seg_utm" placeholder="UTM source…" value="<?= htmlspecialchars($seg_utm) ?>" style="width:120px;">
            <input type="number" name="seg_pages" min="0" placeholder="Min pages" value="<?= $seg_pages ?: '' ?>" style="width:85px;">
            <button type="submit" class="btn-filter"><i class="bi bi-funnel"></i> Explore</button>
            <?php if ($seg_country || $seg_device || $seg_utm || $seg_pages): ?>
            <a href="<?= $page_file ?>?dr=<?= $date_range ?>#pal-segments" style="font-size:0.76rem; color:#9ca3af;">✕ Clear</a>
            <?php endif; ?>
        </form>
        <?php if ($seg_country || $seg_device || $seg_utm || $seg_pages > 0): ?>
            <?php if (empty($seg_results)): ?>
            <div style="color:#6b7280; font-size:0.78rem; padding:0.25rem 0;">No sessions match this segment.</div>
            <?php else: ?>
            <?php $seg_known = count(array_filter(array_column($seg_results, 'uid'))); ?>
            <div style="font-size:0.72rem; color:#9ca3af; margin-bottom:0.75rem;">
                <?= count($seg_results) ?> sessions ·
                <?= number_format(array_sum(array_column($seg_results, 'page_count'))) ?> total pages ·
                <?= $seg_known ?> known user<?= $seg_known !== 1 ? 's' : '' ?>
            </div>
            <div style="overflow-x:auto;">
            <table class="log-table">
                <thead><tr><th>Session</th><th>User</th><th>Geo</th><th>Device</th><th>Browser</th><th>UTM Source</th><th>Pages</th><th>Events</th><th>Last Seen</th></tr></thead>
                <tbody>
                <?php foreach ($seg_results as $sr): ?>
                <tr>
                    <td><span class="sess-chip"><?= htmlspecialchars(substr($sr['session_id'], 0, 10)) ?>…</span></td>
                    <td><?php if ($sr['uid']): ?><span class="user-tag"><?= htmlspecialchars($sr['display_name'] ?? '#'.$sr['uid']) ?></span><?php else: ?><span class="guest-tag">guest</span><?php endif; ?></td>
                    <td><span class="country-flag"><?= country_flag($sr['country'] ?? '') ?></span><?= htmlspecialchars($sr['city'] ? $sr['city'].', '.($sr['country']??'') : ($sr['country']??'—')) ?></td>
                    <td style="color:#9ca3af;"><?= htmlspecialchars($sr['device_type'] ?? '—') ?></td>
                    <td style="color:#9ca3af;"><?= htmlspecialchars($sr['browser'] ?? '—') ?></td>
                    <td style="color:#a78bfa;"><?= htmlspecialchars($sr['utm_source'] ?? '—') ?></td>
                    <td style="font-weight:700;"><?= $sr['page_count'] ?></td>
                    <td style="color:#6b7280;"><?= $sr['event_count'] ?></td>
                    <td class="time-cell"><?= substr($sr['last_seen'] ?? '', 0, 16) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        <?php else: ?>
        <div style="color:#6b7280; font-size:0.78rem;">Filter your audience by country, device, UTM source, or minimum pages visited. Example: <em>Country=PH + Device=mobile</em> → all Filipino mobile visitors.</div>
        <?php endif; ?>
    </div>
</div>

<!-- 4. Alert Engine -->
<div class="palantir-section" id="pal-alerts">
    <div class="palantir-header" onclick="palToggle('pal-alerts')">
        <i class="bi bi-bell-fill pal-icon"></i> Alert Engine
        <?php $active_rule_count = count(array_filter($alert_rules_list, fn($r) => $r['active'])); ?>
        <span class="pal-badge"><?= $active_rule_count ?> active rule<?= $active_rule_count !== 1 ? 's' : '' ?></span>
        <?php if ($recent_alert_log): ?>
        <span style="font-size:0.67rem; padding:0.1rem 0.5rem; border-radius:6px; background:rgba(248,113,113,0.15); color:#f87171; font-weight:700;"><?= count($recent_alert_log) ?> recent fire<?= count($recent_alert_log) !== 1 ? 's' : '' ?></span>
        <?php endif; ?>
        <i class="bi bi-chevron-down pal-toggle"></i>
    </div>
    <div class="palantir-body">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">

            <div>
                <div class="ins-title" style="margin-bottom:0.6rem;"><i class="bi bi-sliders"></i> Alert Rules</div>
                <?php if (empty($alert_rules_list)): ?>
                <div style="color:#6b7280; font-size:0.78rem; margin-bottom:0.75rem;">No rules yet. Add one below.</div>
                <?php else: ?>
                <?php
                $type_style = [
                    'traffic_drop' => ['#fbbf24', 'rgba(251,191,36,0.14)'],
                    'error_spike'  => ['#f87171', 'rgba(248,113,113,0.14)'],
                    'no_traffic'   => ['#60a5fa', 'rgba(96,165,250,0.14)'],
                ];
                foreach ($alert_rules_list as $ar):
                    [$tc, $tbg] = $type_style[$ar['alert_type']] ?? ['#9ca3af', 'rgba(255,255,255,0.07)'];
                ?>
                <div class="alert-rule-row">
                    <span class="alert-active-dot" style="background:<?= $ar['active'] ? '#34d399' : '#4b5563' ?>;"></span>
                    <span class="alert-type-badge" style="background:<?= $tbg ?>; color:<?= $tc ?>;"><?= htmlspecialchars(str_replace('_', ' ', $ar['alert_type'])) ?></span>
                    <span class="alert-site-badge"><?= htmlspecialchars($ar['site']) ?></span>
                    <span style="font-size:0.7rem; color:#9ca3af; flex:1;">
                        <?php if (!empty($ar['name'])): ?><em style="color:#d1d5db;"><?= htmlspecialchars($ar['name']) ?></em> · <?php endif; ?>
                        <?= $ar['threshold_pct'] > 0 ? $ar['threshold_pct'].'% · ' : '' ?><?= $ar['window_minutes'] ?>min
                    </span>
                    <a href="<?= $page_file ?>?dr=<?= $date_range ?>&toggle_alert=<?= $ar['id'] ?>#pal-alerts"
                       style="font-size:0.68rem; font-weight:700; text-decoration:none; color:<?= $ar['active'] ? '#f87171' : '#34d399' ?>; margin-right:6px;"
                       onclick="return confirm('Toggle this rule?')"><?= $ar['active'] ? 'Disable' : 'Enable' ?></a>
                    <a href="<?= $page_file ?>?dr=<?= $date_range ?>&action=del_alert_rule&id=<?= $ar['id'] ?>#pal-alerts"
                       style="font-size:0.68rem; font-weight:700; text-decoration:none; color:#4b5563;"
                       onclick="return confirm('Delete this alert rule?')"><i class="bi bi-trash"></i></a>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <!-- Add rule form -->
                <div style="margin-top:0.85rem; background:var(--surface2); border-radius:9px; padding:0.75rem 0.9rem;">
                    <div style="font-size:0.7rem; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:0.6rem;"><i class="bi bi-plus-circle"></i> New Alert Rule</div>
                    <form method="POST" action="<?= htmlspecialchars($page_file) ?>?dr=<?= $date_range ?>#pal-alerts" style="display:flex; flex-direction:column; gap:0.45rem;">
                        <input type="hidden" name="action" value="add_alert_rule">
                        <input type="text" name="rule_name" placeholder="Rule name (optional)"
                               style="background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:6px; padding:0.28rem 0.55rem; font-size:0.76rem; width:100%;">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.4rem;">
                            <select name="alert_type" style="background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:6px; padding:0.28rem 0.5rem; font-size:0.76rem;">
                                <option value="traffic_drop">Traffic Drop</option>
                                <option value="error_spike">Error Spike</option>
                                <option value="no_traffic">No Traffic</option>
                            </select>
                            <select name="rule_site" style="background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:6px; padding:0.28rem 0.5rem; font-size:0.76rem;">
                                <?php foreach (['apexcybernet','ocpd','loan','alrisha'] as $rs): ?>
                                <option value="<?= $rs ?>" <?= $active_site === $rs ? 'selected' : '' ?>><?= ucfirst($rs) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.4rem;">
                            <div style="display:flex; flex-direction:column; gap:0.15rem;">
                                <label style="font-size:0.65rem; color:#6b7280;">Threshold %</label>
                                <input type="number" name="threshold_pct" min="1" max="100" value="50"
                                       style="background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:6px; padding:0.28rem 0.5rem; font-size:0.76rem;">
                            </div>
                            <div style="display:flex; flex-direction:column; gap:0.15rem;">
                                <label style="font-size:0.65rem; color:#6b7280;">Window (minutes)</label>
                                <input type="number" name="window_minutes" min="1" value="30"
                                       style="background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:6px; padding:0.28rem 0.5rem; font-size:0.76rem;">
                            </div>
                        </div>
                        <button type="submit" style="background:rgba(251,191,36,0.12); border:1px solid rgba(251,191,36,0.3); color:#fbbf24; border-radius:7px; padding:0.3rem 0.7rem; font-size:0.76rem; cursor:pointer; font-weight:700; align-self:flex-start;">
                            <i class="bi bi-bell-plus"></i> Add Rule
                        </button>
                    </form>
                </div>

                <div style="margin-top:0.75rem; padding:0.65rem 0.85rem; background:var(--surface2); border-radius:8px; font-size:0.69rem; color:#6b7280; line-height:1.6;">
                    <div style="color:#9ca3af; font-weight:700; margin-bottom:3px;"><i class="bi bi-terminal"></i> Cron setup (every 15 min)</div>
                    <code style="color:#a78bfa; word-break:break-all;">*/15 * * * * curl -s "https://apexcybernet.com/cron/alerts.php?token=apexcybernet-admin-2026-token"</code>
                </div>
            </div>

            <div>
                <div class="ins-title" style="margin-bottom:0.6rem;"><i class="bi bi-clock-history"></i> Recent Alerts</div>
                <?php if (empty($recent_alert_log)): ?>
                <div style="color:#6b7280; font-size:0.78rem; padding:0.4rem 0;">No alerts fired yet. <span style="color:#34d399;">Good sign!</span></div>
                <?php else: ?>
                <?php foreach ($recent_alert_log as $al): ?>
                <div style="padding:0.5rem 0; border-bottom:1px solid var(--border); font-size:0.72rem;">
                    <div style="display:flex; align-items:center; gap:0.45rem; margin-bottom:3px;">
                        <span style="font-size:0.63rem; color:#f87171; font-weight:700; background:rgba(248,113,113,0.1); padding:0.07rem 0.4rem; border-radius:4px;"><?= htmlspecialchars(str_replace('_', ' ', $al['alert_type'] ?? '')) ?></span>
                        <span class="alert-site-badge"><?= htmlspecialchars($al['site'] ?? '') ?></span>
                        <span style="color:#6b7280; font-size:0.63rem; margin-left:auto;"><?= substr($al['fired_at'] ?? '', 0, 16) ?></span>
                    </div>
                    <div style="color:#9ca3af; word-break:break-word; line-height:1.4;"><?= htmlspecialchars(substr($al['message'] ?? '', 0, 180)) ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- Live feed bar -->
<div class="live-bar">
    <span class="live-dot" id="liveDot"></span>
    <span id="liveStatus">Live feed off</span>
    <button class="live-btn" id="liveToggle">Start Live</button>
    <span style="margin-left:auto; font-size:0.72rem; color:#4b5563;" id="liveCount"></span>
</div>

<!-- Filter + log table -->
<form method="get" class="filter-bar">
    <select name="dr">
        <option value="today" <?= $date_range==='today'?'selected':'' ?>>Today</option>
        <option value="7d"    <?= $date_range==='7d'?'selected':'' ?>>Last 7 days</option>
        <option value="30d"   <?= $date_range==='30d'?'selected':'' ?>>Last 30 days</option>
        <option value="all"   <?= $date_range==='all'?'selected':'' ?>>All time</option>
    </select>
    <select name="ev">
        <option value="all"      <?= $event_filter==='all'?'selected':'' ?>>All events</option>
        <option value="pageview" <?= $event_filter==='pageview'?'selected':'' ?>>Pageviews</option>
        <option value="click"    <?= $event_filter==='click'?'selected':'' ?>>Clicks</option>
        <option value="chat"     <?= $event_filter==='chat'?'selected':'' ?>>Chat Messages</option>
        <option value="scroll"   <?= $event_filter==='scroll'?'selected':'' ?>>Scrolls</option>
        <option value="error"    <?= $event_filter==='error'?'selected':'' ?>>Errors</option>
    </select>
    <select name="uf">
        <option value="all"      <?= $user_filter==='all'?'selected':'' ?>>All users</option>
        <option value="loggedin" <?= $user_filter==='loggedin'?'selected':'' ?>>Logged-in</option>
        <option value="guest"    <?= $user_filter==='guest'?'selected':'' ?>>Guests</option>
    </select>
    <input type="text" name="q" placeholder="Search URL, user, IP…" value="<?= htmlspecialchars($search) ?>" style="width:200px;">
    <button type="submit" class="btn-filter"><i class="bi bi-funnel"></i> Filter</button>
    <a href="<?= $page_file ?>?<?= $export_qs ?>" class="btn-export"><i class="bi bi-download"></i> CSV</a>
    <?php if ($search || $date_range!=='today' || $event_filter!=='all' || $user_filter!=='all'): ?>
    <a href="<?= $page_file ?>" style="color:#9ca3af; font-size:0.76rem;">✕ Clear</a>
    <?php endif; ?>
    <span style="margin-left:auto; font-size:0.76rem; color:#6b7280;"><?= number_format($total_rows) ?> rows</span>
</form>

<div class="main-grid">

    <div class="card">
        <div class="card-header"><span><i class="bi bi-list-ul"></i> Event Log</span>
            <span style="font-size:0.7rem; color:#6b7280;">Click <kbd style="background:var(--bg);border:1px solid var(--border);padding:1px 4px;border-radius:3px;font-size:0.65rem;">SID</kbd> to see full session journey</span>
        </div>
        <div style="overflow-x:auto;">
        <table class="log-table">
            <thead>
                <tr>
                    <th>Time</th><th>Type</th><th>User</th><th>Page</th><th>Action</th><th>IP</th>
                </tr>
            </thead>
            <tbody id="liveRows">
            <?php if (empty($rows)): ?>
            <tr><td colspan="6" style="text-align:center; padding:2rem; color:#6b7280;"><i class="bi bi-inbox"></i> No events found</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td class="time-cell">
                    <?= date('H:i:s', strtotime($row['created_at'])) ?><br>
                    <span style="font-size:0.65rem;"><?= date('M j', strtotime($row['created_at'])) ?></span><br>
                    <span class="sess-chip" onclick="openSession('<?= htmlspecialchars($row['session_id']) ?>')" title="View session journey">SID <?= htmlspecialchars(substr($row['session_id'],0,6)) ?>…</span>
                </td>
                <td>
                    <?php if ($row['event_type']==='pageview'): ?><span class="badge-pv">PV</span>
                    <?php elseif ($row['event_type']==='click'): ?><span class="badge-cl">CLK</span>
                    <?php elseif ($row['event_type']==='scroll'): ?><span class="badge-scroll">SCR</span>
                    <?php elseif ($row['event_type']==='timeonpage'): ?><span class="badge-time">TOP</span>
                    <?php elseif ($row['event_type']==='error'): ?><span class="badge-error">ERR</span>
                    <?php else: ?><span class="badge-oth"><?= htmlspecialchars(strtoupper($row['event_type'])) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($row['account_id']): ?>
                        <span class="user-tag" onclick="filterUser('<?= htmlspecialchars($row['display_name']??'') ?>')" title="Filter by this user"><?= htmlspecialchars($row['display_name'] ?? 'User #'.$row['account_id']) ?></span>
                    <?php else: ?>
                        <span class="guest-tag">Guest <span style="font-size:0.65rem; opacity:0.5;"><?= htmlspecialchars(substr($row['session_id'],0,8)) ?>…</span></span>
                    <?php endif; ?>
                </td>
                <td class="url-cell" title="<?= htmlspecialchars($row['page_url']) ?>"><?= htmlspecialchars(short_url($row['page_url'])) ?></td>
                <td class="action-cell">
                    <?php if ($row['event_type']==='pageview'): ?>
                        <span style="color:#6b7280; font-size:0.7rem;"><?= htmlspecialchars($row['page_title']??'') ?></span>
                        <?php if (!empty($row['load_time'])): ?><span style="margin-left:4px; font-size:0.62rem; color:#38bdf8;">⚡<?= $row['load_time'] >= 1000 ? round($row['load_time']/1000,1).'s' : $row['load_time'].'ms' ?></span><?php endif; ?>
                    <?php elseif ($row['event_type']==='click'): ?>
                        <?php
                        $parts=[];
                        if ($row['element_tag'])  $parts[] = '<span style="color:#6b7280;font-size:0.68rem;">&lt;'.htmlspecialchars($row['element_tag']).'&gt;</span>';
                        if ($row['element_text']) $parts[] = '"'.htmlspecialchars(mb_strimwidth($row['element_text'],0,40,'…')).'"';
                        if ($row['element_href'] && $row['element_href']!==$row['page_url'])
                            $parts[] = '<span style="color:var(--accent-light);">'.htmlspecialchars(short_url($row['element_href'])).'</span>';
                        echo implode(' ', $parts) ?: '<span style="color:#4b5563;">—</span>';
                        ?>
                    <?php elseif ($row['event_type']==='scroll'): ?>
                        <?php $sd=(int)($row['scroll_depth']??0); $sc=$sd>=75?'#34d399':($sd>=50?'#fbbf24':'#fb923c'); ?>
                        <span style="color:<?=$sc?>;font-weight:700;font-size:0.78rem;"><?=$sd?>% scrolled</span>
                    <?php elseif ($row['event_type']==='timeonpage'): ?>
                        <?php $top_val=(int)($row['time_on_page']??0); $m=floor($top_val/60); $sec_val=$top_val%60; ?>
                        <span style="color:#fbbf24;font-size:0.78rem;"><?= $m>0?"⏱ {$m}m {$sec_val}s":"⏱ {$sec_val}s" ?> on page</span>
                    <?php elseif ($row['event_type']==='error'): ?>
                        <span style="color:#f87171;font-size:0.7rem;font-family:monospace;"><?= htmlspecialchars(mb_strimwidth($row['element_text']??'',0,60,'…')) ?></span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td class="ip-cell">
                    <?php if (!empty($row['country'])): ?><span title="<?= htmlspecialchars(($row['city']??'').' '.$row['country']) ?>"><?= country_flag($row['country']) ?></span> <?php endif; ?>
                    <?= htmlspecialchars($row['ip']??'—') ?>
                    <?php if (!empty($row['browser'])): ?><br><span style="font-size:0.62rem; color:#4b5563;"><?= htmlspecialchars($row['browser']) ?></span><?php endif; ?>
                    <?php if (!empty($row['city'])): ?><br><span style="font-size:0.62rem; color:#374151;"><?= htmlspecialchars($row['city']) ?></span><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            $qp = http_build_query(array_filter(['dr'=>$date_range!=='today'?$date_range:null,'ev'=>$event_filter!=='all'?$event_filter:null,'uf'=>$user_filter!=='all'?$user_filter:null,'q'=>$search?:null]));
            $qs = $qp ? '&' : '';
            $start = max(1,$page-4); $end = min($total_pages,$page+4);
            if ($page>1): ?><a href="<?= $page_file ?>?<?=$qp.$qs?>p=<?=$page-1?>" class="pg-btn"><i class="bi bi-chevron-left"></i></a><?php endif;
            for ($i=$start;$i<=$end;$i++): ?>
            <a href="<?= $page_file ?>?<?=$qp.$qs?>p=<?=$i?>" class="pg-btn <?=$i===$page?'active':''?>"><?=$i?></a>
            <?php endfor;
            if ($page<$total_pages): ?><a href="<?= $page_file ?>?<?=$qp.$qs?>p=<?=$page+1?>" class="pg-btn"><i class="bi bi-chevron-right"></i></a><?php endif; ?>
            <span style="font-size:0.72rem; color:#6b7280; margin-left:0.5rem;">Page <?=$page?> of <?=$total_pages?></span>
        </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="card side-card">
            <div class="card-header"><i class="bi bi-bar-chart-line"></i> Top Pages</div>
            <?php if (empty($top_pages)): ?>
            <div style="padding:1rem; color:#6b7280; font-size:0.78rem; text-align:center;">No data</div>
            <?php endif; ?>
            <?php foreach ($top_pages as $tp): ?>
            <div class="top-item">
                <span class="page-path" title="<?= htmlspecialchars($tp['page_url']) ?>"
                    style="cursor:pointer;" onclick="retargetPage('<?= htmlspecialchars(short_url($tp['page_url'])) ?>')"
                ><?= htmlspecialchars(short_url($tp['page_url'])) ?></span>
                <span class="count"><?= number_format($tp['views']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="card side-card">
            <div class="card-header"><i class="bi bi-people"></i> Top Users</div>
            <?php if (empty($top_users)): ?>
            <div style="padding:1rem; color:#6b7280; font-size:0.78rem; text-align:center;">No data</div>
            <?php endif; ?>
            <?php foreach ($top_users as $tu): ?>
            <div class="top-item" style="flex-direction:column; align-items:flex-start; gap:0.15rem;">
                <div style="display:flex; justify-content:space-between; width:100%; align-items:center;">
                    <span class="user-tag" onclick="filterUser('<?= htmlspecialchars($tu['display_name']??'') ?>')"><?= htmlspecialchars($tu['display_name']??'User') ?></span>
                    <span class="count"><?= number_format($tu['events']) ?> ev</span>
                </div>
                <?php if ($tu['email']): ?>
                <span style="font-size:0.65rem; color:#6b7280;"><?= htmlspecialchars($tu['email']) ?></span>
                <?php endif; ?>
                <span style="font-size:0.62rem; color:#4b5563;">Last: <?= date('M j H:i', strtotime($tu['last_seen'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Chat Analytics ── -->
    <div class="row">
        <div class="card" style="grid-column: span 12;">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                <div><i class="bi bi-chat-dots-fill" style="color:#a78bfa;"></i> Chat Activity</div>
                <a href="?<?= http_build_query(['dr'=>$date_range,'ev'=>'chat','uf'=>$user_filter,'q'=>$search]) ?>" style="font-size:0.72rem;color:#9ca3af;text-decoration:none;">Filter to chat →</a>
            </div>
            <div style="padding: 0.85rem 1rem 1.1rem;">
                <!-- Quick stats -->
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0.6rem;margin-bottom:0.85rem;">
                    <div style="background:rgba(124,58,237,0.06);border:1px solid rgba(124,58,237,0.2);border-radius:10px;padding:0.65rem 0.85rem;">
                        <div style="font-size:0.62rem;color:#9ca3af;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Messages</div>
                        <div style="font-size:1.35rem;font-weight:900;color:#e5e7eb;line-height:1.1;margin-top:0.2rem;"><?= number_format($chat_stats['total']) ?></div>
                    </div>
                    <div style="background:rgba(52,211,153,0.06);border:1px solid rgba(52,211,153,0.2);border-radius:10px;padding:0.65rem 0.85rem;">
                        <div style="font-size:0.62rem;color:#9ca3af;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Senders</div>
                        <div style="font-size:1.35rem;font-weight:900;color:#e5e7eb;line-height:1.1;margin-top:0.2rem;"><?= number_format($chat_stats['senders']) ?></div>
                    </div>
                    <div style="background:rgba(96,165,250,0.06);border:1px solid rgba(96,165,250,0.2);border-radius:10px;padding:0.65rem 0.85rem;">
                        <div style="font-size:0.62rem;color:#9ca3af;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Conversations</div>
                        <div style="font-size:1.35rem;font-weight:900;color:#e5e7eb;line-height:1.1;margin-top:0.2rem;"><?= number_format($chat_stats['conversations']) ?></div>
                    </div>
                    <div style="background:rgba(251,191,36,0.06);border:1px solid rgba(251,191,36,0.2);border-radius:10px;padding:0.65rem 0.85rem;">
                        <div style="font-size:0.62rem;color:#9ca3af;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Avg Length</div>
                        <div style="font-size:1.35rem;font-weight:900;color:#e5e7eb;line-height:1.1;margin-top:0.2rem;"><?= number_format($chat_stats['avg_len']) ?> <span style="font-size:0.7rem;color:#6b7280;font-weight:600;">chars</span></div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns: 1fr 1.4fr 1fr;gap:0.9rem;">
                    <!-- Top senders -->
                    <div>
                        <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#9ca3af;margin-bottom:0.5rem;">Top Senders</div>
                        <?php if (empty($chat_top)): ?>
                        <div style="color:#6b7280;font-size:0.78rem;">No chat yet.</div>
                        <?php else: foreach ($chat_top as $ct): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:0.35rem 0.5rem;border-radius:6px;margin-bottom:2px;background:rgba(255,255,255,0.02);">
                            <span style="font-size:0.78rem;font-weight:600;color:#e5e7eb;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($ct['display_name'] ?? 'User') ?></span>
                            <span style="font-size:0.72rem;color:#a78bfa;font-weight:700;"><?= number_format($ct['msgs']) ?></span>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <!-- Recent messages -->
                    <div>
                        <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#9ca3af;margin-bottom:0.5rem;">Recent Messages</div>
                        <?php if (empty($chat_recent)): ?>
                        <div style="color:#6b7280;font-size:0.78rem;">No chat yet.</div>
                        <?php else: foreach ($chat_recent as $cr):
                            $kind_label = match($cr['kind'] ?? '') { 'live' => 'Live chat', 'group' => 'Group', 'dm' => 'DM', default => '' };
                            $kind_color = match($cr['kind'] ?? '') { 'live' => '#a78bfa', 'group' => '#34d399', 'dm' => '#38bdf8', default => '#6b7280' };
                        ?>
                        <div style="padding:0.4rem 0.6rem;border-radius:6px;margin-bottom:3px;background:rgba(255,255,255,0.02);border-left:2px solid <?= $kind_color ?>;">
                            <div style="display:flex;justify-content:space-between;gap:0.4rem;">
                                <span style="font-size:0.72rem;font-weight:700;color:#e5e7eb;"><?= htmlspecialchars($cr['display_name'] ?? 'User') ?></span>
                                <span style="font-size:0.62rem;color:#6b7280;"><span style="color:<?= $kind_color ?>;font-weight:700;"><?= $kind_label ?></span> · <?= date('M j H:i', strtotime($cr['created_at'])) ?></span>
                            </div>
                            <div style="font-size:0.78rem;color:#d1d5db;margin-top:1px;word-break:break-word;"><?= htmlspecialchars($cr['message'] ?? '') ?></div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <!-- Word cloud / top tokens -->
                    <div>
                        <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#9ca3af;margin-bottom:0.5rem;">Top Words</div>
                        <?php if (empty($chat_wordcloud)): ?>
                        <div style="color:#6b7280;font-size:0.78rem;">No chat yet.</div>
                        <?php else:
                            $wc_max = max($chat_wordcloud);
                            foreach ($chat_wordcloud as $w => $n):
                                $scale = 0.7 + ($n / max(1,$wc_max)) * 0.6;
                        ?>
                        <span style="display:inline-block;margin:2px 4px;padding:2px 8px;border-radius:99px;background:rgba(124,58,237,0.12);border:1px solid rgba(124,58,237,0.25);color:#a78bfa;font-size:<?= round($scale * 0.8, 2) ?>rem;font-weight:700;" title="<?= $n ?>× ><?= htmlspecialchars($w) ?></span>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Retargeting Panel -->
<div class="retarget-panel" id="retargetPanel">
    <h2><i class="bi bi-bullseye"></i> Retargeting</h2>
    <p>Find users & guests who visited a specific page — export for follow-up.</p>
    <div class="retarget-input-row">
        <input type="text" class="retarget-input" id="retargetUrl" placeholder="Page URL or partial path, e.g. /buy.php or hcoin">
        <select class="retarget-dr" id="retargetDr">
            <option value="today">Today</option>
            <option value="7d">Last 7 days</option>
            <option value="30d">Last 30 days</option>
            <option value="all" selected>All time</option>
        </select>
        <button class="btn-retarget" onclick="runRetarget()"><i class="bi bi-search"></i> Find Visitors</button>
    </div>
    <div class="retarget-results" id="retargetResults" style="display:none;"></div>
</div>

</div><!-- /wrap -->

<!-- Session Timeline Modal -->
<div class="modal-overlay" id="sessionModal" onclick="closeModal(event)">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="bi bi-diagram-3" style="color:var(--accent-light)"></i> Session Journey</h3>
            <button class="modal-close" onclick="document.getElementById('sessionModal').classList.remove('open')">✕</button>
        </div>
        <div class="modal-body" id="modalBody">
            <div style="text-align:center; color:#6b7280; padding:2rem;">Loading…</div>
        </div>
    </div>
</div>

<script>
// ── Main line chart ──
const ctx = document.getElementById('mainChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [
            { label:'Pageviews', data:<?= json_encode($chart_pv) ?>, borderColor:'#60a5fa', backgroundColor:'rgba(96,165,250,0.08)', borderWidth:2, pointRadius:3, pointHoverRadius:5, tension:0.35, fill:true },
            { label:'Clicks',    data:<?= json_encode($chart_cl) ?>, borderColor:'#a78bfa', backgroundColor:'rgba(167,139,250,0.06)', borderWidth:2, pointRadius:3, pointHoverRadius:5, tension:0.35, fill:true }
        ]
    },
    options: {
        responsive:true, maintainAspectRatio:false,
        interaction:{ mode:'index', intersect:false },
        plugins:{
            legend:{ labels:{ color:'#9ca3af', font:{ size:11 }, boxWidth:12 } },
            tooltip:{ backgroundColor:'#1e1e28', borderColor:'rgba(255,255,255,0.08)', borderWidth:1, titleColor:'#e5e7eb', bodyColor:'#9ca3af' },
            apexcybernetAnnotations: {}
        },
        scales:{
            x:{ grid:{ color:'rgba(255,255,255,0.04)' }, ticks:{ color:'#6b7280', font:{ size:10 }, maxTicksLimit:12 } },
            y:{ grid:{ color:'rgba(255,255,255,0.04)' }, ticks:{ color:'#6b7280', font:{ size:10 } }, beginAtZero:true }
        }
    },
    plugins: [window._annotationPlugin || {}]
});

// ── New vs Returning donut ──
new Chart(document.getElementById('nrChart').getContext('2d'), {
    type:'doughnut',
    data:{ labels:['New','Returning'], datasets:[{ data:[<?= $new_sessions ?>,<?= $ret_sessions ?>], backgroundColor:['#34d399','#a78bfa'], borderWidth:0, hoverOffset:4 }] },
    options:{ cutout:'70%', plugins:{ legend:{ display:false }, tooltip:{ backgroundColor:'#1e1e28', borderColor:'rgba(255,255,255,0.08)', borderWidth:1, titleColor:'#e5e7eb', bodyColor:'#9ca3af' } }, responsive:false }
});

// ── Auto refresh ──
document.getElementById('lastRefresh').textContent = 'Updated ' + new Date().toLocaleTimeString();
var refreshTimer = null;
document.getElementById('autoRefresh').addEventListener('change', function(){
    refreshTimer = this.checked ? setInterval(()=>location.reload(), 30000) : (clearInterval(refreshTimer), null);
});

// ── Filter by user (click on name) ──
function filterUser(name) {
    const url = new URL(window.location.href);
    url.searchParams.set('q', name);
    url.searchParams.set('p', '1');
    window.location.href = url.toString();
}

// ── Session timeline modal ──
function openSession(sid) {
    const modal = document.getElementById('sessionModal');
    const body  = document.getElementById('modalBody');
    body.innerHTML = '<div style="text-align:center;color:#6b7280;padding:2rem;">Loading…</div>';
    modal.classList.add('open');
    fetch('<?= $page_file ?>?ajax=session&sid=' + encodeURIComponent(sid))
        .then(r => r.json())
        .then(events => {
            if (!events.length) { body.innerHTML = '<p style="color:#6b7280;">No events found for this session.</p>'; return; }
            const typeBadge = t => t==='pageview' ? '<span class="badge-pv">PV</span>' : t==='click' ? '<span class="badge-cl">CLK</span>' : '<span class="badge-oth">'+t.toUpperCase()+'</span>';
            const dotColor  = t => t==='pageview' ? '#60a5fa' : t==='click' ? '#a78bfa' : '#9ca3af';
            const shortUrl  = u => { try { const p=new URL(u); return p.pathname+(p.search||''); } catch(e){ return u; } };
            const t0 = new Date(events[0].created_at.replace(' ','T'));
            let html = `<div style="font-size:0.72rem;color:#6b7280;margin-bottom:1rem;">${events.length} events &bull; Session: <code style="color:#a78bfa">${sid.slice(0,12)}…</code></div>`;
            html += '<div class="timeline">';
            events.forEach((ev, i) => {
                const t = new Date(ev.created_at.replace(' ','T'));
                const offset = i === 0 ? '+0s' : '+' + Math.round((t - t0)/1000) + 's';
                let action = '';
                if (ev.event_type === 'pageview') action = ev.page_title ? `<span style="color:#6b7280">${ev.page_title}</span>` : '';
                else if (ev.event_type === 'click') {
                    const parts = [];
                    if (ev.element_tag)  parts.push('&lt;'+ev.element_tag+'&gt;');
                    if (ev.element_text) parts.push('"'+ev.element_text.slice(0,50)+'"');
                    if (ev.element_href) parts.push('<span style="color:var(--accent-light)">→ '+shortUrl(ev.element_href)+'</span>');
                    action = parts.join(' ');
                }
                html += `<div class="tl-item">
                    <span class="tl-dot" style="background:${dotColor(ev.event_type)}"></span>
                    <div class="tl-time">${ev.created_at.slice(11,19)} <span style="color:#4b5563">(${offset})</span> &nbsp;${typeBadge(ev.event_type)}</div>
                    <div class="tl-page">${shortUrl(ev.page_url)}</div>
                    ${action ? `<div class="tl-action">${action}</div>` : ''}
                </div>`;
            });
            html += '</div>';
            body.innerHTML = html;
        })
        .catch(() => { body.innerHTML = '<p style="color:#f87171;">Failed to load session.</p>'; });
}
function closeModal(e) {
    if (e.target === document.getElementById('sessionModal'))
        document.getElementById('sessionModal').classList.remove('open');
}

// ── Live feed ──
let liveTimer = null, liveMaxId = <?= $max_id ?>, liveCount = 0;
document.getElementById('liveToggle').addEventListener('click', function() {
    if (liveTimer) {
        clearInterval(liveTimer);
        liveTimer = null;
        this.textContent = 'Start Live';
        this.classList.remove('on');
        document.getElementById('liveDot').classList.remove('active');
        document.getElementById('liveStatus').textContent = 'Live feed off';
    } else {
        this.textContent = 'Stop Live';
        this.classList.add('on');
        document.getElementById('liveDot').classList.add('active');
        document.getElementById('liveStatus').textContent = 'Polling every 5s…';
        pollLive();
        liveTimer = setInterval(pollLive, 5000);
    }
});
function pollLive() {
    fetch('<?= $page_file ?>?ajax=live&after=' + liveMaxId)
        .then(r => r.json())
        .then(events => {
            if (!events.length) return;
            liveMaxId = Math.max(liveMaxId, ...events.map(e => e.id));
            liveCount += events.length;
            document.getElementById('liveCount').textContent = '+' + liveCount + ' new since page load';
            const tbody = document.getElementById('liveRows');
            events.forEach(ev => {
                const shortU = u => { try { const p=new URL(u); return p.pathname+(p.search||''); } catch(e){ return u; } };
                const typeBadge = ev.event_type==='pageview' ? '<span class="badge-pv">PV</span>' : ev.event_type==='click' ? '<span class="badge-cl">CLK</span>' : '<span class="badge-oth">'+ev.event_type.toUpperCase()+'</span>';
                const userCell = ev.account_id
                    ? `<span class="user-tag" onclick="filterUser('${ev.display_name||''}')">${ev.display_name||'User'}</span>`
                    : `<span class="guest-tag">Guest <span style="font-size:0.65rem;opacity:0.5;">${(ev.session_id||'').slice(0,8)}…</span></span>`;
                const actionCell = ev.event_type==='pageview'
                    ? `<span style="color:#6b7280;font-size:0.7rem;">${ev.page_title||''}</span>`
                    : ev.element_text ? `"${ev.element_text.slice(0,40)}"` : '—';
                const tr = document.createElement('tr');
                tr.classList.add('new-row');
                tr.innerHTML = `
                    <td class="time-cell">${ev.created_at.slice(11,19)}<br><span style="font-size:0.65rem;">${ev.created_at.slice(5,10)}</span><br>
                        <span class="sess-chip" onclick="openSession('${ev.session_id||''}')">SID ${(ev.session_id||'').slice(0,6)}…</span></td>
                    <td>${typeBadge}</td>
                    <td>${userCell}</td>
                    <td class="url-cell" title="${ev.page_url}">${shortU(ev.page_url)}</td>
                    <td class="action-cell">${actionCell}</td>
                    <td class="ip-cell">${ev.ip||'—'}</td>`;
                tbody.insertBefore(tr, tbody.firstChild);
            });
        })
        .catch(() => {});
}

// ── Retargeting panel ──
function retargetPage(path) {
    document.getElementById('retargetUrl').value = path;
    document.getElementById('retargetPanel').scrollIntoView({ behavior:'smooth', block:'start' });
    document.getElementById('retargetUrl').focus();
}
function runRetarget() {
    const url = document.getElementById('retargetUrl').value.trim();
    const dr  = document.getElementById('retargetDr').value;
    const res = document.getElementById('retargetResults');
    if (!url) return;
    res.style.display = 'block';
    res.innerHTML = '<div style="color:#6b7280;padding:0.5rem 0;">Searching…</div>';
    fetch('<?= $page_file ?>?ajax=retarget&url=' + encodeURIComponent(url) + '&dr=' + dr)
        .then(r => r.json())
        .then(data => {
            const users = data.users || [];
            const guests = data.guest_sessions || 0;
            let html = `<div>
                <span class="rt-stat"><span style="color:#a78bfa">&#9679;</span> <strong>${users.length}</strong> logged-in users</span>
                <span class="rt-stat"><span style="color:#6b7280">&#9679;</span> <strong>${guests}</strong> guest sessions</span>
                ${users.length ? `<button class="btn-export-rt" onclick="exportRetargetCsv()"><i class="bi bi-download"></i> Export CSV</button>` : ''}
            </div>`;
            if (users.length) {
                html += `<table class="retarget-table" style="margin-top:0.75rem;" id="rtTable">
                    <thead><tr><th>#</th><th>Display Name</th><th>Email</th><th>Visits</th><th>Last Seen</th></tr></thead>
                    <tbody>`;
                users.forEach((u, i) => {
                    html += `<tr>
                        <td style="color:#6b7280;">${i+1}</td>
                        <td><span class="user-tag" onclick="filterUser('${u.display_name||''}')">${u.display_name||'—'}</span></td>
                        <td style="color:#9ca3af;">${u.email||'—'}</td>
                        <td><span class="count">${u.visits}</span></td>
                        <td style="color:#6b7280;">${(u.last_seen||'').slice(0,16)}</td>
                    </tr>`;
                });
                html += '</tbody></table>';
            } else {
                html += '<div style="color:#6b7280;font-size:0.78rem;padding:0.5rem 0;">No logged-in users found for this URL.</div>';
            }
            res.innerHTML = html;
            window._rtData = users;
        })
        .catch(() => { res.innerHTML = '<div style="color:#f87171;">Request failed.</div>'; });
}

// ── PALANTIR panel collapse ──
function palToggle(id) {
    const sec = document.getElementById(id);
    if (!sec) return;
    const hdr  = sec.querySelector('.palantir-header');
    const body = sec.querySelector('.palantir-body');
    hdr.classList.toggle('collapsed');
    body.classList.toggle('hidden');
    try {
        const s = JSON.parse(localStorage.getItem('_pal') || '{}');
        s[id] = hdr.classList.contains('collapsed');
        localStorage.setItem('_pal', JSON.stringify(s));
    } catch(e) {}
}
document.addEventListener('DOMContentLoaded', function() {
    let s = {};
    try { s = JSON.parse(localStorage.getItem('_pal') || '{}'); } catch(e) {}
    ['pal-goals','pal-cohort','pal-identity','pal-funnels','pal-segments','pal-alerts'].forEach(function(id) {
        if (s[id]) {
            const sec = document.getElementById(id);
            if (!sec) return;
            sec.querySelector('.palantir-header').classList.add('collapsed');
            sec.querySelector('.palantir-body').classList.add('hidden');
        }
    });
    // Auto-expand and scroll to hash target
    if (location.hash && location.hash.startsWith('#pal-')) {
        const el = document.getElementById(location.hash.slice(1));
        if (el) {
            el.querySelector('.palantir-header').classList.remove('collapsed');
            el.querySelector('.palantir-body').classList.remove('hidden');
            setTimeout(() => el.scrollIntoView({behavior:'smooth', block:'start'}), 150);
        }
    }
});

function exportRetargetCsv() {
    const data = window._rtData || [];
    if (!data.length) return;
    const rows = [['account_id','display_name','email','visits','last_seen']];
    data.forEach(u => rows.push([u.account_id||'', u.display_name||'', u.email||'', u.visits||'', u.last_seen||'']));
    const csv = rows.map(r => r.map(v => '"'+String(v).replace(/"/g,'""')+'"').join(',')).join('\n');
    const a = document.createElement('a');
    a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    a.download = 'retarget_' + Date.now() + '.csv';
    a.click();
}
</script>
