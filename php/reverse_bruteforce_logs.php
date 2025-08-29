<?php
require_once __DIR__ . '/common_init.php';
require 'db.php';

// 管理者のみ閲覧可能
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

// 逆ブルートフォース演習が無効の場合はリダイレクト
if (empty($_SESSION['reverse_bruteforce_enabled'])) {
    header('Location: simulation_tools.php?error=' . urlencode('逆総当たり演習は現在無効です'));
    exit;
}

// ページネーション設定
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// 統計情報の取得
try {
    $stats_query = "
        SELECT 
            COUNT(DISTINCT session_id) as total_sessions,
            COUNT(*) as total_attempts,
            SUM(success) as successful_attempts,
            COUNT(DISTINCT target_password) as unique_passwords,
            COUNT(DISTINCT attempted_username) as unique_usernames
        FROM reverse_bruteforce_logs
    ";
    $stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = [
        'total_sessions' => 0,
        'total_attempts' => 0,
        'successful_attempts' => 0,
        'unique_passwords' => 0,
        'unique_usernames' => 0
    ];
}

// 最近のログエントリを取得
try {
    $logs_query = "
        SELECT 
            id, session_id, target_password, attempted_username, 
            success, attempt_order, created_at
        FROM reverse_bruteforce_logs 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ";
    $logs_stmt = $pdo->prepare($logs_query);
    $logs_stmt->execute([$per_page, $offset]);
    $logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 総件数取得
    $total_count = $pdo->query("SELECT COUNT(*) FROM reverse_bruteforce_logs")->fetchColumn();
    $total_pages = ceil($total_count / $per_page);
} catch (Exception $e) {
    $logs = [];
    $total_count = 0;
    $total_pages = 1;
}

