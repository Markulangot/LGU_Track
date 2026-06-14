<?php
$host='127.0.0.1'; $db='Mam_track'; $user='root'; $pass=''; $charset='utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit(1);
}

$res = [];
$q1 = "SELECT id, ordinance_number, status, date_enacted FROM ordinances ORDER BY id DESC LIMIT 20";
$q2 = "SELECT status, COUNT(*) AS cnt FROM ordinances WHERE status IN ('under_review','draft','1st_reading','2nd_reading','3rd_reading') GROUP BY status";
$q3 = "SELECT id, user_id, action, target_id, target_name, description, timestamp FROM activity_log ORDER BY timestamp DESC LIMIT 20";

try {
    $res['latest'] = $pdo->query($q1)->fetchAll();
    $res['reading_counts'] = $pdo->query($q2)->fetchAll();
    $res['activity'] = $pdo->query($q3)->fetchAll();
    echo json_encode($res, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit(1);
}
