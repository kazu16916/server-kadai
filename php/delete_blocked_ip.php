<?php
require_once __DIR__ . '/common_init.php';
require 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    
    if (!empty($id) && is_numeric($id)) {
        try {
            // カスタムルール（is_custom = TRUE）のみ削除可能
            $stmt = $pdo->prepare("DELETE FROM waf_ip_blocklist WHERE id = ? AND is_custom = TRUE");
            $stmt->execute([(int)$id]);
            
            if ($stmt->rowCount() > 0) {
                header('Location: waf_settings.php?success=' . urlencode('IPルールを削除しました。'));
            } else {
                header('Location: waf_settings.php?error=' . urlencode('削除対象のIPルールが見つかりませんでした。'));
            }
        } catch (PDOException $e) {
            error_log("Failed to delete IP rule: " . $e->getMessage());
            header('Location: waf_settings.php?error=' . urlencode('IPルールの削除に失敗しました。'));
        }
    } else {
        header('Location: waf_settings.php?error=' . urlencode('無効なIPルールIDです。'));
    }
} else {
    header('Location: waf_settings.php');
}
exit;