// セッション別の成功率を取得
try {
    $session_stats_query = "
        SELECT 
            session_id,
            target_password,
            COUNT(*) as attempts,
            SUM(success) as successes,
            ROUND((SUM(success) / COUNT(*)) * 100, 2) as success_rate,
            MAX(created_at) as last_attempt
        FROM reverse_bruteforce_logs 
        GROUP BY session_id, target_password
        ORDER BY last_attempt DESC
        LIMIT 10
    ";
    $session_stats = $pdo->query($session_stats_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $session_stats = [];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>逆総当たり攻撃ログ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .success-row { background-color: #fef3c7; }
        .failure-row { background-color: #fef2f2; }
    </style>
</head>
<body class="bg-gray-100">
<?php include 'header.php'; ?>

<div class="container mx-auto mt-10 p-4">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800">逆総当たり攻撃ログ</h1>
        <div class="flex items-center gap-2">
            <a href="simulation_tools.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">演習設定に戻る</a>
        </div>
    </div>

    <!-- 統計概要 -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow border">
            <div class="text-sm text-gray-500">総セッション数</div>
            <div class="text-2xl font-bold text-purple-600"><?= (int)$stats['total_sessions'] ?></div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border">
            <div class="text-sm text-gray-500">総試行回数</div>
            <div class="text-2xl font-bold text-blue-600"><?= (int)$stats['total_attempts'] ?></div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border">
            <div class="text-sm text-gray-500">成功した試行</div>
            <div class="text-2xl font-bold text-green-600"><?= (int)$stats['successful_attempts'] ?></div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border">
            <div class="text-sm text-gray-500">試行パスワード数</div>
            <div class="text-2xl font-bold text-amber-600"><?= (int)$stats['unique_passwords'] ?></div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border">
            <div class="text-sm text-gray-500">試行ユーザー名数</div>
            <div class="text-2xl font-bold text-indigo-600"><?= (int)$stats['unique_usernames'] ?></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- セッション別成功率 -->
        <div class="bg-white rounded-lg shadow border">
            <div class="p-4 border-b">
                <h2 class="text-lg font-semibold">最近のセッション（成功率順）</h2>
            </div>
            <div class="p-4 space-y-3">
                <?php if (empty($session_stats)): ?>
                    <p class="text-gray-500">データがありません</p>
                <?php else: ?>
                    <?php foreach ($session_stats as $session): ?>
                        <div class="border rounded p-3">
                            <div class="flex justify-between items-center mb-1">
                                <span class="font-mono text-sm text-gray-600">
                                    Session: <?= htmlspecialchars(substr($session['session_id'], 0, 8)) ?>...
                                </span>
                                <span class="text-xs text-gray-500"><?= htmlspecialchars($session['last_attempt']) ?></span>
                            </div>
                            <div class="text-sm">
                                <div>パスワード: <code class="bg-gray-100 px-1 rounded"><?= htmlspecialchars($session['target_password']) ?></code></div>
                                <div class="flex justify-between mt-1">
                                    <span>試行: <?= (int)$session['attempts'] ?>回</span>
                                    <span>成功: <?= (int)$session['successes'] ?>回</span>
                                    <span class="font-semibold <?= $session['success_rate'] > 0 ? 'text-green-600' : 'text-gray-500' ?>">
                                        <?= $session['success_rate'] ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- 詳細ログ -->
        <div class="lg:col-span-2 bg-white rounded-lg shadow border">
            <div class="p-4 border-b flex justify-between items-center">
                <h2 class="text-lg font-semibold">詳細ログ</h2>
                <div class="text-sm text-gray-500">
                    全 <?= number_format($total_count) ?> 件中 <?= number_format($offset + 1) ?> - <?= number_format(min($offset + $per_page, $total_count)) ?> 件
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">時刻</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">セッション</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">パスワード</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ユーザー名</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">順番</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">結果</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">ログデータがありません</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr class="border-b hover:bg-gray-50 <?= $log['success'] ? 'success-row' : 'failure-row' ?>">
                                    <td class="px-4 py-2 text-sm font-mono">
                                        <?= htmlspecialchars(date('H:i:s', strtotime($log['created_at']))) ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs font-mono">
                                        <?= htmlspecialchars(substr($log['session_id'], 0, 8)) ?>...
                                    </td>
                                    <td class="px-4 py-2 text-sm font-mono">
                                        <code class="bg-gray-100 px-1 rounded"><?= htmlspecialchars($log['target_password']) ?></code>
                                    </td>
                                    <td class="px-4 py-2 text-sm font-mono">
                                        <?= htmlspecialchars($log['attempted_username']) ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        #<?= (int)$log['attempt_order'] ?>
                                    </td>
                                    <td class="px-4 py-2">
                                        <?php if ($log['success']): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                ✅ 成功
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                ❌ 失敗
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ページネーション -->
            <?php if ($total_pages > 1): ?>
                <div class="px-4 py-3 border-t flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        ページ <?= $page ?> / <?= $total_pages ?>
                    </div>
                    <div class="flex space-x-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" class="px-3 py-1 border rounded text-sm hover:bg-gray-100">前へ</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>" 
                               class="px-3 py-1 border rounded text-sm <?= $i === $page ? 'bg-purple-600 text-white' : 'hover:bg-gray-100' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>" class="px-3 py-1 border rounded text-sm hover:bg-gray-100">次へ</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 攻撃手法の説明 -->
    <div class="mt-8 bg-white rounded-lg shadow border p-6">
        <h2 class="text-lg font-semibold mb-4">💡 逆総当たり攻撃について</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-gray-700">
            <div>
                <h3 class="font-semibold text-purple-700 mb-2">攻撃手法</h3>
                <ul class="list-disc list-inside space-y-1">
                    <li>1つの推測パスワードで複数のユーザー名を試行</li>
                    <li>一般的なユーザー名辞書を使用</li>
                    <li>弱いパスワードを使用するアカウントの発見</li>
                    <li>アカウントロックアウトを回避しやすい</li>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold text-blue-700 mb-2">対策</h3>
                <ul class="list-disc list-inside space-y-1">
                    <li>強力なパスワードポリシーの実装</li>
                    <li>共通パスワードの使用禁止</li>
                    <li>多要素認証（MFA）の導入</li>
                    <li>異常なログイン試行の監視</li>
                    <li>IP単位でのレート制限</li>
                </ul>
            </div>
        </div>
    </div>
</div>

</body>
</html>