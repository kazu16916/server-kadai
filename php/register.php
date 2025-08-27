<?php
session_start();
// ログイン済みの場合は一覧ページへリダイレクト
if (isset($_SESSION['user_id'])) {
    header('Location: list.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ユーザー登録</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<div class="container mx-auto mt-10 p-4 max-w-md">
    <div class="bg-white p-8 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold text-center mb-6">ユーザー登録</h1>
        <?php if (isset($_GET['error'])): ?>
            <p class="text-red-500 text-center mb-4"><?= htmlspecialchars($_GET['error']) ?></p>
        <?php endif; ?>
        <form action="register_process.php" method="POST">
            <div class="mb-4">
                <label for="username" class="block text-gray-700">ユーザー名</label>
                <input type="text" name="username" id="username" class="w-full px-3 py-2 border rounded-lg" required>
            </div>
            <div class="mb-6">
                <label for="password" class="block text-gray-700">パスワード</label>
                <input type="password" name="password" id="password" class="w-full px-3 py-2 border rounded-lg" required>
            </div>
            <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600">登録</button>
        </form>
        <p class="text-center mt-4">
            アカウントをお持ちですか？ <a href="login.php" class="text-blue-500">ログイン</a>
        </p>
    </div>
</div>
</body>
</html>
