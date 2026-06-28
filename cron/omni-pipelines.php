<?php
/**
 * Omniscient pipeline runner — cron entry point.
 *
 * Hit from cron every 15 minutes:
 *   *\/15 * * * * curl -s "https://argonar.co/cron/omni-pipelines.php?k=argonar-omni-2026" >/dev/null 2>&1
 *
 * Or manually via browser (kirfenia session required) or CLI.
 *
 * This is a thin passthrough to admin/omni/pipelines/run.php so the runner
 * stays in one place and the cron entry is just the public URL.
 */

$token_ok = PHP_SAPI === 'cli' || (($_GET['k'] ?? '') === 'argonar-omni-2026');
if (!$token_ok) {
    http_response_code(403);
    exit("Forbidden\n");
}

$_GET['k'] = 'argonar-omni-2026';
require __DIR__ . '/../admin/omni/pipelines/run.php';
