<?php
require_once __DIR__ . '/common_init.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attack_type'])) {
    $attack_type = $_POST['attack_type'];

    // 【変更】有効化/無効化の対象となる演習のリストを更新
    $all_attack_modes = [
        'dictionary_attack_enabled', 
        'bruteforce_enabled',
        'keylogger_enabled',
        'trusted_admin_bypass_enabled',
        'ransomware_enabled',
        'tamper_enabled'

    ];

    if ($attack_type === 'all_enable') {
        // 全て有効化
        foreach ($all_attack_modes as $mode) {
            $_SESSION[$mode] = true;
        }
    } elseif ($attack_type === 'all_disable') {
        // 全て無効化
        foreach ($all_attack_modes as $mode) {
            $_SESSION[$mode] = false;
        }
    } else {
        // 個別切り替え
        $session_key = $attack_type . '_enabled';
        $is_enabled = $_SESSION[$session_key] ?? false;
        $_SESSION[$session_key] = !$is_enabled;
    }
}

header('Location: simulation_tools.php');
exit;