<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bin_id'])) {
    $bin_id = (int)$_POST['bin_id'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM recycle_bin WHERE id = ?");
        $stmt->execute([$bin_id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entry) throw new Exception('Bin entry not found');

        $data = json_decode($entry['data'], true);
        $row = $data['row'] ?? null;

        // Attempt to delete any attached hard copy file for cleanup
        if ($row && !empty($row['hard_copy_path']) && file_exists(__DIR__ . '/../' . $row['hard_copy_path'])) {
            @unlink(__DIR__ . '/../' . $row['hard_copy_path']);
        }

        // Delete bin record
        $pdo->prepare("DELETE FROM recycle_bin WHERE id = ?")->execute([$bin_id]);

        // Log
        $doc_number = $row['ordinance_number'] ?? ($row['resolution_number'] ?? 'unknown');
        $log_stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, target_type, target_id, target_name, description) VALUES (?, 'delete_permanent', 'recycle_bin', ?, ?, ?)");
        $log_stmt->execute([$_SESSION['user_id'], $bin_id, $doc_number, "Permanently deleted from Bin: " . $doc_number]);

        header('Location: ../bin.php?deleted=1');
        exit;
    } catch (Exception $e) {
        header('Location: ../bin.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

header('Location: ../bin.php');
exit;
?>
