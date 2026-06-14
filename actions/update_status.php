<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id     = (int)($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$note   = trim($_POST['note'] ?? '');
$type   = trim($_POST['type'] ?? 'ordinance');

// Allow legacy and new reading-stage statuses
// Allow legacy and new reading-stage statuses
$allowed = ['active', 'approved', 'under_review', 'for_reading', 'draft', '1st_reading', '2nd_reading', '3rd_reading', 'rejected', 'amended', 'repealed'];
if (!$id || !in_array($status, $allowed)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Update status on appropriate table (ordinances or resolutions)
    if ($type === 'resolution') {
        $stmt = $pdo->prepare("UPDATE resolutions SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);

        $ord = $pdo->prepare("SELECT resolution_number FROM resolutions WHERE id = ?");
        $ord->execute([$id]);
        $ord_num = $ord->fetchColumn() ?: "RES-$id";
    } else {
        $stmt = $pdo->prepare("UPDATE ordinances SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);

        // Fetch ordinance number for log
        $ord = $pdo->prepare("SELECT ordinance_number FROM ordinances WHERE id = ?");
        $ord->execute([$id]);
        $ord_num = $ord->fetchColumn() ?: "ORD-$id";
    }

    // Map status to human-readable action
    $action_map = [
        'active'       => 'approved',
        'approved'     => 'approved',
        'under_review' => 'marked_under_review',
        'draft'        => 'revision_requested',
        'for_reading'  => 'marked_under_review',
        '1st_reading'  => 'marked_under_review',
        '2nd_reading'  => 'marked_under_review',
        '3rd_reading'  => 'marked_under_review',
        'rejected'     => 'rejected',
        'amended'      => 'marked_amended',
        'repealed'     => 'repealed',
    ];
    $action = $action_map[$status] ?? 'status_changed';

    // Log the action
    $desc = "Status changed to '$status'" . ($note ? ": $note" : "");
    $target_type = ($type === 'resolution') ? 'resolution' : 'ordinance';
    $log  = $pdo->prepare("INSERT INTO activity_log (user_id, action, target_type, target_id, target_name, description) VALUES (?, ?, ?, ?, ?, ?)");
    $log->execute([$_SESSION['user_id'], $action, $target_type, $id, $ord_num, $desc]);

    $pdo->commit();
    echo json_encode(['status' => 'success', 'new_status' => $status, 'ord_num' => $ord_num]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
