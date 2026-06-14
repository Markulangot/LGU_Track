<?php
/**
 * Test script: create a resolution and advance through reading stages.
 * Run: php actions/test_e2e_resolution.php
 */
require_once __DIR__ . '/../includes/db.php';

function logit($msg) { echo $msg . PHP_EOL; }

try {
    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS resolutions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        resolution_number VARCHAR(50) NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        date_enacted DATE,
        status VARCHAR(30) DEFAULT 'draft',
        department VARCHAR(100),
        main_author VARCHAR(100),
        co_authors TEXT,
        soft_copy LONGTEXT,
        hard_copy_path VARCHAR(255),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $timestamp = time();
    $res_num = 'RES-TEST-' . $timestamp;
    $title = 'E2E Test Resolution ' . date('Y-m-d H:i:s', $timestamp);

    // Insert resolution
    $stmt = $pdo->prepare("INSERT INTO resolutions (resolution_number, title, description, date_enacted, status, department, main_author, soft_copy) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$res_num, $title, 'Automated E2E test', date('Y-m-d'), 'for_reading', 'Test Dept', 'Test User', 'Test soft copy']);
    $res_id = $pdo->lastInsertId();
    logit("Created resolution $res_num (id: $res_id)");

    // Log creation
    $pdo->prepare("INSERT INTO activity_log (user_id, action, target_type, target_id, target_name, description) VALUES (?, 'create', 'resolution', ?, ?, ?)")->execute([1, $res_id, $res_num, 'E2E created']);

    $stages = ['1st_reading','2nd_reading','3rd_reading','approved'];
    foreach ($stages as $s) {
        $pdo->prepare("UPDATE resolutions SET status = ? WHERE id = ?")->execute([$s, $res_id]);
        $action = ($s === 'approved') ? 'approved' : 'marked_under_review';
        $pdo->prepare("INSERT INTO activity_log (user_id, action, target_type, target_id, target_name, description) VALUES (?, ?, 'resolution', ?, ?, ?)")->execute([1, $action, $res_id, $res_num, "Moved to $s"]);
        logit("Advanced $res_num -> $s");
    }

    // Final check
    $count = $pdo->query("SELECT COUNT(*) FROM resolutions WHERE status IN ('approved','active')")->fetchColumn();
    logit("Published resolutions (approved/active): $count");

    $row = $pdo->prepare("SELECT * FROM resolutions WHERE id = ?");
    $row->execute([$res_id]);
    $r = $row->fetch(PDO::FETCH_ASSOC);
    logit('Final record: ' . json_encode($r));

    exit(0);
} catch (Exception $e) {
    echo "E2E test failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
