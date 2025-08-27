<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 検索キーワードを取得
$search_query = $_GET['search'] ?? '';

// SQLクエリを準備
$sql = "SELECT id, title, token, creator_user_id, created_at FROM polls";
$params = [];

if (!empty($search_query)) {
    // 検索キーワードがある場合、WHERE句を追加
    $sql .= " WHERE title LIKE ?";
    $params[] = '%' . $search_query . '%';
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$polls = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>作成済み投票一覧</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<?php include 'header.php'; ?>
<div class="container mx-auto mt-10 p-4">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-8 gap-4">
        <h1 class="text-3xl font-bold text-gray-800">作成済み投票一覧</h1>
        <a href="index.php" class="bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600 whitespace-nowrap">新しい投票を作成</a>
    </div>

    <!-- 検索フォームを追加 -->
    <div class="mb-6 bg-white p-4 rounded-lg shadow-md">
        <form method="GET" action="list.php" class="flex items-center gap-2">
            <input type="text" name="search" placeholder="投票のタイトルで検索..." value="<?= htmlspecialchars($search_query) ?>" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            <button type="submit" class="bg-gray-600 text-white py-2 px-4 rounded-lg hover:bg-gray-700">検索</button>
        </form>
    </div>

    <div class="bg-white shadow-md rounded-lg p-6">
        <?php if (empty($polls)): ?>
            <p class="text-center text-gray-500">
                <?php if (!empty($search_query)): ?>
                    「<?= htmlspecialchars($search_query) ?>」に一致する投票は見つかりませんでした。
                <?php else: ?>
                    まだ投票は作成されていません。
                <?php endif; ?>
            </p>
        <?php else: ?>
            <ul class="space-y-4">
                <?php foreach ($polls as $poll): ?>
                    <li class="border rounded-lg p-4 flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div>
                            <h2 class="text-xl font-semibold"><?= htmlspecialchars($poll['title']) ?></h2>
                            <p class="text-sm text-gray-500">作成日時: <?= htmlspecialchars($poll['created_at']) ?></p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <a href="vote.php?token=<?= $poll['token'] ?>" class="bg-blue-500 text-white font-bold py-2 px-4 rounded">投票</a>
                            <a href="result.php?token=<?= $poll['token'] ?>" class="bg-green-500 text-white font-bold py-2 px-4 rounded">結果</a>
                            
                            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['user_id'] == $poll['creator_user_id']): ?>
                                <form action="delete_poll.php" method="POST" onsubmit="return confirm('本当にこの投票を削除しますか？');">
                                    <input type="hidden" name="poll_id" value="<?= $poll['id'] ?>">
                                    <button type="submit" class="bg-red-500 text-white font-bold py-2 px-4 rounded">削除</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
