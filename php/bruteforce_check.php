<?php
require_once __DIR__ . '/common_init.php';
require 'db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$guess = $data['guess'] ?? '';

if (empty($username) || !isset($guess)) {
    echo json_encode(['match' => false, 'error' => 'Invalid input']);
    exit;
}

$stmt = $pdo->prepare("SELECT password FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($guess, $user['password'])) {
    echo json_encode(['match' => true]);
} else {
    echo json_encode(['match' => false]);
}
