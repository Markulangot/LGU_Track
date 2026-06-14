<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $type = isset($_POST['type']) && $_POST['type'] === 'resolution' ? 'resolution' : 'ordinance';

    if ($type === 'resolution') {
        $stmt = $pdo->prepare("SELECT resolution_number FROM resolutions WHERE id = ?");
        $stmt->execute([$id]);
        $num = $stmt->fetchColumn();
        if ($num) {
            $stmt = $pdo->prepare("DELETE FROM resolutions WHERE id = ?");
            $stmt->execute([$id]);
            $log_stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, target_type, target_id, target_name, description) VALUES (?, 'delete', 'resolution', ?, ?, ?)");
            $log_stmt->execute([$_SESSION['user_id'], $id, $num, "Deleted resolution: $num"]);
            header("Location: ../database.php?type=resolution&deleted=1");
            exit;
        }
    } else {
        $stmt = $pdo->prepare("SELECT ordinance_number FROM ordinances WHERE id = ?");
        $stmt->execute([$id]);
        $ord_number = $stmt->fetchColumn();

        if ($ord_number) {
            // Delete
            $stmt = $pdo->prepare("DELETE FROM ordinances WHERE id = ?");
            $stmt->execute([$id]);

            // Log activity
            $log_stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, target_type, target_id, target_name, description) VALUES (?, 'delete', 'ordinance', ?, ?, ?)");
            $log_stmt->execute([$_SESSION['user_id'], $id, $ord_number, "Deleted ordinance: $ord_number"]);
            
            header("Location: ../database.php?deleted=1");
            exit;
        }
    }
}

header("Location: ../database.php");
exit;
?>
