
<?php
// cli_attack_endpoint.php
// CLI攻撃からのHTTPリクエストを受け取り、IDSログに記録するエンドポイント

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/common_init.php';
require 'db.php';

// CLI攻撃演習が有効でない場合は404を返す（攻撃者に存在を隠す）
if (empty($_SESSION['cli_attack_enabled'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Not Found']);
    exit;
}

// POSTのみ受け付ける
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// JSON入力を解析
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// 必要なフィールドの検証
$required_fields = ['attack_type', 'target_ip', 'target_port', 'source_ip', 'user_agent'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

$attack_type = $data['attack_type'];
$target_ip = $data['target_ip'];
$target_port = (int)$data['target_port'];
$source_ip = $data['source_ip'];
$user_agent = $data['user_agent'];
$payload = $data['payload'] ?? '';
$scan_result = $data['scan_result'] ?? '';
$additional_info = $data['additional_info'] ?? '';

// 攻撃タイプに応じたレスポンス生成
$responses = [
    'port_scan' => [
        'status' => 'detected',
        'message' => 'Port scan detected and logged',
        'detected_ports' => [22, 80, 443, 3306, 8088],
        'honeypot_activated' => true
    ],
    'ssh_bruteforce' => [
        'status' => 'blocked',
        'message' => 'SSH brute force attempt blocked',
        'failed_attempts' => rand(5, 20),
        'account_locked' => true
    ],
    'web_attack' => [
        'status' => 'filtered',
        'message' => 'Web attack filtered by WAF',
        'waf_rule_triggered' => 'SQL_INJECTION_DETECTED'
    ],
    'vulnerability_scan' => [
        'status' => 'monitored',
        'message' => 'Vulnerability scan detected',
        'services_probed' => ['http', 'https', 'ssh', 'mysql']
    ],
    'intrusion_attempt' => [
        'status' => 'alert',
        'message' => 'Intrusion attempt detected',
        'severity' => 'HIGH',
        'containment_active' => true
    ]
];

$response = $responses[$attack_type] ?? [
    'status' => 'unknown',
    'message' => 'Unknown attack type processed',
];

// IDSログに記録
try {
    if (function_exists('log_attack')) {
        $malicious_input = "CLI Attack - Type: $attack_type, Target: $target_ip:$target_port";
        if ($payload) $malicious_input .= ", Payload: " . substr($payload, 0, 200);
        if ($scan_result) $malicious_input .= ", Result: $scan_result";
        if ($additional_info) $malicious_input .= ", Info: $additional_info";
        
        // 攻撃元IPとしてCLI側のIPを記録
        $_SESSION['temp_cli_ip'] = $source_ip;
        $_SESSION['temp_cli_ua'] = $user_agent;
        
        log_attack($pdo, "CLI $attack_type Attack", $malicious_input, 'cli_attack_endpoint.php', 200);
        
        // 一時的なセッション変数をクリア
        unset($_SESSION['temp_cli_ip'], $_SESSION['temp_cli_ua']);
    }
} catch (Throwable $e) {
    error_log("Failed to log CLI attack: " . $e->getMessage());
}

// より詳細なログをattack_logsテーブルに直接記録
try {
    $stmt = $pdo->prepare(
        "INSERT INTO attack_logs (ip_address, user_id, attack_type, malicious_input, request_uri, user_agent, status_code, source_type, detected_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    
    $detailed_log = json_encode([
        'attack_type' => $attack_type,
        'target_ip' => $target_ip,
        'target_port' => $target_port,
        'payload' => $payload,
        'scan_result' => $scan_result,
        'additional_info' => $additional_info,
        'cli_timestamp' => date('c')
    ]);
    
    $stmt->execute([
        $source_ip,
        null, // CLI攻撃は通常認証なしユーザー
        "CLI Network Attack: " . ucfirst(str_replace('_', ' ', $attack_type)),
        $detailed_log,
        "/cli_attack_endpoint.php",
        $user_agent,
        200,
        'CLI External'
    ]);
    
} catch (PDOException $e) {
    error_log("Failed to log detailed CLI attack: " . $e->getMessage());
}

// レスポンス返却（攻撃結果のシミュレーション）
http_response_code(200);
echo json_encode($response, JSON_PRETTY_PRINT);
