<?php
// attack_crypto.php
// 依存: OpenSSL (AES-256-GCM)

function attack_get_key(): string {
    // 優先: 環境変数
    $pass = getenv('ATTACK_FILE_SECRET');

    // 予備: ファイル（webroot外推奨）。内容: return ['secret' => '長いパスフレーズ'];
    if ($pass === false || $pass === '') {
        $conf = __DIR__ . '/attack_secret.php';
        if (is_file($conf)) {
            $arr = @include $conf;
            if (is_array($arr) && !empty($arr['secret'])) {
                $pass = $arr['secret'];
            }
        }
    }

    // 最終フォールバック（開発用）— 本番は必ず上書き！
    if ($pass === false || $pass === '') {
        $pass = 'CHANGE-ME-IN-ENV-OR-FILE';
    }

    // ★ SHA-256 で 32byte 鍵導出（不可逆変換）
    return hash('sha256', $pass, true);
}

function attack_encrypt_and_write(string $plaintext, string $file): bool {
    $key = attack_get_key();
    $iv  = random_bytes(12); // GCM用 96bit推奨
    $tag = '';
    $ct  = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ct === false) return false;

    $payload = json_encode([
        'v'   => 1,
        'alg' => 'AES-256-GCM',
        'iv'  => base64_encode($iv),
        'tag' => base64_encode($tag),
        'ct'  => base64_encode($ct),
    ], JSON_UNESCAPED_SLASHES);

    return file_put_contents($file, $payload) !== false;
}

function attack_decrypt_file(string $file): ?string {
    if (!is_file($file)) return null;
    $raw = @file_get_contents($file);
    $data = json_decode($raw, true);
    if (!is_array($data)) return null;

    $iv  = base64_decode($data['iv']  ?? '', true);
    $tag = base64_decode($data['tag'] ?? '', true);
    $ct  = base64_decode($data['ct']  ?? '', true);
    if ($iv === false || $tag === false || $ct === false) return null;

    $key = attack_get_key();
    $pt  = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return ($pt === false) ? null : $pt;
}
