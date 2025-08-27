<?php
// ids_events.php
header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 必要情報だけ読む
$sim_ip = $_SESSION['simulated_ip'] ?? null;

// ★ すぐに解放
session_write_close();

require_once __DIR__ . '/db.php'; // これ経由で waf.php が読み込まれ、log_attack() が使える想定

// JSONのみ受け付ける
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
    stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$attack_type = isset($data['attack_type']) ? (string)$data['attack_type'] : 'Bruteforce Activity';
$detail      = isset($data['detail'])      ? (string)$data['detail']      : '';
$status_code = isset($data['status_code']) ? (int)$data['status_code']    : 200;

$ip_for_note = $sim_ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

// 連投簡易スロットリング（セッションを使わずにIP×タイプで軽く抑制したい場合はDB等で）
// 今回は最小修正のため未実装 or 必要なら別テーブルで実装

try {
    if (function_exists('log_attack')) {
        $pattern_note = 'ids_events.php';
        log_attack($pdo, $attack_type, $detail, $pattern_note, $status_code);
        echo json_encode(['ok' => true]);
        exit;
    } else {
        throw new RuntimeException('log_attack() is not available');
    }
} catch (Throwable $e) {
    error_log('IDS_EVENT_LOG_FAIL: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
    exit;
}
