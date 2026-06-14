<?php
require_once 'includes/db.php';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO ordinances (ordinance_number, title, description, date_enacted, status, department, main_author, soft_copy) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    for ($i = 1; $i <= 50; $i++) {
        $num = "TEST-" . str_pad($i, 4, '0', STR_PAD_LEFT);
        $title = "Mock Ordinance #$i";
        $desc = "This is a system-generated test ordinance for database performance testing.";
        $date = date('Y-m-d', strtotime("-$i days"));
        $status = ['active', 'under_review', 'draft', 'amended', 'repealed'][array_rand(['active', 'under_review', 'draft', 'amended', 'repealed'])];
        $dept = "Department " . rand(1, 5);
        $author = "Author " . rand(1, 10);
        $content = "Mock content for ordinance $i";

        $stmt->execute([$num, $title, $desc, $date, $status, $dept, $author, $content]);
    }

    $pdo->commit();
    echo "Successfully inserted 50 mock ordinances.";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}
?>
