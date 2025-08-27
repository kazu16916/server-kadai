<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'ログインが必要です。']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$comment_id = $data['comment_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (empty($comment_id)) {
    echo json_encode(['success' => false, 'message' => '無効なリクエストです。']);
    exit;
}

try {
    // 既にいいねしているかチェック
    $stmt = $pdo->prepare("SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ?");
    $stmt->execute([$comment_id, $user_id]);
    $existing_like = $stmt->fetch();

    if ($existing_like) {
        // いいねを取り消す
        $stmt = $pdo->prepare("DELETE FROM comment_likes WHERE id = ?");
        $stmt->execute([$existing_like['id']]);
        $liked = false;
    } else {
        // いいねする
        $stmt = $pdo->prepare("INSERT INTO comment_likes (comment_id, user_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$comment_id, $user_id]);
        $liked = true;
    }

    // 新しいいいね数を取得
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM comment_likes WHERE comment_id = ?");
    $stmt->execute([$comment_id]);
    $like_count = $stmt->fetchColumn();

    echo json_encode(['success' => true, 'likes' => $like_count, 'liked' => $liked]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
}
