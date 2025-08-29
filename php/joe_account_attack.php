<?php
// joe_account_attack.php
// 既定(joe系など)ユーザー名群 × 少数のよくあるパスワードを「薄く広く」試す演習
// 修正点：デフォルトでは joe 系を含めない。パターンが j / jo / joe のときのみ追加。

require_once __DIR__ . '/common_init.php';
require 'db.php';

header('Content-Type: application/json; charset=utf-8');

// 演習フラグ
if (empty($_SESSION['joe_account_attack_enabled'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ジョーアカウント攻撃演習は無効です。']);
    exit;
}

$data         = json_decode(file_get_contents('php://input'), true);
$user_pattern = isset($data['user_pattern']) ? trim((string)$data['user_pattern']) : 'joe';
$batch_size   = isset($data['batch_size']) ? max(1, min(100, (int)$data['batch_size'])) : 20;

// パスワード候補（空ならデフォルトセット）
$input_passwords = isset($data['passwords']) && is_array($data['passwords']) ? $data['passwords'] : [];
$passwords = array_values(array_filter(array_map('trim', $input_passwords)));
if (empty($passwords)) {
    $passwords = [
        'Password123', 'P@ssw0rd', 'Welcome1', 'Spring2025', 'admin', 'administrator',
        'qwerty123', '123456', '12345678', "' OR 1=1" // ← 演習仕様
    ];
}

$session_id = session_id();

/** LIKE 用エスケープ */
function escape_like(string $s): string {
    // バックスラッシュ → まず二重化
    $s = str_replace('\\', '\\\\', $s);
    // ワイルドカードをエスケープ
    $s = str_replace(['%', '_'], ['\\%', '\\_'], $s);
    return $s;
}

try {
    // ▼ 候補ユーザー名の抽出：users に実在するもの限定
    //   既定では joe 系は含めない。入力が j/jo/joe のときだけ joe 系を追加する。
    $include_joes = (bool)preg_match('/^j(o(e)?)?$/i', $user_pattern);
    $like = escape_like($user_pattern) . '%';

    // ベース SQL
    $sql_base = "
        SELECT c.username, COALESCE(d.frequency_rank, 9999) AS freq
          FROM (
                SELECT u.username
                  FROM users AS u
                 WHERE u.username LIKE :pat ESCAPE '\\\\'
               /**__JOE_PLACEHOLDER__**/
                 GROUP BY u.username
               ) AS c
          LEFT JOIN username_dictionary AS d
                 ON d.username = c.username
         ORDER BY freq ASC, c.username ASC
         LIMIT 200
    ";

    // joe 系を追加する場合のみ IN 句を追加
    if ($include_joes) {
        $sql = str_replace(
            '/**__JOE_PLACEHOLDER__**/',
            " OR u.username IN ('joe','joeuser','jdoe') ",
            $sql_base
        );
    } else {
        $sql = str_replace('/**__JOE_PLACEHOLDER__**/', '', $sql_base);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':pat', $like, PDO::PARAM_STR);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $candidate_users = array_column($rows, 'username');

    if (empty($candidate_users)) {
        echo json_encode(['success' => false, 'message' => '条件に合うユーザーが見つかりません。']);
        exit;
    }

    // IDS ログ（開始）
    if (function_exists('log_attack')) {
        log_attack(
            $pdo,
            'Joe Account Attack Start',
            "user_pattern={$user_pattern}, candidates=" . count($candidate_users) . ", pw=" . count($passwords),
            'joe_account_attack.php',
            200
        );
    }

    $results       = [];
    $attempt_count = 0;
    $success_count = 0;

    // ユーザー × パスワード を薄く試す（各ユーザーに対して先頭から少数）
    foreach ($candidate_users as $uname) {
        foreach ($passwords as $pw) {
            $attempt_count++;

            $ok = check_login_credentials_for_joe($pdo, $uname, $pw);
            if ($ok) $success_count++;

            // ログ保存（パスワードは先頭3文字のみ保持）
            $masked = substr($pw, 0, 3) . str_repeat('*', max(0, strlen($pw) - 3));
            log_joe_account_attempt($pdo, $session_id, $uname, $masked, $ok, $attempt_count);

            $results[] = [
                'username'       => $uname,
                'password'       => $masked,
                'success'        => $ok,
                'attempt_number' => $attempt_count,
                'timestamp'      => date('H:i:s')
            ];

            if ($attempt_count >= $batch_size) {
                break 2; // バッチ上限
            }

            usleep(40000); // 軽いスロットリング
        }
    }

    $has_more = ($attempt_count < (count($candidate_users) * count($passwords)));

    // 成功報告
    if ($success_count > 0 && function_exists('log_attack')) {
        $found = array_values(array_unique(array_map(
            fn($r) => $r['username'],
            array_filter($results, fn($r) => $r['success'])
        )));
        log_attack(
            $pdo,
            'Joe Account Attack Success',
            "found=" . implode(',', $found) . ", attempts={$attempt_count}",
            'joe_account_attack.php',
            200
        );
    }

    echo json_encode([
        'success'   => true,
        'results'   => $results,
        'statistics'=> [
            'attempts'           => $attempt_count,
            'successful_logins'  => $success_count,
            'success_rate'       => $attempt_count > 0 ? round(($success_count / $attempt_count) * 100, 2) : 0,
            'candidates'         => count($candidate_users),
            'passwords'          => count($passwords),
            'has_more'           => $has_more
        ],
        'message' => $success_count > 0
            ? "発見: {$success_count}件のログイン成立を検出しました"
            : "最初の{$attempt_count}試行では成立しませんでした"
    ]);

} catch (Throwable $e) {
    error_log('Joe account attack error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'エラーが発生しました: ' . $e->getMessage() ]);
    exit;
}

/** ===== 認証チェック（既存の演習仕様と整合） ===== */
function check_login_credentials_for_joe($pdo, $username, $password) {
    try {
        // 1) 演習: "' OR 1=1" を擬似的に成功扱い（username は実在必須）
        if (!empty($_SESSION['joe_account_attack_enabled']) &&
            preg_match("/'\\s*OR\\s*1\\s*=\\s*1/i", $password)) {

            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            return (bool)$stmt->fetchColumn();
        }

        // 2) 通常の厳密照合（平文/sha256）
        $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) return false;

        $stored = (string)$user['password'];
        if (preg_match('/^[0-9a-f]{64}$/i', $stored)) {
            return hash_equals($stored, hash('sha256', $password));
        }
        return hash_equals($stored, $password);

    } catch (Throwable $e) {
        return false;
    }
}

/** ===== ログ保存 ===== */
function log_joe_account_attempt($pdo, $session_id, $username, $masked_pw, $success, $order) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO joe_account_logs (session_id, attempted_username, tried_password, success, attempt_order)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$session_id, $username, $masked_pw, $success ? 1 : 0, $order]);
    } catch (Throwable $e) {
        error_log('log_joe_account_attempt failed: ' . $e->getMessage());
    }
}
