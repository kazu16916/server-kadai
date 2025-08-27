<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        header('Location: register.php?error=ユーザー名とパスワードを入力してください。');
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        header('Location: register.php?error=そのユーザー名は既に使用されています。');
        exit;
    }

    // ★ 演習用：平文で保存（本番では絶対にNG）
    $stored_password = $password;

    // 最初のユーザーは admin
    $count_stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $user_count = $count_stmt->fetchColumn();
    $role = ($user_count == 0) ? 'admin' : 'user';

    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->execute([$username, $stored_password, $role]);

    header('Location: login.php?success=登録が完了しました。ログインしてください。');
    exit;
}
