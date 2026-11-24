<?php
// C:\xampp\htdocs\it_inventory\modules\documents\view_file.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
$force_download = isset($_GET['download']) && $_GET['download'] === '1';

if ($id <= 0) {
    die("Invalid document ID.");
}

$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$doc = $stmt->fetch();

if (!$doc) {
    die("Document record not found.");
}

$real_path = __DIR__ . '/../../uploads/documents/' . basename($doc['file_path']);

if (!file_exists($real_path)) {
    die("File not found on storage server.");
}

if (ob_get_level()) {
    ob_end_clean();
}

$disposition = $force_download ? 'attachment' : 'inline';
$safe_filename = addslashes($doc['original_name']);

header('Content-Description: File Transfer');
header('Content-Type: ' . $doc['mime_type']);
header('Content-Disposition: ' . $disposition . '; filename="' . $safe_filename . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($real_path));

readfile($real_path);
exit;