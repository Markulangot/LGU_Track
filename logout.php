<?php
session_start();
require_once 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    // Log activity
    $log_stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, description) VALUES (?, 'logout', ?)");
    $log_stmt->execute([$_SESSION['user_id'], "User {$_SESSION['username']} logged out"]);
}

session_destroy();
header('Location: login.php');
exit;
?>
