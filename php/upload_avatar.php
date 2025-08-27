<?php
require_once __DIR__ . '/common_init.php';
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $upload_dir = __DIR__ . '/uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file = $_FILES['avatar'];
    $file_name = basename($file['name']);
    $target_path = $upload_dir . $file_name;

    // 【脆弱なコード】ファイルの種類を全くチェックせずに、そのまま保存する
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // DBにファイルパスを保存
        $relative_path = 'uploads/' . $file_name;
        $stmt = $pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
        $stmt->execute([$relative_path, $_SESSION['user_id']]);
    }
}

header('Location: profile.php');
exit;