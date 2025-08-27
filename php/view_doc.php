<?php
session_start();
require 'db.php'; // ヘッダー表示のために読み込む
require_once __DIR__ . '/attack_crypto.php'; // 復号ユーティリティ

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page = $_GET['page'] ?? 'usage.txt';

// 【脆弱なコード】ユーザーからの入力を検証せずにファイルパスとして使用
$file_path = './docs/' . $page;

// 実体パスを解決（存在チェックより前に realpath しておく）
$abs = realpath($file_path);

// 【追加】attack/attack.md へのアクセスかどうかを判定
$attack_candidates = array_filter([
    @realpath(__DIR__ . '/attack/attack.md'),
    @realpath(__DIR__ . '/../attack/attack.md'),
]);

$is_attack_file = ($abs && in_array($abs, $attack_candidates, true));

// ファイル拡張子の判定
$file_extension = strtolower(pathinfo($page, PATHINFO_EXTENSION));
$is_markdown = in_array($file_extension, ['md', 'markdown']);

// 【追加】ファイル存在チェックとHTTPステータス設定
if (!$abs || !file_exists($abs)) {
    http_response_code(404);
    // 404ページとして表示
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <title>ページが見つかりません</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100">
    <?php include 'header.php'; ?>
    <div class="container mx-auto mt-10 p-4 max-w-2xl">
        <div class="bg-white p-8 rounded-lg shadow-md">
            <h1 class="text-2xl font-bold mb-6 text-red-600">404 Not Found</h1>
            <div class="prose max-w-none bg-red-100 p-4 rounded">
                <p class="text-red-700">指定されたドキュメントが見つかりませんでした。</p>
                <p class="text-sm text-gray-600 mt-2">リクエストされたファイル: <?php echo htmlspecialchars($page); ?></p>
            </div>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit; // 404ページ表示後、処理を終了
}

// ここまで来た場合、ファイルは存在する（200ステータス）

// ★ attack/attack.md なら復号、それ以外はそのまま表示
$content = null;
if ($is_attack_file) {
    $content = attack_decrypt_file($abs);
    if ($content === null) {
        $content = "[decrypt failed or key mismatch]"; // 復号失敗時の表示
    }
} else {
    $content = @file_get_contents($abs);
    if ($content === false) {
        $content = "[read failed]";
    }
}

// Markdown を HTML に変換する簡易パーサー
function parseMarkdown($markdown) {
    $html = $markdown;
    
    // エスケープ処理（XSS対策）
    $html = htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
    
    // 見出し変換（下線付き）
    $html = preg_replace('/^### (.+)$/m', '<h3 class="text-xl font-bold mt-8 mb-4 text-gray-800 border-b border-gray-300 pb-2">$1</h3>', $html);
    $html = preg_replace('/^## (.+)$/m', '<h2 class="text-2xl font-bold mt-10 mb-6 text-gray-900 border-b-2 border-gray-400 pb-3">$1</h2>', $html);
    $html = preg_replace('/^# (.+)$/m', '<h1 class="text-3xl font-bold mt-12 mb-8 text-gray-900 border-b-4 border-blue-500 pb-4">$1</h1>', $html);
    
    // 特殊項目のパターン（題名:、ページ名:、URL:、手法:、結果: など）
    $html = preg_replace('/^(題名|ページ名|URL|手法|結果|目的|対象|前提条件):\s*(.+)$/m', 
        '<div class="mb-4"><span class="font-bold text-gray-700">$1:</span> <span class="text-gray-800">$2</span></div>', $html);
    
    // 手順の番号付きリスト（丸数字対応）
    $html = preg_replace('/^([①②③④⑤⑥⑦⑧⑨⑩]|\d+[.．])\s*(.+)$/m', 
        '<div class="mb-3 text-gray-800 leading-relaxed">$1 $2</div>', $html);
    
    // 強調・太字
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong class="font-bold text-blue-700">$1</strong>', $html);
    $html = preg_replace('/\*(.+?)\*/s', '<em class="italic text-gray-700">$1</em>', $html);
    
    // インラインコード
    $html = preg_replace('/`([^`]+)`/', '<code class="bg-blue-100 text-blue-800 px-2 py-1 rounded font-mono text-sm font-semibold">$1</code>', $html);
    
    // コードブロック
    $html = preg_replace_callback('/```([a-zA-Z]*)\n(.*?)\n```/s', function($matches) {
        $lang = $matches[1];
        $code = $matches[2];
        return '<div class="bg-gray-900 text-green-400 p-6 rounded-lg font-mono text-sm my-6 overflow-x-auto border-l-4 border-green-500"><pre>' . $code . '</pre></div>';
    }, $html);
    
    // 通常のリスト項目
    $html = preg_replace('/^- (.+)$/m', '<li class="ml-6 mb-2 text-gray-700">• $1</li>', $html);
    
    // リンク
    $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" class="text-blue-600 hover:text-blue-800 underline font-medium">$1</a>', $html);
    
    // 区切り線（---）
    $html = preg_replace('/^---$/m', '<hr class="my-8 border-gray-300">', $html);
    
    // 改行処理：単一の改行は削除、空行のみ段落区切りとする
    $html = preg_replace('/(?<!\n)\n(?!\n)/', ' ', $html);
    $html = preg_replace('/\n{2,}/', "\n\n", $html);
    $html = str_replace("\n\n", '</p><p class="mb-4 text-gray-700 leading-relaxed">', $html);
    $html = '<p class="mb-4 text-gray-700 leading-relaxed">' . $html . '</p>';
    
    return $html;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ヘルプドキュメント</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .markdown-content {
            line-height: 1.6;
        }
        .markdown-content h1:first-child {
            margin-top: 0;
        }
        .markdown-content pre {
            white-space: pre-wrap;
            word-break: break-word;
        }
        .markdown-content code {
            word-break: break-word;
        }
        .attack-warning {
            animation: pulse 2s infinite;
            border-left: 4px solid #dc2626;
        }
    </style>
</head>
<body class="bg-gray-100">
<?php include 'header.php'; ?>
<div class="container mx-auto mt-10 p-4 max-w-4xl">
    <div class="bg-white p-8 rounded-lg shadow-md">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">ヘルプドキュメント</h1>
            <div class="text-sm text-gray-500">
                ファイル: <?= htmlspecialchars(basename($page)) ?>
                <?php if ($is_markdown): ?>
                    <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full ml-2">Markdown</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="markdown-content">
            <?php if ($is_markdown): ?>
                <!-- Markdown形式で表示 -->
                <div class="prose prose-lg max-w-none space-y-6">
                    <?= parseMarkdown($content) ?>
                </div>
            <?php else: ?>
                <!-- プレーンテキスト形式で表示 -->
                <div class="bg-gray-100 p-6 rounded-lg">
                    <pre class="whitespace-pre-wrap font-mono text-sm leading-relaxed"><code><?= htmlspecialchars($content) ?></code></pre>
                </div>
            <?php endif; ?>
        </div>

        
    </div>
</div>


</body>
</html>