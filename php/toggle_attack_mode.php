<?php
require_once __DIR__ . '/common_init.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attack_type'])) {
    $attack_type = $_POST['attack_type'];

    $all_attack_modes = [
        'dictionary_attack_enabled', 
        'bruteforce_enabled',
        'keylogger_enabled',
        'trusted_admin_bypass_enabled',
        'ransomware_enabled',
        'tamper_enabled',
        'reverse_bruteforce_enabled',
        'joe_account_attack_enabled'
    ];

    if ($attack_type === 'all_enable') {
        foreach ($all_attack_modes as $mode) {
            $_SESSION[$mode] = true;
        }
        // ★ 追加：フル演習モード時は常時ハンバーガー表示
        $_SESSION['force_hamburger'] = true;

    } elseif ($attack_type === 'all_disable') {
        foreach ($all_attack_modes as $mode) {
            $_SESSION[$mode] = false;
        }
        // ★ 追加：通常表示に戻す
        $_SESSION['force_hamburger'] = false;

    } else {
        $session_key = $attack_type . '_enabled';
        $is_enabled = $_SESSION[$session_key] ?? false;
        $_SESSION[$session_key] = !$is_enabled;
        // 個別切替では force_hamburger は変更しない
    }
}

header('Location: simulation_tools.php');
exit;
