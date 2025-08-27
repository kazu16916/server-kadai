<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
// adminのみ
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: list.php'); exit;
}

require_once __DIR__ . '/attack_crypto.php';
require 'db.php';

$output = null;
$is_markdown_output = false;

// ログファイル初期化（既存どおり）
$log_dir  = __DIR__ . '/logs';
$log_file = $log_dir . '/app.log';
if (!is_dir($log_dir)) { mkdir($log_dir, 0755, true); }
if (!file_exists($log_file)) {
    file_put_contents($log_file, "2025-08-22 15:00:00 - INFO: Application started.\n");
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = $_POST['username'];

    // ★ ここが追加ポイント：
    // 入力に "cat attack/attack.md" を含む場合だけ、攻撃ファイルを復号して表示
    if (preg_match('/\bcat\s+attack\/attack\.md\b/i', $username)) {
        // attack.md の既知位置から復号
        $attackPath = null;
        foreach ([__DIR__.'/attack/attack.md', __DIR__.'/../attack/attack.md'] as $cand) {
            if (is_file($cand)) { $attackPath = $cand; break; }
        }
        if ($attackPath) {
            $dec = attack_decrypt_file($attackPath);
            $output = $dec !== null ? $dec : "[decrypt failed or key mismatch]";
            $is_markdown_output = true; // Markdownとして表示
        } else {
            $output = "[attack.md not found]";
        }
    } else {
        // 既存の（脆弱な）grep 実行—演習用途
        $command = "grep -i '" . $username . "' " . $log_file;
        $output = shell_exec($command);
        $is_markdown_output = false;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ユーザー活動ログ検索</title>
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
        <h1 class="text-2xl font-bold mb-6">ユーザー活動ログ検索</h1>
        <p class="mb-4 text-gray-600">ユーザー名を入力して、アプリケーションログ内の活動記録を検索します。（Admin専用）</p>
        
        

        <form method="POST" action="diag.php">
            <div class="flex items-center gap-2">
                <input type="text" name="username" placeholder="例: admin" class="w-full px-3 py-2 border rounded-lg" required>
                <button type="submit" class="bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600">検索</button>
            </div>
        </form>

        <?php if ($output !== null): ?>
            <div class="mt-8">
                <?php if ($is_markdown_output): ?>
                    <!-- Markdown形式で表示 -->
                    <div class="attack-warning bg-red-50 border border-red-200 text-red-900 p-4 rounded-lg mb-6">
                        <div class="flex items-center mb-2">
                            <svg class="w-5 h-5 mr-2 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <strong>機密ファイル検出</strong>
                        </div>
                        <p class="text-sm">
                            コマンドインジェクション脆弱性により、暗号化された攻撃者用ファイルが復号表示されています。
                        </p>
                    </div>
                    
                    <h2 class="text-xl font-semibold mb-4">復号されたMarkdownファイル:</h2>
                    <div class="markdown-content bg-white border rounded-lg p-6">
                        <?= parseMarkdown($output) ?>
                    </div>
                <?php else: ?>
                    <!-- ログ検索結果 -->
                    <h2 class="text-xl font-semibold mb-4">ログ検索結果:</h2>
                    <div class="bg-gray-900 text-white text-sm p-4 rounded-lg overflow-x-auto">
                        <pre><code><?= htmlspecialchars($output ?: '一致するログが見つかりませんでした。') ?></code></pre>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- 使用例 -->
        
    </div>
</div>

<!-- 管理者用デバッグ情報 -->
</body>
</html>