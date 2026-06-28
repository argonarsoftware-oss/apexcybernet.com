<?php
require_once __DIR__ . '/includes/db.php';

// Check if already exists
$check = $pdo->prepare("SELECT id FROM accounts WHERE LOWER(display_name) = 'hillfront'");
$check->execute();
if ($check->fetch()) {
    echo "Account 'hillfront' already exists.\n";
    exit;
}

$hash     = password_hash('hillfront2026', PASSWORD_DEFAULT);
$ref_code = 'ACC-' . strtoupper(bin2hex(random_bytes(4)));

$pdo->prepare("INSERT INTO accounts (email, display_name, password_hash, ref_code, ref_type, claim_status)
               VALUES (?, 'hillfront', ?, ?, 'team', 'approved')")
    ->execute(['hillfront@apexcybernet.com', $hash, $ref_code]);

echo "Created merchant account:\n";
echo "  Username: hillfront\n";
echo "  Password: hillfront2026\n";
echo "  (Change the password after logging in)\n";
