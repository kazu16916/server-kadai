<?php
require_once __DIR__ . '/common_init.php';
require 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pattern = trim($_POST['pattern']);
    $description = trim($_POST['description']);
    $action = $_POST['action'] === 'block' ? 'block' : 'detect';

    if (!empty($pattern)) {
        // is_custom = TRUE としてINSERTする
        $stmt = $pdo->prepare("INSERT INTO waf_blacklist (pattern, description, action, is_custom) VALUES (?, ?, ?, TRUE)");
        $stmt->execute([$pattern, $description, $action]);
    }
}

header('Location: waf_settings.php');
exit;
