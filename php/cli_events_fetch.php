<?php
// cli_events_fetch.php — 防御モニタ用の新着取得API
require_once __DIR__.'/db.php';
header('Content-Type: application/json; charset=utf-8');

session_start();
// 参照は admin だけに制限（必要に応じて講師ロールなども許可）
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'forbidden']);
    exit;
}

$since = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;

$sql = "SELECT id, created_at, event_type, meta, ip
          FROM cli_events
         WHERE id > :since
         ORDER BY id ASC
         LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':since', $since, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok'=>true,'events'=>$rows]);
