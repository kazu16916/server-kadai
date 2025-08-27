<?php
// attacker_confirm_payment.php  — シンプル確認→復旧フロー（IDSログなし）

require_once __DIR__ . '/common_init.php';
require 'db.php';

// 管理者＆演習フラグチェック
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header('Location: list.php');
  exit;
}
if (empty($_SESSION['ransomware_enabled'])) {
  header('Location: list.php?err=disabled');
  exit;
}

set_time_limit(60);
if (session_status() === PHP_SESSION_ACTIVE) {
  // 復旧で時間がかかっても他ページをブロックしない
  session_write_close();
}

// 直近の支払いレコード（pending または confirmed）を取得
function find_latest_payment(PDO $pdo) {
  $st = $pdo->query("SELECT * FROM ransom_payments WHERE status IN ('pending','confirmed') ORDER BY id DESC LIMIT 1");
  return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

$info   = null;
$notice = '';
$error  = '';

// --- アクション処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'confirm') {
    // 「支払い確認する」ボタン
    try {
      $pdo->beginTransaction();
      $row = $pdo->query("SELECT * FROM ransom_payments WHERE status='pending' ORDER BY id DESC LIMIT 1 FOR UPDATE")
                 ->fetch(PDO::FETCH_ASSOC);
      if (!$row) {
        $pdo->rollBack();
        $error = '確認待ちの支払いがありません。';
      } else {
        $st = $pdo->prepare("UPDATE ransom_payments SET status='confirmed', confirmed_at=NOW(), confirmed_by=? WHERE id=? AND status='pending'");
        $st->execute([ (int)($_SESSION['user_id'] ?? 0), (int)$row['id'] ]);
        $pdo->commit();
        $notice = "支払いを確認しました（ID: {$row['id']}）。復旧ボタンを押すと復旧を開始します。";
      }
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $error = '支払い確認中にエラーが発生しました。';
    }
  }
  elseif ($action === 'restore') {
    // 「復旧する」ボタン
    $simulation_dir = __DIR__ . '/simulation_files';
    $restored = 0;

    try {
      if (is_dir($simulation_dir) && is_writable($simulation_dir)) {
        $locked = glob($simulation_dir . '/*.locked');
        if ($locked) {
          foreach ($locked as $locked_file) {
            $orig = substr($locked_file, 0, -7); // .locked を外す
            $enc  = @file_get_contents($locked_file);
            if ($enc === false) continue;
            $dec  = base64_decode($enc, true);
            if ($dec === false) continue;

            $plain = str_replace('_ENCRYPTED_BY_SIMULATION', '', $dec);

            if (@file_put_contents($orig, $plain) !== false) {
              @unlink($locked_file);
              $restored++;
            }
          }
        }
        $note = $simulation_dir . '/README_DECRYPT.txt';
        if (file_exists($note)) @unlink($note);
      }
      $notice = "復旧が完了しました（{$restored} 件のファイルを復元）。";
    } catch (Throwable $e) {
      $error = '復旧中にエラーが発生しました。';
    }
  }
}

// 表示用に最新状況を取得
$info = find_latest_payment($pdo);

// ステータス表示用
function status_badge($status) {
  switch ($status) {
    case 'pending':   return '<span class="px-2 py-1 rounded bg-yellow-600 text-white text-xs">未確認</span>';
    case 'confirmed': return '<span class="px-2 py-1 rounded bg-green-600 text-white text-xs">確認済み</span>';
    case 'cancelled': return '<span class="px-2 py-1 rounded bg-gray-600 text-white text-xs">キャンセル</span>';
    default:          return '<span class="px-2 py-1 rounded bg-gray-700 text-white text-xs">'.htmlspecialchars($status).'</span>';
  }
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>攻撃者コンソール：支払い確認と復旧</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-100">
<?php include 'header.php'; ?>

<div class="max-w-3xl mx-auto mt-8 px-4">
  <h1 class="text-2xl font-bold mb-4">攻撃者コンソール：支払い確認と復旧</h1>

  <?php if ($notice): ?>
    <div class="mb-4 p-3 rounded bg-emerald-800/60 border border-emerald-500"><?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="mb-4 p-3 rounded bg-red-800/60 border border-red-500"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (!$info): ?>
    <div class="p-6 rounded bg-slate-800 border border-slate-700">
      <p class="text-slate-300">支払いデータが見つかりません。</p>
    </div>
  <?php else: ?>
    <div class="p-6 rounded bg-slate-800 border border-slate-700">
      <div class="flex items-center justify-between mb-4">
        <div class="text-lg font-semibold">最新の支払い</div>
        <div><?= status_badge($info['status']) ?></div>
      </div>
      <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
        <dt class="text-slate-400">支払いID</dt><dd>#<?= (int)$info['id'] ?></dd>
        <dt class="text-slate-400">支払者ユーザーID</dt><dd><?= (int)$info['payer_user_id'] ?></dd>
        <dt class="text-slate-400">金額</dt><dd><?= (int)$info['amount'] ?> 円</dd>
        <dt class="text-slate-400">作成日時</dt><dd><?= htmlspecialchars($info['created_at']) ?></dd>
        <dt class="text-slate-400">確認日時</dt><dd><?= htmlspecialchars($info['confirmed_at'] ?? '-') ?></dd>
      </dl>

      <div class="mt-6 flex gap-3">
        <?php if ($info['status'] === 'pending'): ?>
          <!-- 支払い確認ボタン -->
          <form method="post">
            <input type="hidden" name="action" value="confirm">
            <button class="px-4 py-2 rounded bg-amber-600 hover:bg-amber-700 font-semibold">
              支払いを確認する
            </button>
          </form>
        <?php elseif ($info['status'] === 'confirmed'): ?>
          <div class="flex items-center gap-2 text-emerald-300 font-semibold">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
              <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75S21.75 6.615 21.75 12 17.385 21.75 12 21.75 2.25 17.385 2.25 12Zm13.36-2.59a.75.75 0 10-1.22-.86l-3.61 5.12-1.72-1.72a.75.75 0 10-1.06 1.06l2.4 2.4c.33.33.87.29 1.14-.08l5.07-5.92Z" clip-rule="evenodd"/>
            </svg>
            支払いを確認しました
          </div>
          <!-- 復旧ボタン -->
          <form method="post" class="ml-auto">
            <input type="hidden" name="action" value="restore">
            <button class="px-4 py-2 rounded bg-emerald-600 hover:bg-emerald-700 font-semibold">
              復旧する
            </button>
          </form>
        <?php else: ?>
          <p class="text-slate-300">この支払いは処理済み（またはキャンセル）です。</p>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
