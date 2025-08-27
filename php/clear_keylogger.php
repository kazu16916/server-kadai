<?php
// clear_keylogger.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}
$log_file = __DIR__ . '/logs/keylogger.log';
@file_put_contents($log_file, '');
header('Location: attacker_console.php?success=' . urlencode('keyloggerログをクリアしました'));
exit;
