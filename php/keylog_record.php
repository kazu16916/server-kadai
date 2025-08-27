<?php
// keylog_record.php
// 演習用キーロガーの収集エンドポイント。keylogger_enabled のときのみ受付。
// パスワードフィールドでも 1 キー単位で value を記録（生パスとしては保存しない目的ですが、1文字ずつ保存されます）

header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/db.php'; // これ経由で waf.php も読み込まれ、log_attack() が利用可

// キーロガーが有効な場合のみ受け付ける
$keylogger_enabled = $_SESSION['keylogger_enabled'] ?? false;
if (!$keylogger_enabled) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Keylogger disabled']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
    stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

// 期待パラメータ
$field     = isset($data['field']) ? (string)$data['field'] : '';
$key_code  = isset($data['code']) ? (string)$data['code'] : '';
$key_value = isset($data['value']) ? (string)$data['value'] : '';
$is_pwd    = isset($data['is_password']) ? (int)((bool)$data['is_password']) : 0;

// 簡易バリデーション
if ($field === '' || $key_code === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Missing params']);
    exit;
}

$ip  = $_SESSION['simulated_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$ua  = $_SESSION['simulated_user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A');

try {
    // DBへ書き込み（存在すれば）
    $pdo->prepare("
        INSERT INTO keylog_events (ip_address, user_agent, field, key_code, key_value, is_password, note)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $ip,
        $ua,
        $field,
        $key_code,
        mb_substr($key_value, 0, 1, 'UTF-8'), // 1 文字だけ念のため
        $is_pwd,
        'simulation'
    ]);
} catch (Throwable $e) {
    // 無視してIDSだけ記録でもOK
}

// IDS ログとしても記録しておく（可視化用途）
if (function_exists('log_attack')) {
    $detail = sprintf("Key:%s Val:%s Field:%s", $key_code, $key_value, $field);
    log_attack($pdo, 'Keylogger Capture', $detail, 'keylog_record.php', 200);
}

echo json_encode(['ok' => true]);
