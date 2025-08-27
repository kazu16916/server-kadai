<?php
// set_simulation_ip.php
require_once __DIR__ . '/common_init.php';

// 管理者のみ許可
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

// POST 以外は不許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: simulation_tools.php?error=' . urlencode('不正なリクエストです。'));
    exit;
}

if (isset($_POST['stop']) && $_POST['stop'] === 'true') {
    // ===== シミュレーション停止 =====
    unset($_SESSION['simulated_ip'], $_SESSION['simulated_type'], $_SESSION['simulated_user_agent'], $_SESSION['simulated_set_at']);
    header('Location: simulation_tools.php?success=' . urlencode('IPシミュレーションを停止しました。'));
    exit;
}

// ===== シミュレーション開始 =====
$ip         = isset($_POST['ip']) ? trim($_POST['ip']) : '';
$type       = isset($_POST['type']) ? trim($_POST['type']) : '';
$userAgent  = isset($_POST['user_agent']) ? trim($_POST['user_agent']) : '';

// IP バリデーション
if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
    header('Location: simulation_tools.php?error=' . urlencode('有効なIPアドレスを指定してください。'));
    exit;
}

// デフォルト補完
if ($type === '') {
    $type = 'Simulated';
}
if ($userAgent === '') {
    $userAgent = 'Simulated-Agent';
}

// セッションへ保存
$_SESSION['simulated_ip']         = $ip;
$_SESSION['simulated_type']       = $type;
$_SESSION['simulated_user_agent'] = $userAgent;
$_SESSION['simulated_set_at']     = date('c'); // ISO8601

header('Location: simulation_tools.php?success=' . urlencode("模擬IPを設定しました: {$ip}"));
exit;
