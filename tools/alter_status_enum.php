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

try {
    $pdo->exec("ALTER TABLE ordinances MODIFY COLUMN status ENUM('active','approved','rejected','for_reading','under_review','draft','1st_reading','2nd_reading','3rd_reading','amended','repealed') DEFAULT 'draft'");
    $updated = $pdo->exec("UPDATE ordinances SET status = 'for_reading' WHERE status = '' OR status IS NULL");
    echo json_encode(['status'=>'ok','updated_rows'=>$updated]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error'=>$e->getMessage()]);
    exit(1);
}
