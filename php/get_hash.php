<?php
// get_hash.php
session_start();
require 'db.php';
header('Content-Type: application/json; charset=utf-8');

$username = isset($_GET['username']) ? trim($_GET['username']) : '';
if ($username === '') {
    echo json_encode(['ok' => false, 'error' => 'missing username']);
    exit;
}

$stmt = $pdo->prepare("SELECT password FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['ok' => false, 'error' => 'not found']);
    exit;
}

$stored = (string)$row['password'];
$isSha256 = (bool)preg_match('/^[0-9a-f]{64}$/i', $stored);

// DBに既にSHA-256が入っていればそれを、平文ならここでSHA-256にして返す
$hash = $isSha256 ? strtolower($stored) : hash('sha256', $stored);

echo json_encode(['ok' => true, 'hash' => $hash], JSON_UNESCAPED_UNICODE);
