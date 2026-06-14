<?php
require 'includes/db.php';
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'juan'");
$stmt->execute([password_hash('password123', PASSWORD_DEFAULT)]);
echo "Password reset for juan\n";
