<?php
require_once __DIR__ . '/common_init.php';
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id_param = $_GET['id'] ?? $_SESSION['user_id'];

// 【脆弱なコード】ユーザーからの入力を直接SQL文に埋め込む
// ブラインドSQLインジェクション演習用
$sql = "SELECT username, created_at, avatar_path FROM users WHERE id = $user_id_param";

try {
    $stmt = $pdo->query($sql);
    $user_profile = $stmt->fetch();
} catch (PDOException $e) {
    // エラーを隠すことで、ブラインドSQLインジェクションの条件を作る
    $user_profile = null;
    
    // 演習用：攻撃試行をログに記録（実際のシステムでは攻撃者に情報を与えないよう注意）
    if (function_exists('log_attack')) {
        $ip_for_log = $_SESSION['simulated_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        log_attack($pdo, 'SQL Injection Attempt', 
                  'profile.php?id=' . substr($user_id_param, 0, 200), 
                  $ip_for_log, 400);
    }
}

// 演習用：SQLインジェクション攻撃のヒント表示（管理者モード）
$show_debug = isset($_SESSION['sql_debug_mode']) && $_SESSION['sql_debug_mode'];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ユーザープロフィール</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<?php include 'header.php'; ?>
<div class="container mx-auto mt-10 p-4 max-w-lg">
    <div class="bg-white p-8 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold text-center mb-6">ユーザープロフィール</h1>
        
        <?php if ($user_profile): ?>
            <!-- プロフィール画像表示 -->
            <div class="text-center mb-6">
                <img src="<?= htmlspecialchars($user_profile['avatar_path'] ?? 'https://placehold.co/128x128/e2e8f0/64748b?text=No+Image') ?>" alt="Avatar" class="w-32 h-32 rounded-full mx-auto object-cover">
            </div>

            <div class="space-y-4">
                <div>
                    <h2 class="text-sm font-bold text-gray-600">ユーザー名</h2>
                    <p class="text-lg"><?= htmlspecialchars($user_profile['username']) ?></p>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-600">登録日時</h2>
                    <p class="text-lg"><?= htmlspecialchars($user_profile['created_at']) ?></p>
                </div>
            </div>

            <!-- 画像アップロードフォーム -->
            <div class="mt-8 border-t pt-6">
                <h2 class="text-lg font-semibold mb-2">プロフィール画像を変更</h2>
                <form action="upload_avatar.php" method="POST" enctype="multipart/form-data">
                    <input type="file" name="avatar" class="w-full border p-2 rounded">
                    <button type="submit" class="mt-2 w-full bg-blue-500 text-white py-2 rounded hover:bg-blue-600">アップロード</button>
                </form>
            </div>
        <?php else: ?>
            <div class="text-center">
                <p class="text-red-500 mb-4">指定されたユーザーは見つかりませんでした。</p>
                <a href="profile.php" class="inline-block mt-4 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    自分のプロフィールを表示
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($show_debug): ?>
<!-- 演習用デバッグ情報（SQLインジェクション学習用） -->
<div class="container mx-auto mt-4 p-4 max-w-lg">
    <div class="bg-blue-50 border border-blue-200 text-blue-800 p-4 rounded text-sm">
        <strong>演習用デバッグ情報:</strong><br>
        実行されたSQL: <code><?= htmlspecialchars($sql) ?></code><br>
        受信パラメータ: <?= htmlspecialchars($user_id_param) ?><br>
        結果: <?= $user_profile ? 'データあり' : 'データなし' ?><br><br>
        
        <strong>ブラインドSQLインジェクションのヒント:</strong><br>
        • 条件が真の場合：ユーザー情報が表示される<br>
        • 条件が偽の場合：「ユーザーが見つかりません」と表示される<br>
        • エラーの場合：「ユーザーが見つかりません」と表示される<br><br>
        
        <strong>攻撃例:</strong><br>
        <code>?id=1 AND (SELECT COUNT(*) FROM users) > 0</code><br>
        <code>?id=1 AND (SELECT LENGTH(password) FROM users WHERE username='admin') = 8</code><br>
        <code>?id=1 AND (SELECT SUBSTR(password,1,1) FROM users WHERE username='admin') = 'a'</code>
    </div>
</div>
<?php endif; ?>

<!-- 演習モード切り替えボタン（管理者のみ） -->
<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
<div class="container mx-auto mt-4 p-4 max-w-lg text-center">
    <form method="POST" action="toggle_debug.php" class="inline">
        <button type="submit" name="toggle_sql_debug" class="bg-gray-600 text-white px-3 py-1 rounded text-sm hover:bg-gray-700">
            SQLデバッグモード: <?= $show_debug ? 'ON' : 'OFF' ?>
        </button>
    </form>
</div>
<?php endif; ?>

</body>
</html>