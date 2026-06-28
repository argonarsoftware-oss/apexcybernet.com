<?php
require_once __DIR__ . '/../includes/db.php';

unset($_SESSION['admin_logged_in']);
session_destroy();

header('Location: ' . base_url('admin/'));
exit;
