<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

$bin_id = isset($_GET['bin_id']) ? (int)$_GET['bin_id'] : 0;
if (!$bin_id) {
    http_response_code(400);
    echo 'Missing bin_id';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM recycle_bin WHERE id = ?");
$stmt->execute([$bin_id]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$entry) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$data = json_decode($entry['data'], true);
$row = $data['row'] ?? null;
if (!$row) {
    http_response_code(404);
    echo 'No attachment info';
    exit;
}

$path = $row['hard_copy_path'] ?? '';
if (!$path) {
    http_response_code(404);
    echo 'No file attached';
    exit;
}

// Prevent external redirects - if path is absolute URL, redirect
if (preg_match('#^https?://#i', $path)) {
    header('Location: ' . $path);
    exit;
}

// Build absolute filesystem path
$file_path = realpath(__DIR__ . '/../' . ltrim($path, '/'));
if (!$file_path || !file_exists($file_path)) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

// Ensure file is inside the uploads directory for safety
$uploads_dir = realpath(__DIR__ . '/../uploads');
if ($uploads_dir && strpos($file_path, $uploads_dir) !== 0) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

$filename = basename($file_path);
$fsize = filesize($file_path);

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $fsize);
header('Cache-Control: public, must-revalidate');
readfile($file_path);
exit;

?>
