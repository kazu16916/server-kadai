<?php
// tamper_defense.php
require_once __DIR__ . '/common_init.php';
require 'db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header('Location: list.php'); exit;
}
if (empty($_SESSION['tamper_enabled'])) {
  header('Location: simulation_tools.php'); exit;
}

$dir = __DIR__ . '/simulation_files';
@is_dir($dir) || @mkdir($dir, 0755, true);

function upsert_baseline($pdo, $path, $hash) {
  $st = $pdo->prepare("INSERT INTO tamper_baselines (file_path, sha256, updated_at)
                       VALUES (?, ?, NOW())
                       ON DUPLICATE KEY UPDATE sha256=VALUES(sha256), updated_at=NOW()");
  $st->execute([$path, $hash]);
}

function get_all_baselines($pdo) {
  $st = $pdo->query("SELECT file_path, sha256 FROM tamper_baselines");
  $out = [];
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[$r['file_path']] = $r['sha256'];
  return $out;
}

$notice = null;
$report = null;

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'baseline') {
    // ベースライン作成/更新
    $files = array_values(array_filter(scandir($dir), fn($f)=>$f!=='.' && $f!=='..' && is_file($dir.'/'.$f)));
    foreach ($files as $f) {
      $abs = realpath($dir.'/'.$f);
      $h = hash_file('sha256', $abs);
      upsert_baseline($pdo, $f, $h);
    }
    $notice = 'ベースラインを作成/更新しました（'.count($files).'件）。';
  } elseif ($action === 'verify') {
    // 検証
    $base = get_all_baselines($pdo);
    $nowFiles = array_values(array_filter(scandir($dir), fn($f)=>$f!=='.' && $f!=='..' && is_file($dir.'/'.$f)));
    $nowMap = [];
    foreach ($nowFiles as $f) $nowMap[$f] = hash_file('sha256', $dir.'/'.$f);

    $modified = [];
    $added = [];
    $deleted = [];

    foreach ($nowMap as $f => $h) {
      if (!isset($base[$f])) { $added[] = $f; continue; }
      if ($base[$f] !== $h) { $modified[] = $f; }
    }
    foreach ($base as $f => $h) {
      if (!isset($nowMap[$f])) $deleted[] = $f;
    }

    $report = compact('modified','added','deleted');

    // 検知ログ（何か差分があれば）
    if ($modified || $added || $deleted) {
      try {
        $st = $pdo->prepare(
          "INSERT INTO attack_logs (ip_address, user_id, attack_type, malicious_input, request_uri, user_agent, status_code, source_type)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $ip = $_SESSION['simulated_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'localhost');
        $ua = $_SESSION['simulated_user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Tamper-Defense');
        $detail = 'modified=['.implode(',', $modified).'], added=['.implode(',', $added).'], deleted=['.implode(',', $deleted).']';
        $st->execute([$ip, $_SESSION['user_id'] ?? null, 'Tamper Detected', $detail, $_SERVER['REQUEST_URI'] ?? '', $ua, 200, 'Exercise']);
      } catch(Throwable $e) { /* noop */ }
    }
  }
}

$baseline_count = (int)$pdo->query("SELECT COUNT(*) FROM tamper_baselines")->fetchColumn();
$files = array_values(array_filter(scandir($dir), fn($f)=>$f!=='.' && $f!=='..' && is_file($dir.'/'.$f)));
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>改ざん検知（防御）</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white">
<?php include 'header.php'; ?>
<div class="container mx-auto p-6">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">改ざん検知（防御）</h1>
    <a href="tamper_attack.php" class="text-sm px-3 py-2 rounded bg-slate-600 hover:bg-slate-700">← 攻撃画面へ戻る</a>
  </div>

  <?php if ($notice): ?>
    <div class="mb-4 p-3 rounded bg-emerald-800/50 border border-emerald-700"><?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>

  <div class="grid md:grid-cols-2 gap-6">
    <div class="bg-gray-800 p-5 rounded border border-gray-700">
      <h2 class="font-semibold mb-2">1. ベースライン（事前ハッシュ）</h2>
      <p class="text-sm text-gray-300 mb-3">保持件数：<?= $baseline_count ?> 件</p>
      <form method="post" class="flex gap-3">
        <input type="hidden" name="action" value="baseline">
        <button class="px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 font-semibold">作成/更新する</button>
      </form>
      <p class="text-xs text-gray-400 mt-3">※ <code>simulation_files</code> 配下の全ファイルの SHA-256 を保存します。</p>
    </div>

    <div class="bg-gray-800 p-5 rounded border border-gray-700">
      <h2 class="font-semibold mb-2">2. 検証</h2>
      <form method="post" class="flex gap-3">
        <input type="hidden" name="action" value="verify">
        <button class="px-4 py-2 rounded bg-purple-600 hover:bg-purple-700 font-semibold">検証する</button>
      </form>

      <?php if ($report): ?>
        <div class="mt-4 space-y-3 text-sm">
          <div>
            <h3 class="font-semibold text-amber-300">改ざんされたファイル</h3>
            <ul class="list-disc pl-5">
              <?php if (empty($report['modified'])): ?>
                <li class="text-gray-400">なし</li>
              <?php else: foreach ($report['modified'] as $f): ?>
                <li class="text-amber-200"><?= htmlspecialchars($f) ?></li>
              <?php endforeach; endif; ?>
            </ul>
          </div>
          <div>
            <h3 class="font-semibold text-emerald-300">新規ファイル</h3>
            <ul class="list-disc pl-5">
              <?php if (empty($report['added'])): ?>
                <li class="text-gray-400">なし</li>
              <?php else: foreach ($report['added'] as $f): ?>
                <li class="text-emerald-200"><?= htmlspecialchars($f) ?></li>
              <?php endforeach; endif; ?>
            </ul>
          </div>
          <div>
            <h3 class="font-semibold text-rose-300">削除されたファイル</h3>
            <ul class="list-disc pl-5">
              <?php if (empty($report['deleted'])): ?>
                <li class="text-gray-400">なし</li>
              <?php else: foreach ($report['deleted'] as $f): ?>
                <li class="text-rose-200"><?= htmlspecialchars($f) ?></li>
              <?php endforeach; endif; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="mt-6 bg-gray-800 p-5 rounded border border-gray-700">
    <h2 class="font-semibold mb-3">対象ファイル一覧</h2>
    <div class="font-mono text-sm bg-black/60 p-3 rounded max-h-64 overflow-auto">
      <?php foreach ($files as $f): ?>
        <div>📄 <?= htmlspecialchars($f) ?> — <?= hash_file('sha256', $dir.'/'.$f) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</body>
</html>
