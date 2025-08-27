<?php
// pay_ransom.php
require_once __DIR__ . '/common_init.php';
require 'db.php';

if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
if (empty($_SESSION['ransomware_enabled'])) {
  header('Location: ransomware_defense_dashboard.php?err=disabled');
  exit;
}

$amount = isset($_POST['amount']) ? (int)$_POST['amount'] : 0;
if ($amount !== 10) $amount = 10; // 固定

$simulation_dir = __DIR__ . '/simulation_files';
$locked_exist = false;
if (is_dir($simulation_dir)) {
  $locked = glob($simulation_dir.'/*.locked');
  $locked_exist = !empty($locked);
}
if (!$locked_exist) {
  header('Location: ransomware_defense_dashboard.php?err=nolocked');
  exit;
}

$pdo->beginTransaction();
try {
  // 残高確認
  $st = $pdo->prepare("SELECT id, balance FROM users WHERE id=? FOR UPDATE");
  $st->execute([ (int)$_SESSION['user_id'] ]);
  $u = $st->fetch(PDO::FETCH_ASSOC);
  if (!$u) throw new Exception('user not found');
  if ((int)$u['balance'] < $amount) {
    $pdo->rollBack();
    header('Location: ransomware_defense_dashboard.php?err=nomoney');
    exit;
  }

  // 残高減算
  $st = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id=?");
  $st->execute([ $amount, (int)$u['id'] ]);

  // 支払いレコード作成（pending）
  $st = $pdo->prepare("INSERT INTO ransom_payments (payer_user_id, amount, status, note) VALUES (?, ?, 'pending', '演習: 身代金支払い')");
  $st->execute([ (int)$u['id'], $amount ]);

  // ログ（任意）
  $st = $pdo->prepare("INSERT INTO attack_logs (ip_address, user_id, attack_type, malicious_input, request_uri, user_agent, status_code, source_type)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
  $ip  = $_SESSION['simulated_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'localhost');
  $ua  = $_SESSION['simulated_user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Defense-UI');
  $req = $_SERVER['REQUEST_URI'] ?? '';
  $st->execute([$ip, $_SESSION['user_id'], 'Ransomware Payment Attempt', "amount={$amount}", $req, $ua, 202, 'Exercise']);

  $pdo->commit();
  header('Location: ransomware_defense_dashboard.php?pay=pending');
  exit;

} catch(Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  header('Location: ransomware_defense_dashboard.php?err=exception');
  exit;
}
