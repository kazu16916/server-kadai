<?php
// tamper_attack.php
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

// サンプルが無ければ作る（演習向け軽量ファイル）
$seed = [
  'about.html'        => "<h1>About</h1>\n<p>Company profile</p>\n",
  'script.js'         => "console.log('hello');\n",
  'styles.css'        => "body{font-family:sans-serif}\n",
  'readme.txt'        => "baseline file for tamper test\n",
];
foreach ($seed as $n => $c) {
  $p = $dir.'/'.$n;
  if (!file_exists($p)) @file_put_contents($p, $c);
}

// 改ざん実行
$msg = null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $file = $_POST['file'] ?? '';
  $type = $_POST['type'] ?? 'append';

  $target = realpath($dir).'/'.basename($file);
  if (!is_file($target)) {
    $msg = 'ファイルが存在しません。';
  } else {
    $before = @hash_file('sha256', $target) ?: '';
    if ($type === 'append') {
      @file_put_contents($target, "\n<!-- injected:" . date('H:i:s') . " -->\n", FILE_APPEND);
    } elseif ($type === 'overwrite') {
      @file_put_contents($target, "/* tampered at ".date('c')." */\n");
    } elseif ($type === 'replacejs') {
      @file_put_contents($target, "/* malicious */\nalert('tampered');\n");
    }
    $after = @hash_file('sha256', $target) ?: '';
    $msg = basename($file).' を改ざんしました。';

    // IDSログ
    try {
      $st = $pdo->prepare(
        "INSERT INTO attack_logs (ip_address, user_id, attack_type, malicious_input, request_uri, user_agent, status_code, source_type)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
      );
      $ip = $_SESSION['simulated_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'localhost');
      $ua = $_SESSION['simulated_user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Tamper-Attack');
      $st->execute([$ip, $_SESSION['user_id'] ?? null, 'Tamper Attack',
        "file=".basename($file)."; sha256_before=$before; sha256_after=$after",
        $_SERVER['REQUEST_URI'] ?? '', $ua, 200, 'Exercise']);
    } catch(Throwable $e) { /* noop */ }
  }
}

$files = array_values(array_filter(scandir($dir), fn($f)=>$f!=='.' && $f!=='..' && is_file($dir.'/'.$f)));
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>改ざん攻撃（演習）</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white">
<?php include 'header.php'; ?>

<div class="container mx-auto p-6">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">改ざん攻撃（演習）</h1>
    <a href="tamper_defense.php" class="text-sm px-3 py-2 rounded bg-purple-600 hover:bg-purple-700">→ 防御画面で検証する</a>
  </div>

  <?php if ($msg): ?>
    <div class="mb-4 p-3 rounded bg-amber-700/40 border border-amber-600 text-amber-100"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="bg-gray-800 p-5 rounded border border-gray-700">
    <h2 class="font-semibold mb-3">対象ファイル</h2>
    <form method="post" class="grid md:grid-cols-3 gap-3">
      <div>
        <select name="file" class="w-full text-black rounded p-2">
          <?php foreach ($files as $f): ?>
            <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <select name="type" class="w-full text-black rounded p-2">
          <option value="append">末尾に無害コメントを追記</option>
          <option value="overwrite">内容を上書き</option>
          <option value="replacejs">JSを置換（例）</option>
        </select>
      </div>
      <div>
        <button class="w-full bg-red-600 hover:bg-red-700 rounded py-2 font-semibold">改ざんを実行</button>
      </div>
    </form>
  </div>

  <div class="mt-6 bg-gray-800 p-5 rounded border border-gray-700">
    <h2 class="font-semibold mb-3">現在のハッシュ（SHA-256）</h2>
    <div class="font-mono text-sm bg-black/60 p-3 rounded max-h-64 overflow-auto">
      <?php foreach ($files as $f): ?>
        <div>📄 <?= htmlspecialchars($f) ?> — <?= hash_file('sha256', $dir.'/'.$f) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</body>
</html>
