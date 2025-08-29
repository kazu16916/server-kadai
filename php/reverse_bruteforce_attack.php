<?php
// reverse_bruteforce_attack.php
// 逆ブルートフォース攻撃：1つのパスワードに対して複数のユーザー名を試行

require_once __DIR__ . '/common_init.php';
require 'db.php';

header('Content-Type: application/json; charset=utf-8');

// 逆ブルートフォース演習が有効でない場合は拒否
if (empty($_SESSION['reverse_bruteforce_enabled'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '逆総当たり演習は無効です。']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$password = $data['password'] ?? '';
$mode = $data['mode'] ?? 'auto'; // 'auto' | 'manual'
$batch_size = isset($data['batch_size']) ? max(1, min(50, (int)$data['batch_size'])) : 10;
$session_id = session_id();

if ($password === '') {
    echo json_encode(['success' => false, 'message' => 'パスワードを指定してください。']);
    exit;
}

// IDSログ - 攻撃開始
if (function_exists('log_attack')) {
    log_attack($pdo, 'Reverse Bruteforce Start', "target_password_length=" . strlen($password), 'reverse_bruteforce_attack.php', 200);
}

try {
    // ユーザー名辞書を取得（頻度順）
    $stmt = $pdo->prepare("
        SELECT u.username
        FROM users u
        LEFT JOIN username_dictionary d ON d.username = u.username
        ORDER BY COALESCE(d.frequency_rank, 9999) ASC, u.id ASC
        LIMIT 100
    ");
    $stmt->execute();
    $dictionary = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($dictionary)) {
        echo json_encode(['success' => false, 'message' => 'ユーザー名辞書が空です。']);
        exit;
    }

    $results = [];
    $attempt_count = 0;
    $success_count = 0;
    
    foreach ($dictionary as $username) {
        $attempt_count++;
        
        // 実際のログイン試行（パスワードベースの認証チェック）
        $login_success = check_login_credentials($pdo, $username, $password);
        
        if ($login_success) {
            $success_count++;
        }
        
        // ログに記録
        log_reverse_bruteforce_attempt($pdo, $session_id, $password, $username, $login_success, $attempt_count);
        
        $results[] = [
            'username' => $username,
            'success' => $login_success,
            'attempt_number' => $attempt_count,
            'timestamp' => date('H:i:s')
        ];
        
        // バッチサイズに達したら一旦結果を返す（段階的処理）
        if ($attempt_count >= $batch_size) {
            break;
        }
        
        // 負荷軽減のための短い待機
        usleep(50000); // 0.05秒
    }
    
    $has_more = $attempt_count < count($dictionary);
    $total_dictionary_size = count($dictionary);
    
    // 成功したアカウントがあればIDSログに詳細記録
    if ($success_count > 0) {
        $successful_accounts = array_filter($results, fn($r) => $r['success']);
        $usernames = array_column($successful_accounts, 'username');
        
        if (function_exists('log_attack')) {
            log_attack($pdo, 'Reverse Bruteforce Success', 
                "found_accounts=" . implode(',', $usernames) . ", attempts=" . $attempt_count, 
                'reverse_bruteforce_attack.php', 200);
        }
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'statistics' => [
            'attempts' => $attempt_count,
            'successful_logins' => $success_count,
            'success_rate' => $attempt_count > 0 ? round(($success_count / $attempt_count) * 100, 2) : 0,
            'dictionary_size' => $total_dictionary_size,
            'has_more' => $has_more
        ],
        'message' => $success_count > 0 
            ? "発見: {$success_count}個のアカウントがパスワード '{$password}' でログイン可能です"
            : "最初の{$attempt_count}個の試行では有効なアカウントは見つかりませんでした"
    ]);

} catch (Exception $e) {
    error_log("Reverse bruteforce error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'エラーが発生しました: ' . $e->getMessage()]);
}

/**
 * ログイン認証をチェック（平文パスワード対応）
 */
function check_login_credentials($pdo, $username, $password) {
    try {
        // SQLi演習モード: パスワードに 'OR 1=1 が含まれていたら突破できる
        if (!empty($_SESSION['reverse_bruteforce_enabled'])) {
            if (preg_match("/'\\s*OR\\s*1=1/i", $password)) {
                // usersテーブルに実在するユーザー名なら「突破成功」
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                return (bool)$user;
            }
        }

        // 通常処理（平文 or SHA256）
        $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user) return false;

        $stored_password = (string)$user['password'];
        if (preg_match('/^[0-9a-f]{64}$/i', $stored_password)) {
            return hash_equals($stored_password, hash('sha256', $password));
        } else {
            return hash_equals($stored_password, $password);
        }
    } catch (Exception $e) {
        return false;
    }
}


/**
 * 逆ブルートフォース試行をログに記録
 */
function log_reverse_bruteforce_attempt($pdo, $session_id, $password, $username, $success, $attempt_order) {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO reverse_bruteforce_logs (session_id, target_password, attempted_username, success, attempt_order) 
             VALUES (?, ?, ?, ?, ?)"
        );
        // パスワードは最初の3文字のみログ記録（セキュリティ配慮）
        $masked_password = substr($password, 0, 3) . str_repeat('*', max(0, strlen($password) - 3));
        $stmt->execute([$session_id, $masked_password, $username, $success ? 1 : 0, $attempt_order]);
    } catch (Exception $e) {
        error_log("Failed to log reverse bruteforce attempt: " . $e->getMessage());
    }
}