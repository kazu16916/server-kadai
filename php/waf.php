<?php
// waf.php
// 簡易的な IDS/IPS（侵入検知・防御）
// 追加：IP アドレス単位の IPS ブロック（正確一致 / ワイルドカード / CIDR 対応）
// 追加：ランサムウェア関連シグネチャの検知

// グローバル変数で"検知のみ（block 以外）"だったイベントを終了時に記録するため保持
global $waf_detected_info;
$waf_detected_info = null;

/**
 * クライアントIPを取得（模擬IPがあれば最優先）
 */
function waf_client_ip(): string {
    return $_SESSION['simulated_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

/**
 * IPパターンとのマッチ判定
 * サポート：
 *  - 正確一致（例：203.0.113.5）
 *  - ワイルドカード（例：203.0.113.* / 203.0.*.*）
 *  - CIDR（例：203.0.113.0/24, 2001:db8::/32）
 */
function waf_ip_matches(string $ip, string $pattern): bool {
    $pattern = trim($pattern);
    if ($pattern === '') return false;

    // CIDR
    if (strpos($pattern, '/') !== false) {
        return waf_ip_in_cidr($ip, $pattern);
    }

    // ワイルドカード
    if (strpos($pattern, '*') !== false) {
        // IPv4のみ簡易対応（演習用途）
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }
        // パターンを正規表現化
        $re = '/^' . str_replace(['.','*'], ['\.','[0-9]{1,3}'], $pattern) . '$/';
        return (bool)preg_match($re, $ip);
    }

    // 正確一致
    return hash_equals($pattern, $ip);
}

/**
 * IPがCIDRに含まれるか（IPv4/IPv6対応）
 */
function waf_ip_in_cidr(string $ip, string $cidr): bool {
    [$subnet, $mask] = explode('/', $cidr, 2) + [null, null];
    if ($subnet === null || $mask === null) return false;

    // inet_pton でバイナリ化
    $ip_bin     = @inet_pton($ip);
    $subnet_bin = @inet_pton($subnet);
    if ($ip_bin === false || $subnet_bin === false) return false;

    $len = strlen($ip_bin); // 4 or 16
    $mask = (int)$mask;

    // IPv4/IPv6 の bit 長整合チェック
    if (($len === 4 && $mask > 32) || ($len === 16 && $mask > 128)) return false;
    if (strlen($subnet_bin) !== $len) return false;

    $bytes = intdiv($mask, 8);
    $remainder = $mask % 8;

    // 先頭の完全一致バイトを比較
    if ($bytes && substr($ip_bin, 0, $bytes) !== substr($subnet_bin, 0, $bytes)) {
        return false;
    }
    if ($remainder === 0) return true;

    // 次の1バイトをマスクして比較
    $maskByte = ~((1 << (8 - $remainder)) - 1) & 0xFF;
    return (ord($ip_bin[$bytes]) & $maskByte) === (ord($subnet_bin[$bytes]) & $maskByte);
}

/**
 * ランサムウェア演習が有効な場合にデフォルトシグネチャを動的に追加
 */
function add_ransomware_signatures_if_enabled($pdo): void {
    // ランサムウェア演習が無効の場合は何もしない
    if (empty($_SESSION['ransomware_enabled'])) {
        return;
    }

    $ransomware_signatures = [
        ['.locky', 'Ransomware: Locky encryption pattern detected'],
        ['encryption started', 'Ransomware: Encryption process detected'],
        ['send bitcoin', 'Ransomware: Cryptocurrency ransom demand'],
        ['unlock your files', 'Ransomware: File unlock demand'],
        ['RSA-2048', 'Ransomware: Strong encryption algorithm reference'],
        ['all files encrypted', 'Ransomware: Mass encryption claim'],
        ['spreading to network', 'Ransomware: Network propagation attempt'],
        ['scanning for *.doc', 'Ransomware: File type scanning pattern'],
        ['scanning for *.pdf', 'Ransomware: Document scanning pattern'],
        ['scanning for *.jpg', 'Ransomware: Image file scanning pattern'],
        ['via SMB', 'Ransomware: SMB-based network spread'],
        ['network shares', 'Ransomware: Network share targeting']
    ];

    try {
        // 既存のランサムウェア関連ルールをクリア（重複防止）
        $pdo->exec("DELETE FROM waf_blacklist WHERE is_custom = FALSE AND description LIKE '%Ransomware:%'");

        // 新しいシグネチャを追加
        $stmt = $pdo->prepare("
            INSERT INTO waf_blacklist (pattern, description, action, is_custom) 
            VALUES (?, ?, 'detect', FALSE)
        ");

        foreach ($ransomware_signatures as [$pattern, $description]) {
            $stmt->execute([$pattern, $description]);
        }

    } catch (Throwable $e) {
        error_log("Failed to add ransomware signatures: " . $e->getMessage());
    }
}

/**
 * メイン：WAF/IPS 実行
 */
function run_waf($pdo) {
    global $waf_detected_info;

    // ランサムウェア演習が有効な場合、動的にシグネチャを追加
    add_ransomware_signatures_if_enabled($pdo);

    // ===== 1) まずは IP ブロックリスト（IPS）を評価 =====
    try {
        // テーブルが無くても動くように例外を握りつぶす
        $stmt = $pdo->query("SELECT ip_pattern, action, description FROM waf_ip_blocklist");
        $ip_rules = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        $ip_rules = [];
    }

    if (!empty($ip_rules)) {
        $client_ip = waf_client_ip();

        foreach ($ip_rules as $r) {
            $pattern = (string)($r['ip_pattern'] ?? '');
            $action  = (string)($r['action'] ?? 'block');       // 'block' or 'monitor'
            $desc    = (string)($r['description'] ?? 'IPS: IP Rule Matched');

            if ($pattern !== '' && waf_ip_matches($client_ip, $pattern)) {
                if ($action === 'block') {
                    // 即時ブロック＆ログ
                    log_attack($pdo, $desc, 'IPS: IP Blocked', $pattern, 403);
                    http_response_code(403);
                    echo "<!DOCTYPE html><html><head><title>Forbidden</title></head><body><h1>403 Forbidden</h1><p>このIPアドレスからのアクセスはブロックされています。</p></body></html>";
                    die();
                } else {
                    // monitor：通すが、検知情報として終了時に記録
                    $waf_detected_info = [
                        'pdo'              => $pdo,
                        'attack_type'      => $desc,
                        'malicious_input'  => 'IPS: IP Matched (monitor)',
                        'detected_pattern' => $pattern
                    ];
                    // 以降のコンテンツ検査も実行（早期returnしない）
                }
            }
        }
    }

    // ===== 2) コンテンツ検査（WAF ブラックリスト） =====
    try {
        $stmt = $pdo->query("SELECT pattern, action, description FROM waf_blacklist");
        $rules = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        error_log("WAF Error: Could not fetch rules. " . $e->getMessage());
        return;
    }

    if (empty($rules)) return;

    // チェック対象を集約
    $strings_to_check = [];
    $strings_to_check[] = $_SERVER['REQUEST_URI'] ?? '';

    foreach ([ $_GET, $_POST, $_COOKIE ] as $global_array) {
        array_walk_recursive($global_array, function($value) use (&$strings_to_check) {
            if (is_string($value)) $strings_to_check[] = $value;
        });
    }

    // JSON ボディ
    if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $json_input = file_get_contents('php://input');
        $decoded_data = json_decode($json_input, true);
        if (is_array($decoded_data)) {
            array_walk_recursive($decoded_data, function($value) use (&$strings_to_check) {
                if (is_string($value)) $strings_to_check[] = $value;
            });
        }
    }

    // アップロードファイル
    if (!empty($_FILES)) {
        foreach ($_FILES as $file) {
            if (isset($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
                $content = @file_get_contents($file['tmp_name']);
                if (is_string($content)) $strings_to_check[] = $content;
            }
        }
    }

    // ★ detect は「保存だけして継続」、block はその場で 403
    foreach ($strings_to_check as $value) {
        $raw     = (string)$value;
        $decoded = urldecode($raw);

        foreach ($rules as $rule) {
            $pattern     = (string)($rule['pattern'] ?? '');
            $action      = (string)($rule['action'] ?? 'detect');   // 既定 detect（DB側も DEFAULT 'detect'）
            $description = (string)($rule['description'] ?? 'WAF Rule Matched');

            if ($pattern === '') continue;

            // URLデコード前後の両方で部分一致を判定
            $hit = (stripos($decoded, $pattern) !== false) || (stripos($raw, $pattern) !== false);
            if (!$hit) continue;

            if ($action === 'block') {
                // 即時ブロック
                log_attack($pdo, $description, $raw, $pattern, 403);
                http_response_code(403);
                echo "<!DOCTYPE html><html><head><title>Forbidden</title></head><body><h1>403 Forbidden</h1><p>不正な入力が検知されたため、リクエストはブロックされました。</p></body></html>";
                die();
            } else {
                // 検知のみ：終了時にまとめて記録（最初の1件だけ保持）
                if ($waf_detected_info === null) {
                    $waf_detected_info = [
                        'pdo'              => $pdo,
                        'attack_type'      => $description,
                        'malicious_input'  => $raw,
                        'detected_pattern' => $pattern
                    ];
                }
                // ★ ここで return しない！ 続けて block ルールがないか評価する
            }
        }
    }
}

/**
 * スクリプト終了時に実行される最終ログ記録関数
 * （block 以外の"検知のみ"を1件まとめて書く）
 */
function final_log_handler() {
    global $waf_detected_info;

    if ($waf_detected_info === null) return;

    $status_code = http_response_code();
    log_attack(
        $waf_detected_info['pdo'],
        $waf_detected_info['attack_type'],
        $waf_detected_info['malicious_input'],
        $waf_detected_info['detected_pattern'],
        $status_code
    );
}
register_shutdown_function('final_log_handler');

/**
 * IDS/IPS ログ記録
 */
function log_attack($pdo, $attack_type, $malicious_input, $detected_pattern, $status_code) {
    // シミュレーション中のIP/UserAgentがあれば優先
    $ip_address = $_SESSION['simulated_ip']         ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $user_agent = $_SESSION['simulated_user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A');
    $source_type = $_SESSION['simulated_type']      ?? 'Direct';

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO attack_logs (ip_address, user_id, attack_type, malicious_input, request_uri, user_agent, status_code, source_type)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $log_message = "Input: " . (string)$malicious_input . " | Pattern: " . (string)$detected_pattern;
        $stmt->execute([
            $ip_address,
            $_SESSION['user_id'] ?? null,
            (string)$attack_type,
            $log_message,
            $_SERVER['REQUEST_URI'] ?? '',
            (string)$user_agent,
            (int)$status_code,
            (string)$source_type
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log attack: " . $e->getMessage());
    }
}

// PDO があれば即実行
if (isset($pdo)) {
    run_waf($pdo);
}