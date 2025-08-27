<?php
require_once __DIR__ . '/common_init.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

// 現在の状態を反転させる
$is_enabled = $_SESSION['bruteforce_enabled'] ?? false;
$_SESSION['bruteforce_enabled'] = !$is_enabled;

// 【変更】シミュレーションツールページに戻る
header('Location: simulation_tools.php');
exit;