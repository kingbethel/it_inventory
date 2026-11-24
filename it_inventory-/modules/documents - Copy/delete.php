<?php
// C:\xampp\htdocs\it_inventory\modules\documents\delete.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', 'Invalid document ID.');
    redirect('modules/documents/index.php');
}

$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$doc = $stmt->fetch();

if ($doc) {
    $real_path = __DIR__ . '/../../uploads/documents/' . basename($doc['file_path']);
    if (file_exists($real_path)) {
        unlink($real_path);
    }

    $del_stmt = $pdo->prepare("DELETE FROM documents WHERE id = :id");
    $del_stmt->execute([':id' => $id]);

    log_activity($pdo, $_SESSION['user_id'], 'DELETE_DOCUMENT', 'Document', $id, "Deleted document: {$doc['original_name']}");
    set_flash('success', "Document {$doc['original_name']} successfully deleted.");
} else {
    set_flash('danger', 'Document record not found.');
}

redirect('modules/documents/index.php');