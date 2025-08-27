<?php
// tools/decrypt_attack_file.php
// 目的: attack/attack.md を「復号して」ファイルとして保存する（上書き or 別名）
//
// 使い方 (CLI):
//   php tools/decrypt_attack_file.php
//   php tools/decrypt_attack_file.php --in=/path/to/attack.md --mode=overwrite
//   php tools/decrypt_attack_file.php --in=/path/to/attack.md --out=/tmp/recovered.md
//
// 使い方 (Web):
//   /tools/decrypt_attack_file.php            → 標準の場所を探して sidecar で復元
//   /tools/decrypt_attack_file.php?in=...&mode=overwrite
//   /tools/decrypt_attack_file.php?in=...&out=...
//
// 出力先の規則:
//   - --mode=overwrite を明示しない限り、<元ファイル名>.decrypted.md に書き出します（安全）
//   - overwrite の場合は、元の暗号化JSONを <元ファイル名>.enc.json.bak として残します

declare(strict_types=1);

$root = dirname(__DIR__);
$attackCrypto = $root . '/attack_crypto.php';
if (is_file($attackCrypto)) {
    require_once $attackCrypto; // attack_decrypt_file / attack_decrypt_and_write があれば再利用
}

// 候補ファイル
$candidates = [
    $root . '/attack/attack.md',
    dirname($root) . '/attack/attack.md',
];

$in = opt('in') ?? req('in');
$mode = strtolower(opt('mode') ?? req('mode') ?? 'sidecar'); // 'sidecar' | 'overwrite'
$out  = opt('out') ?? req('out');

// 入力ファイルの決定
$target = null;
if ($in) {
    $real = realpath($in);
    if ($real && is_file($real)) $target = $real;
} else {
    foreach ($candidates as $p) {
        if (is_file($p)) { $target = realpath($p); break; }
    }
}
if (!$target) respond(1, "復号対象の attack.md が見つかりません。--in で明示指定も可能です。");

// 読み込み
$enc = @file_get_contents($target);
if ($enc === false) respond(2, "読み込みに失敗しました: $target");

// 既に平文っぽい？（JSONの暗号フィールドがなければ何もしない）
if (!looks_encrypted_json($enc)) {
    respond(0, "暗号化されていないようです。何もしません。\n対象: $target");
}

// 復号本体：まず attack_crypto.php の関数を優先
$plain = null;

// 1) 高レベル関数があれば使う（path→plain）
if (!$plain && function_exists('attack_decrypt_file')) {
    $plain = @attack_decrypt_file($target);
}

// 2) 失敗時は、暗号JSON → 低レベル復号関数があれば使う
if ((!$plain) && function_exists('attack_decrypt_json_to_plain')) {
    $plain = @attack_decrypt_json_to_plain($enc, $target); // 第二引数は AAD 用パスヒント
}

// 3) それも無い場合のフォールバック（共通形式: AES-256-GCM / {iv,tag,ct} Base64）
//    ※ キーや AAD は attack_crypto.php と同じでなければ復号できません。
//    attack_crypto.php 内に ATTACK_SECRET_KEY や resolve 関数があれば呼び出して揃えます。
if (!$plain) {
    $js = json_decode($enc, true);
    if (!is_array($js) || !isset($js['iv'],$js['tag'],$js['ct'])) {
        respond(3, "暗号JSONの形式が不正です（iv, tag, ct が見つかりません）。");
    }
    $iv  = b64($js['iv']);  $tag = b64($js['tag']);  $ct = b64($js['ct']);
    if ($iv===false || $tag===false || $ct===false) respond(4, "iv/tag/ct のBase64デコードに失敗しました。");

    // キー解決：attack_crypto.php に同等の関数/定数がある前提で探す
    $key = null;
    if (function_exists('attack_resolve_key')) {
        $key = attack_resolve_key(); // 32byte raw を期待
    } elseif (defined('ATTACK_SECRET_KEY')) {
        $key = constant('ATTACK_SECRET_KEY');
    } elseif (getenv('ATTACK_SECRET_KEY')) {
        $key = getenv('ATTACK_SECRET_KEY');
    }

    if (!$key) {
        respond(5, "鍵が特定できません。attack_crypto.php の鍵解決（定数/ENV/関数）を再確認してください。");
    }
    // 32byteへ正規化
    if (strlen($key) !== 32) {
        // base64:, 素のB64, HEXなどを吸収
        if (str_starts_with($key,'base64:')) {
            $raw = base64_decode(substr($key,7), true);
            if ($raw!==false) $key = $raw;
        } elseif (($raw = base64_decode($key, true)) !== false) {
            $key = $raw;
        } elseif (preg_match('/^[0-9a-fA-F]{64}$/', $key)) {
            $key = pack('H*', $key);
        } else {
            $key = hash('sha256', (string)$key, true); // パスフレーズならシャ256化
        }
    }

    // AAD は attack_crypto.php の方針に合わせる（あれば）
    $aad = '';
    if (function_exists('attack_resolve_aad')) {
        $aad = (string)attack_resolve_aad($target);
    }

    $plain = @openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad);
    if ($plain === false) {
        respond(6, "復号に失敗しました（鍵 or AAD が一致していません）。view_doc.php で表示復号できているなら、attack_crypto.php の関数を利用できるようにしてください。");
    }
}

// 書き出し先の決定
if ($out) {
    $dest = $out;
} else {
    if ($mode === 'overwrite') {
        $dest = $target; // 上書き
    } else {
        // sidecar: example.md → example.md.decrypted.md
        $dest = $target . '.decrypted.md';
    }
}

// 上書きモードならバックアップを残す
if ($mode === 'overwrite') {
    $bak = $target . '.enc.json.bak';
    @file_put_contents($bak, $enc);
}

// 書き出し
if (@file_put_contents($dest, $plain) === false) {
    respond(7, "書き込みに失敗しました: $dest  (書込権限を確認してください)");
}

if ($mode === 'overwrite') {
    respond(0, "復号に成功し、上書きしました。\n対象: $target\nバックアップ: {$target}.enc.json.bak");
} else {
    respond(0, "復号に成功しました。\n出力: $dest");
}

// ---- ユーティリティ ----
function opt(string $name): ?string {
    if (php_sapi_name() !== 'cli') return null;
    foreach ($GLOBALS['argv'] ?? [] as $a) {
        if (strpos($a, "--$name=") === 0) return substr($a, strlen($name)+3);
    }
    return null;
}
function req(string $k): ?string { return $_POST[$k] ?? $_GET[$k] ?? null; }
function b64(string $s){ return base64_decode($s, true); }
function looks_encrypted_json(string $s): bool {
    $j = json_decode($s, true);
    return is_array($j) && isset($j['ct'],$j['iv'],$j['tag']);
}
function respond(int $code, string $msg){
    if (php_sapi_name()==='cli') {
        if ($code===0) fwrite(STDOUT, $msg.PHP_EOL);
        else fwrite(STDERR, $msg.PHP_EOL);
        exit($code);
    } else {
        http_response_code($code===0?200:500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $msg;
        exit;
    }
}
