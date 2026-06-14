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
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM recycle_bin WHERE id = ?");
        $stmt->execute([$bin_id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entry) throw new Exception('Bin entry not found');

        $data = json_decode($entry['data'], true);
        if (!$data || !isset($data['row'])) throw new Exception('Invalid bin data');

        $row = $data['row'];

        // Re-insert based on original_table
        $orig_table = $entry['original_table'] ?? 'ordinances';
        if ($orig_table === 'resolutions') {
            $cols = ['resolution_number','title','description','date_enacted','status','department','main_author','co_authors','soft_copy','hard_copy_path','notes','created_at','updated_at'];
            $values = [];
            foreach ($cols as $c) {
                $values[] = isset($row[$c]) ? $row[$c] : null;
            }
            $placeholders = rtrim(str_repeat('?,', count($cols)), ',');
            $ins = $pdo->prepare("INSERT INTO resolutions (" . implode(',', $cols) . ") VALUES ($placeholders)");
            $ins->execute($values);
            $new_id = $pdo->lastInsertId();

            // Restore tags
            if (!empty($data['tag_ids'])) {
                $tag_stmt = $pdo->prepare("INSERT INTO resolution_tags (resolution_id, tag_id) VALUES (?, ?)");
                foreach ($data['tag_ids'] as $t) {
                    $tag_stmt->execute([$new_id, $t]);
                }
            }

            // Remove bin entry
            $pdo->prepare("DELETE FROM recycle_bin WHERE id = ?")->execute([$bin_id]);

            // Log
            $log_stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, target_type, target_id, target_name, description) VALUES (?, 'recover', 'resolution', ?, ?, ?)");
            $log_stmt->execute([$_SESSION['user_id'], $new_id, $row['resolution_number'] ?? ($row['ordinance_number'] ?? ''), "Recovered from Bin: " . ($row['resolution_number'] ?? ($row['ordinance_number'] ?? ''))]);

            $pdo->commit();
            header('Location: ../bin.php?restored=1');
            exit;
        } else {
            // Re-insert ordinance
            $cols = ['ordinance_number','title','description','date_enacted','status','department','main_author','co_authors','soft_copy','hard_copy_path','notes','created_at','updated_at'];
            $values = [];
            foreach ($cols as $c) {
                $values[] = isset($row[$c]) ? $row[$c] : null;
            }

            $placeholders = rtrim(str_repeat('?,', count($cols)), ',');
            $ins = $pdo->prepare("INSERT INTO ordinances (" . implode(',', $cols) . ") VALUES ($placeholders)");
            $ins->execute($values);
            $new_id = $pdo->lastInsertId();

            // Restore tags
            if (!empty($data['tag_ids'])) {
                $tag_stmt = $pdo->prepare("INSERT INTO ordinance_tags (ordinance_id, tag_id) VALUES (?, ?)");
                foreach ($data['tag_ids'] as $t) {
                    $tag_stmt->execute([$new_id, $t]);
                }
            }

            // Remove bin entry
            $pdo->prepare("DELETE FROM recycle_bin WHERE id = ?")->execute([$bin_id]);

            // Log
            $log_stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, target_type, target_id, target_name, description) VALUES (?, 'recover', 'ordinance', ?, ?, ?)");
            $log_stmt->execute([$_SESSION['user_id'], $new_id, $row['ordinance_number'], "Recovered from Bin: " . $row['ordinance_number']]);

            $pdo->commit();
            header('Location: ../bin.php?restored=1');
            exit;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header('Location: ../bin.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

header('Location: ../bin.php');
exit;
?>
