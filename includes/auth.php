<?php
// Auth helpers — must be included after db.php

function current_user($pdo) {
    if (empty($_SESSION['account_id'])) return null;
    static $user = false;
    if ($user !== false) return $user;
    $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
    $stmt->execute([$_SESSION['account_id']]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

function require_login() {
    if (empty($_SESSION['account_id'])) {
        header('Location: ' . base_url('login.php'));
        exit;
    }
}

function is_ref_claimed($pdo, $ref_code) {
    $stmt = $pdo->prepare("SELECT 1 FROM accounts WHERE ref_code = ?");
    $stmt->execute([$ref_code]);
    return (bool) $stmt->fetch();
}
