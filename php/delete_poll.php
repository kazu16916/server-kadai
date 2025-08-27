<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $poll_id = $_POST['poll_id'];
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];

    // 削除対象の投票の作成者IDを取得
    $stmt = $pdo->prepare("SELECT creator_user_id FROM polls WHERE id = ?");
    $stmt->execute([$poll_id]);
    $poll = $stmt->fetch();

    if (!$poll) {
        die("投票が見つかりません。");
    }

    // 権限チェック: admin または 投票の作成者であること
    if ($user_role === 'admin' || $poll['creator_user_id'] == $user_id) {
        // ON DELETE CASCADE制約により、関連するchoices, votes, comments, comment_likesも自動で削除される
        $delete_stmt = $pdo->prepare("DELETE FROM polls WHERE id = ?");
        $delete_stmt->execute([$poll_id]);
        
        header('Location: list.php');
        exit;
    } else {
        die("この投票を削除する権限がありません。");
    }
}
