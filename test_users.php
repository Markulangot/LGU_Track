<?php
require 'includes/db.php';
$users = $pdo->query('SELECT username, role FROM users')->fetchAll(PDO::FETCH_ASSOC);
print_r($users);

$has_user = false;
foreach ($users as $u) {
    if ($u['role'] === 'user') $has_user = true;
}

if (!$has_user) {
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role) VALUES ('testuser', ?, 'Test User', 'user')");
    $stmt->execute([password_hash('password123', PASSWORD_DEFAULT)]);
    echo "Created 'testuser' with password 'password123'\n";
}
