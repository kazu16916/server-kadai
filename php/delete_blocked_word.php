<?php
require_once __DIR__ . '/common_init.php';
require 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    if (!empty($id)) {
        $stmt = $pdo->prepare("DELETE FROM waf_blacklist WHERE id = ?");
        $stmt->execute([$id]);
    }
}

header('Location: waf_settings.php');
exit;
