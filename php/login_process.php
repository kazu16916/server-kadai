<?php
session_start();
require 'db.php'; // この中で waf.php も読み込まれる

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if ($username === '') {
        header('Location: login.php?error=' . urlencode('ユーザー名を入力してください。'));
        exit;
    }

    // ▼ 信頼IP・模擬IP・バイパス可否（デフォルトは“無効(false)”）
    $trusted_ip      = $_SESSION['trusted_ip']      ?? '';
    $simulated_ip    = $_SESSION['simulated_ip']    ?? '';
    $bypass_enabled  = isset($_SESSION['trusted_admin_bypass_enabled']) ? (bool)$_SESSION['trusted_admin_bypass_enabled'] : false;
    $trusted_match   = ($bypass_enabled && !empty($trusted_ip) && !empty($simulated_ip) && hash_equals($trusted_ip, $simulated_ip));

    // ===== パスワード無し admin ログインは「バイパス有効」かつ「信頼IP一致」の時だけ =====
    if ($password === '' && strcasecmp($username, 'admin') === 0 && $trusted_match) {
        $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE username = 'admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch();
        if ($admin) {
            $_SESSION['user_id']  = (int)$admin['id'];
            $_SESSION['username'] = (string)$admin['username'];
            $_SESSION['role']     = (string)($admin['role'] ?? 'admin');

            // IDSログ（許可されたIPからの admin パスワードレス（フォーム経由/ワンクリック））
            $ip_for_log = $_SESSION['simulated_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            if (function_exists('log_attack')) {
                log_attack($pdo, 'Trusted IP Admin Bypass Login', 'passwordless (login_process.php)', $ip_for_log, 200);
            }

            header('Location: list.php');
            exit;
        } else {
            header('Location: login.php?error=' . urlencode('admin ユーザーが存在しません。'));
            exit;
        }
    }

    // ===== 通常ログイン（全ユーザー許可） =====
    if ($password === '') {
        // バイパスが無効、または admin 以外が空パス → 必ずパスワード必須
        header('Location: login.php?error=' . urlencode('パスワードを入力してください（admin の信頼IPログインを除く）。'));
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // 攻撃演習: 簡易SQLi試行の検出
    $is_injection_attempt = (strpos($password, "'") !== false || strpos($password, '"') !== false);

    // ===== 通常ログイン判定（平文 or 旧SHA-256の両対応）=====
    if ($user && is_string($user['password'])) {
        $dbpass   = (string)$user['password'];
        $isSha256 = (bool)preg_match('/^[0-9a-f]{64}$/i', $dbpass);

        $ok = false;
        if ($isSha256) {
            // 旧：SHA-256で保存されているユーザー
            $ok = hash_equals($dbpass, hash('sha256', $password));
        } else {
            // 新：演習用に平文保存されているユーザー
            $ok = hash_equals($dbpass, $password);
        }

        if ($ok) {
            $_SESSION['user_id']  = (int)$user['id'];
            $_SESSION['username'] = (string)$user['username'];
            $_SESSION['role']     = (string)$user['role'];

            // admin が通常ログイン成功＆模擬IPあり → 信頼IPとして保存（演習仕様）
            if (strcasecmp($user['username'], 'admin') === 0 && !empty($_SESSION['simulated_ip'])) {
                $_SESSION['trusted_ip'] = $_SESSION['simulated_ip'];
            }

            write_log("INFO: User '{$user['username']}' logged in successfully.");
            header('Location: list.php');
            exit;
        }
    }

    // ===== SQLインジェクション演習ブロック =====
    if ($is_injection_attempt) {
        $injected_sql = "SELECT id, username, role FROM users WHERE username = '$username' OR '1'='1' LIMIT 1";
        try {
            $injected_stmt = $pdo->query($injected_sql);
            $injected_user = $injected_stmt ? $injected_stmt->fetch() : false;
        } catch (\Throwable $e) {
            $injected_user = false;
        }

        if ($injected_user) {
            $_SESSION['user_id']  = (int)$injected_user['id'];
            $_SESSION['username'] = (string)$injected_user['username'] . ' (Injection)';
            $_SESSION['role']     = (string)$injected_user['role'];

            // admin として SQLi ログイン & シミュレーション中 → そのIPを信頼IPに（演習仕様）
            if (strcasecmp($injected_user['username'], 'admin') === 0 && isset($_SESSION['simulated_ip'])) {
                $_SESSION['trusted_ip'] = $_SESSION['simulated_ip'];
                write_log("INFO: Persistent IP backdoor for admin has been activated for IP: " . $_SESSION['simulated_ip']);
            }

            if (function_exists('log_attack')) {
                log_attack($pdo, 'Successful SQLi (WAF Bypassed)', $password, 'N/A (Bypassed)', 200);
            }
            write_log("WARN: User '{$injected_user['username']}' logged in via SQL Injection.");
            header('Location: list.php');
            exit;
        }
    }

    // 失敗
    write_log("INFO: Failed login attempt for username '{$username}'.");
    header('Location: login.php?error=' . urlencode('ユーザー名またはパスワードが間違っています。'));
    exit;
}

header('Location: login.php');
exit;

function write_log($message) {
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
    $log_file = $log_dir . '/app.log';
    $log_entry = date('Y-m-d H:i:s') . " - " . $message . "\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}
