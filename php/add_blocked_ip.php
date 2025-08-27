<?php
// add_blocked_ip.php
require_once __DIR__ . '/common_init.php';
require 'db.php';

// 戻り先（リファラ優先）
$back = (isset($_SERVER['HTTP_REFERER']) && filter_var($_SERVER['HTTP_REFERER'], FILTER_VALIDATE_URL))
    ? $_SERVER['HTTP_REFERER']
    : 'waf_settings.php';

// 管理者チェック
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . $back . '?error=' . urlencode('権限がありません。'));
    exit;
}

// POST以外は戻す
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . $back);
    exit;
}

// 入力
$ip_pattern  = trim($_POST['ip_pattern'] ?? '');
$action      = trim($_POST['action'] ?? 'block'); // 'block' or 'monitor'
$description = trim($_POST['description'] ?? '');

$errors = [];

// アクション妥当性
if (!in_array($action, ['block', 'monitor'], true)) {
    $errors[] = 'アクションが不正です。';
}

// IPパターン妥当性チェック
if (!validate_ip_pattern($ip_pattern, $errMsg)) {
    $errors[] = $errMsg;
}

if (!empty($errors)) {
    header('Location: ' . $back . '?error=' . urlencode(implode(' / ', $errors)));
    exit;
}

try {
    // --- テーブル自動作成（存在しなければ） ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS waf_ip_blocklist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_pattern VARCHAR(128) NOT NULL,
            action ENUM('block','monitor') NOT NULL DEFAULT 'block',
            description VARCHAR(255) NULL,
            is_custom BOOLEAN NOT NULL DEFAULT TRUE,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 追加
    $stmt = $pdo->prepare("
        INSERT INTO waf_ip_blocklist (ip_pattern, action, description, is_custom, created_at)
        VALUES (?, ?, ?, TRUE, NOW())
    ");
    $stmt->execute([$ip_pattern, $action, $description]);

    header('Location: ' . $back . '?success=' . urlencode('IPルールを追加しました。'));
    exit;

} catch (Throwable $e) {
    // ログ出力
    safe_app_log('add_blocked_ip failed: ' . $e->getMessage());
    header('Location: ' . $back . '?error=' . urlencode('IPルールの追加に失敗しました（詳細はログ参照）。'));
    exit;
}

/**
 * アプリ用ログ
 */
function safe_app_log(string $msg): void {
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    $log_file = $log_dir . '/app.log';
    $entry = sprintf("%s - %s\n", date('Y-m-d H:i:s'), $msg);
    @file_put_contents($log_file, $entry, FILE_APPEND);
}

/**
 * IPパターンの検証
 * 対応：
 *  - 正確一致（IPv4/IPv6）
 *  - ワイルドカード（IPv4のみ: 203.0.113.* / 203.0.*.*）
 *  - CIDR（IPv4/IPv6: 203.0.113.0/24, 2001:db8::/32）
 */
function validate_ip_pattern(string $pattern, ?string &$error = null): bool
{
    $pattern = trim($pattern);
    if ($pattern === '') {
        $error = 'IPパターンを入力してください。';
        return false;
    }

    // CIDR表記
    if (strpos($pattern, '/') !== false) {
        [$subnet, $mask] = explode('/', $pattern, 2) + [null, null];
        if ($subnet === null || $mask === null || $mask === '') {
            $error = 'CIDR表記が不正です（例: 203.0.113.0/24）。';
            return false;
        }
        if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if (!ctype_digit($mask) || (int)$mask < 0 || (int)$mask > 32) {
                $error = 'IPv4のCIDRマスクは 0〜32 で指定してください。';
                return false;
            }
            return true;
        }
        if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if (!ctype_digit($mask) || (int)$mask < 0 || (int)$mask > 128) {
                $error = 'IPv6のCIDRマスクは 0〜128 で指定してください。';
                return false;
            }
            return true;
        }
        $error = 'CIDRのIPアドレスが不正です。';
        return false;
    }

    // ワイルドカード（IPv4）
    if (strpos($pattern, '*') !== false) {
        $re = '/^((\d{1,3}|\*)\.){3}(\d{1,3}|\*)$/';
        if (!preg_match($re, $pattern)) {
            $error = 'ワイルドカードIPv4の形式が不正です（例: 203.0.113.*）。';
            return false;
        }
        foreach (explode('.', $pattern) as $oct) {
            if ($oct === '*') continue;
            if ((int)$oct < 0 || (int)$oct > 255) {
                $error = 'IPv4オクテットは 0〜255 で指定してください。';
                return false;
            }
        }
        return true;
    }

    // 正確一致（IPv4/IPv6）
    if (filter_var($pattern, FILTER_VALIDATE_IP)) {
        return true;
    }

    $error = 'IPパターンの形式が不正です（IPv4/IPv6、ワイルドカード、CIDRに対応）。';
    return false;
}
