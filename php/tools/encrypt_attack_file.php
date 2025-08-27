<?php
// tools/encrypt_attack_file.php
require_once __DIR__ . '/../attack_crypto.php';

// 候補パス（環境差異に対応）
$candidates = [
    __DIR__ . '/../attack/attack.md',
    __DIR__ . '/../../attack/attack.md',
];

$target = null;
foreach ($candidates as $p) {
    if (is_file($p)) { $target = realpath($p); break; }
}
if (!$target) { fwrite(STDERR, "attack.md が見つかりません\n"); exit(1); }

$cur = file_get_contents($target);
if ($cur === false) { fwrite(STDERR, "read失敗\n"); exit(2); }

// すでに暗号化済みかゆるく判定（JSONでct/tagがあるならスキップ）
if (json_decode($cur, true) && str_contains($cur, '"ct"')) {
    echo "すでに暗号化済み: $target\n";
    exit(0);
}

if (attack_encrypt_and_write($cur, $target)) {
    echo "暗号化OK: $target\n";
    exit(0);
} else {
    fwrite(STDERR, "暗号化失敗\n");
    exit(3);
}
