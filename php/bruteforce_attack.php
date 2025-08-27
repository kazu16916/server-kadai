<?php
require_once __DIR__ . '/common_init.php';
require 'db.php'; // この中でwaf.phpが読み込まれ、log_attack関数が使えるようになる

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';

if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'ユーザー名が指定されていません。']);
    exit;
}

// 【ここから追加】
// --- ブルートフォース攻撃の開始をIDSに記録 ---
// log_attack関数は waf.php で定義されている
log_attack(
    $pdo,                          // データベース接続
    'Bruteforce Simulation',      // 攻撃タイプ
    "Target: " . $username,       // 攻撃内容 (ターゲットユーザー)
    'Bruteforce Simulation Start',// 検知パターン
    200                            // このスクリプト自体のステータスコード
);
// --- 追加ここまで ---


// データベースから対象ユーザーのハッシュ化されたパスワードを取得
$stmt = $pdo->prepare("SELECT password FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'message' => '指定されたユーザーは存在しません。']);
    exit;
}

$hashed_password = $user['password'];

// ブルートフォース攻撃に使う、よくあるパスワードの辞書
$password_dictionary = [
    'password', '123456', '12345678', 'qwerty', 'test', 'admin', 'user'
];

// 辞書内のパスワードを一つずつ試行
foreach ($password_dictionary as $password_attempt) {
    usleep(250000); // 0.25秒待機
    if (password_verify($password_attempt, $hashed_password)) {
        echo json_encode(['success' => true, 'password' => $password_attempt]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => '辞書内のパスワードでは見つかりませんでした。']);