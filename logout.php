<?php
require_once __DIR__ . '/includes/db.php';
unset($_SESSION['account_id']);
session_destroy();
header('Location: ' . base_url());
exit;
