<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $type = isset($_POST['type']) && $_POST['type'] === 'resolution' ? 'resolution' : 'ordinance';

    try {
        $pdo->beginTransaction();

        if ($type === 'resolution') {
            $stmt = $pdo->prepare("SELECT * FROM resolutions WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) throw new Exception('Resolution not found');

            $tags = $pdo->prepare("SELECT tag_id FROM resolution_tags WHERE resolution_id = ?");
            $tags->execute([$id]);
            $tag_ids = $tags->fetchAll(PDO::FETCH_COLUMN);
        } else {
            // ordinance
            $stmt = $pdo->prepare("SELECT * FROM ordinances WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) throw new Exception('Ordinance not found');

            $tags = $pdo->prepare("SELECT tag_id FROM ordinance_tags WHERE ordinance_id = ?");
            $tags->execute([$id]);
            $tag_ids = $tags->fetchAll(PDO::FETCH_COLUMN);
        }

        // Ensure recycle_bin table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS recycle_bin (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_table VARCHAR(100),
            original_id INT,
            data LONGTEXT,
            deleted_by INT,
            deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $data = [
            'row' => $row,
            'tag_ids' => $tag_ids
        ];

        $ins = $pdo->prepare("INSERT INTO recycle_bin (original_table, original_id, data, deleted_by) VALUES (?, ?, ?, ?)");
        $ins->execute([$type === 'resolution' ? 'resolutions' : 'ordinances', $id, json_encode($data), $_SESSION['user_id']]);

        // Remove tags and row from source table
        if ($type === 'resolution') {
            $pdo->prepare("DELETE FROM resolution_tags WHERE resolution_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM resolutions WHERE id = ?")->execute([$id]);
        } else {
            $pdo->prepare("DELETE FROM ordinance_tags WHERE ordinance_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM ordinances WHERE id = ?")->execute([$id]);
        }

        // Log
        $log_stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, target_type, target_id, target_name, description) VALUES (?, 'move_to_bin', ?, ?, ?, ?)");
        $log_stmt->execute([$_SESSION['user_id'], $type === 'resolution' ? 'resolution' : 'ordinance', $id, $row[$type === 'resolution' ? 'resolution_number' : 'ordinance_number'], "Moved to Bin: " . ($row[$type === 'resolution' ? 'resolution_number' : 'ordinance_number'])]);

        $redirect = isset($_POST['redirect']) ? trim($_POST['redirect']) : '';
        if (!empty($redirect) && strpos($redirect, '/') !== 0 && strpos($redirect, '../') !== 0) {
            $redirect = '';
        }

        $pdo->commit();
        
        if (!empty($redirect)) {
            $separator = (strpos($redirect, '?') !== false) ? '&' : '?';
            header('Location: ' . $redirect . $separator . 'moved=1');
        } else {
            header('Location: ../database.php' . ($type === 'resolution' ? '?type=resolution&moved=1' : '?moved=1'));
        }
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $redirect = isset($_POST['redirect']) ? trim($_POST['redirect']) : '';
        if (!empty($redirect) && strpos($redirect, '/') !== 0 && strpos($redirect, '../') !== 0) {
            $redirect = '';
        }
        if (!empty($redirect)) {
            $separator = (strpos($redirect, '?') !== false) ? '&' : '?';
            header('Location: ' . $redirect . $separator . 'error=' . urlencode($e->getMessage()));
        } else {
            header('Location: ../database.php' . ($type === 'resolution' ? '?type=resolution&error=' : '?error=') . urlencode($e->getMessage()));
        }
        exit;
    }
}

header('Location: ../database.php');
exit;

?>
