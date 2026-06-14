<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

try {
    check_rate_limit($pdo, 'export');
} catch (Exception $e) {
    exit($e->getMessage());
}

// Replicate filtering logic from database.php
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$year_filter = isset($_GET['year']) ? trim($_GET['year']) : '';
$doc_type_filter = isset($_GET['doc_type']) ? trim($_GET['doc_type']) : '';
$selected_tags = isset($_GET['tags']) ? (is_array($_GET['tags']) ? $_GET['tags'] : explode(',', $_GET['tags'])) : [];
$selected_tags = array_filter($selected_tags);

$query = "
    FROM ordinances o
    LEFT JOIN ordinance_tags ot ON o.id = ot.ordinance_id
    LEFT JOIN tags t ON ot.tag_id = t.id
    WHERE 1=1
";
$params = [];

if ($search) {
    $query .= " AND (o.ordinance_number LIKE ? OR o.title LIKE ? OR o.description LIKE ? OR o.id IN (SELECT ot2.ordinance_id FROM ordinance_tags ot2 JOIN tags t2 ON ot2.tag_id = t2.id WHERE t2.name LIKE ?))";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}
if ($status_filter) { $query .= " AND o.status = ?"; $params[] = $status_filter; }
if ($year_filter) { $query .= " AND YEAR(o.date_enacted) = ?"; $params[] = $year_filter; }
if ($doc_type_filter) {
    if ($doc_type_filter === 'hard') { $query .= " AND o.hard_copy_path IS NOT NULL AND o.hard_copy_path != ''"; }
    elseif ($doc_type_filter === 'soft') { $query .= " AND o.soft_copy IS NOT NULL AND o.soft_copy != ''"; }
}
if (!empty($selected_tags)) {
    foreach ($selected_tags as $tag_name) {
        $query .= " AND o.id IN (SELECT ot3.ordinance_id FROM ordinance_tags ot3 JOIN tags t3 ON ot3.tag_id = t3.id WHERE t3.name = ?)";
        $params[] = $tag_name;
    }
}

$sql = "
    SELECT o.ordinance_number, o.title, o.description, o.date_enacted, o.status, o.department, o.main_author, 
           GROUP_CONCAT(DISTINCT t.name SEPARATOR '; ') as tags
    " . $query . " 
    GROUP BY o.id 
    ORDER BY o.date_enacted DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Generate CSV
$filename = "ordinances_export_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Headers as per spec: Number, Title, Description, Date Enacted, Status, Department, Sponsor, Tags
fputcsv($output, ['Number', 'Title', 'Description', 'Date Enacted', 'Status', 'Department', 'Sponsor', 'Tags']);

foreach ($records as $row) {
    fputcsv($output, $row);
}

fclose($output);

// Log activity
$log_stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, description) VALUES (?, 'export', ?)");
$log_stmt->execute([$_SESSION['user_id'], "Exported ordinance database to CSV"]);
exit;
?>
