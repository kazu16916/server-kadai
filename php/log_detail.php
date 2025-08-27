<?php
require_once __DIR__ . '/common_init.php';
require 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

$log_id = $_GET['id'] ?? null;
if (!$log_id) die("IDが指定されていません。");

$stmt = $pdo->prepare("SELECT * FROM attack_logs WHERE id = ?");
$stmt->execute([$log_id]);
$log = $stmt->fetch();

if (!$log) die("ログが見つかりません。");
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>検知ログ詳細 #<?= $log['id'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<?php include 'header.php'; ?>
<div class="container mx-auto mt-10 p-4">
    <a href="ids_dashboard.php" class="text-blue-600 hover:underline mb-4 inline-block">&laquo; ダッシュボードに戻る</a>
    <h1 class="text-3xl font-bold text-gray-800 mb-8">検知ログ詳細 <span class="text-gray-500">#<?= $log['id'] ?></span></h1>
    <div class="bg-white p-8 rounded-lg shadow-md">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-1 border-r pr-6">
                <h2 class="text-xl font-semibold mb-4">概要</h2>
                <?php
                    $status_color = 'bg-gray-200 text-gray-800'; // デフォルト
                    if ($log['status_code'] == 403) $status_color = 'bg-red-200 text-red-800';
                    if ($log['status_code'] == 404) $status_color = 'bg-yellow-200 text-yellow-800';
                    if ($log['status_code'] == 500) $status_color = 'bg-purple-200 text-purple-800'; // 500エラー用の色
                ?>
                <div class="space-y-3">
                    <p><strong>検知日時:</strong> <?= htmlspecialchars($log['detected_at']) ?></p>
                    <p><strong>レスポンスコード:</strong> <span class="px-2 py-1 font-semibold rounded-full <?= $status_color ?>"><?= htmlspecialchars($log['status_code']) ?></span></p>
                    <p><strong>攻撃タイプ:</strong> <?= htmlspecialchars($log['attack_type']) ?></p>
                    <p><strong>送信元IP:</strong> <?= htmlspecialchars($log['ip_address']) ?></p>
                    <p><strong>対象ページ:</strong> <?= htmlspecialchars($log['request_uri']) ?></p>
                </div>
            </div>
            <div class="md:col-span-2">
                <h2 class="text-xl font-semibold mb-4">ペイロードと環境</h2>
                <div class="space-y-4">
                    <div>
                        <h3 class="font-bold text-gray-700">検知されたペイロード:</h3>
                        <pre class="bg-gray-100 p-3 rounded-md mt-1 text-sm text-red-600"><code><?= htmlspecialchars($log['malicious_input']) ?></code></pre>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-700">User-Agent:</h3>
                        <pre class="bg-gray-100 p-3 rounded-md mt-1 text-sm"><code><?= htmlspecialchars($log['user_agent']) ?></code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
