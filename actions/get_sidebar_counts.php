<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

try {
    // Consider 'published' statuses as those visible in the public DB
    $published_statuses = "('approved','active')";

    // Total published ordinances (from ordinances table)
    $total = $pdo->query("SELECT COUNT(*) FROM ordinances WHERE status IN $published_statuses")->fetchColumn();

    // For Reading counts (ordinances under review)
    $pending = $pdo->query("SELECT COUNT(*) FROM ordinances WHERE status IN ('for_reading','under_review','draft','1st_reading','2nd_reading','3rd_reading')")->fetchColumn();

    // Archives (published ordinances that are repealed)
    $archives = $pdo->query("SELECT COUNT(*) FROM ordinances WHERE status = 'repealed'")->fetchColumn();

    // Resolutions published (from resolutions table if exists)
    try {
        $resolutions = $pdo->query("SELECT COUNT(*) FROM resolutions WHERE status IN $published_statuses")->fetchColumn();
    } catch (Exception $e) {
        // Fallback: count RES- prefixed records in ordinances for backwards compatibility
        $resolutions = $pdo->query("SELECT COUNT(*) FROM ordinances WHERE ordinance_number LIKE 'RES-%' AND status IN $published_statuses")->fetchColumn();
    }

    // Bin count (if table exists)
    try {
        $bin = $pdo->query("SELECT COUNT(*) FROM recycle_bin")->fetchColumn();
    } catch (Exception $e) {
        $bin = 0;
    }

    echo json_encode([
        'status' => 'success',
            'counts' => [
            'total' => (int)$total,
            'resolutions' => (int)$resolutions,
            'pending' => (int)$pending,
            'archives' => (int)$archives,
            'bin' => (int)$bin
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
