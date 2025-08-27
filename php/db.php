<?php
require_once __DIR__ . '/common_init.php';
$host = 'db';
$db   = 'voting_app';
$user = 'appuser';
$pass = 'apppass';

$retries = 5;
while ($retries > 0) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
        break; // 成功したらループ終了
    } catch (PDOException $e) {
        $retries--;
        if ($retries === 0) {
            die("DB接続エラー: " . $e->getMessage());
        }
        sleep(3); // 待機して再試行
    }
}

require_once __DIR__ . '/waf.php';
run_waf($pdo);
?>
