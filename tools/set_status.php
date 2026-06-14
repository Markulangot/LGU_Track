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

$id = $argv[1] ?? 73;
$status = $argv[2] ?? 'for_reading';
try {
    $stmt = $pdo->prepare("UPDATE ordinances SET status = ? WHERE id = ?");
    $stmt->execute([$status, (int)$id]);
    echo json_encode(['status'=>'ok','id'=>$id,'new_status'=>$status,'rows'=>$stmt->rowCount()]);
} catch (Exception $e) {
    echo json_encode(['error'=>$e->getMessage()]);
}
