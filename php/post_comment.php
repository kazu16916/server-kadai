<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'ログインが必要です。']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$poll_id = $data['poll_id'] ?? null;
$content = trim($data['content'] ?? '');
$choice_id = $data['choice_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (empty($content) || empty($poll_id)) {
    echo json_encode(['success' => false, 'message' => 'コメント内容が空です。']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO comments (poll_id, user_id, choice_id, content, created_at) VALUES (?, ?, ?, ?, NOW())"
    );
    // データベースにはそのまま保存
    $stmt->execute([$poll_id, $user_id, $choice_id, $content]);
    $comment_id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'comment' => [
            'id' => $comment_id,
            'username' => $_SESSION['username'],
            // 【脆弱なコード】エスケープ処理を削除
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s'),
            'likes' => 0,
            'liked_by_user' => false
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
}
