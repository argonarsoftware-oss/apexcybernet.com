<?php
// pay.php — redirect to unified Send page in scan mode
require_once __DIR__ . '/layout.php';
$dest = m_base('send.php') . '?mode=scan';
header('Location: ' . $dest);
exit;
