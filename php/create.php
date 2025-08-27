<?php
session_start();
require 'db.php';


if (!isset($_SESSION['user_id'])) {
    die("ログインが必要です。");
}
$creator_user_id = $_SESSION['user_id'];

$title = $_POST['title'];
$choices = array_filter($_POST['choices']);
$token = bin2hex(random_bytes(8));

// creator_user_idも一緒にINSERTする
$stmt = $pdo->prepare("INSERT INTO polls (title, token, creator_user_id) VALUES (?, ?, ?)");
$stmt->execute([$title, $token, $creator_user_id]);
$poll_id = $pdo->lastInsertId();

$stmt = $pdo->prepare("INSERT INTO choices (poll_id, text) VALUES (?, ?)");
foreach ($choices as $choice) {
    $stmt->execute([$poll_id, $choice]);
}

header("Location: vote.php?token=" . $token);
exit;
