<?php
// ransomware_defense_dashboard.php
// Blue-team style dashboard to detect/visualize simulated ransomware activity
// Uses existing tables (attack_logs), simulation_files directory, and ransom_payments/users.balance (10円支払い→攻撃者確認で復旧)

require_once __DIR__ . '/common_init.php';
require 'db.php';

// Admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

// Optional auto refresh (5-60 seconds)
$auto_refresh = isset($_GET['refresh']) ? (int)$_GET['refresh'] : 0;
if ($auto_refresh >= 5 && $auto_refresh <= 60) {
    header("Refresh: $auto_refresh");
}

$simulation_dir  = __DIR__ . '/simulation_files';
$dir_exists      = is_dir($simulation_dir);
$dir_writable    = $dir_exists ? is_writable($simulation_dir) : false;

$files = $dir_exists ? glob($simulation_dir . '/*') : [];
$total_files = 0;
$locked_files = 0;
$normal_files = 0;
$ransom_note_exists = false;
$latest_mtime = 0;
$latest_mtime_file = '';
$locked_list = [];
$normal_list = [];

if ($dir_exists) {
    foreach ($files as $f) {
        if (!is_file($f)) continue;
        $total_files++;
        $bn = basename($f);
        $mtime = @filemtime($f) ?: 0;
        if ($mtime > $latest_mtime) { $latest_mtime = $mtime; $latest_mtime_file = $bn; }
        if ($bn === 'README_DECRYPT.txt') {
            $ransom_note_exists = true;
            continue;
        }
        if (substr($bn, -7) === '.locked') {
            $locked_files++;
            $locked_list[] = $bn;
        } else {
            $normal_files++;
            $normal_list[] = $bn;
        }
    }
}

$encryption_rate = ($locked_files + $normal_files) > 0 ? ($locked_files / ($locked_files + $normal_files)) : 0.0;

// Severity scoring (simple heuristic)
$score = 0;
if ($ransom_note_exists) $score += 60;                         // ransom note strongly indicates incident
$score += min(40, (int)round($encryption_rate * 100 * 0.4));   // up to +40 based on encryption rate
$severity = 'Informational';
$sev_color = 'bg-gray-600';
if     ($score >= 80) { $severity = 'CRITICAL'; $sev_color = 'bg-red-600'; }
elseif ($score >= 50) { $severity = 'High';     $sev_color = 'bg-red-500'; }
elseif ($score >= 30) { $severity = 'Medium';   $sev_color = 'bg-amber-500'; }
elseif ($score >= 10) { $severity = 'Low';      $sev_color = 'bg-yellow-500'; }

// Recent ransomware-related logs
$recent_logs = [];
$recent_activity = [];
try {
    $stmt = $pdo->prepare("SELECT id, detected_at, attack_type, ip_address, status_code, malicious_input FROM attack_logs WHERE attack_type LIKE ? ORDER BY detected_at DESC LIMIT 50");
    $stmt->execute(['Ransomware%']);
    $recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { /* ignore */ }

try {
    $stmt = $pdo->prepare("SELECT id, detected_at, attack_type, ip_address, status_code, malicious_input FROM attack_logs WHERE attack_type LIKE ? ORDER BY detected_at DESC LIMIT 50");
    $stmt->execute(['Ransomware File Activity:%']);
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { /* ignore */ }

// Logged-in user's balance
$me = null; $balance = 0;
if (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT id, username, balance FROM users WHERE id=?");
    $stmt->execute([ (int)$_SESSION['user_id'] ]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($me) $balance = (int)$me['balance'];
}

// Last payment
$last_payment = null;
try {
    $st = $pdo->prepare("SELECT * FROM ransom_payments ORDER BY id DESC LIMIT 1");
    $st->execute();
    $last_payment = $st->fetch(PDO::FETCH_ASSOC);
} catch(Throwable $e) {}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>防御ダッシュボード - ランサムウェア検知</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes pulseGlow { 0%{box-shadow:0 0 0 rgba(0,0,0,0)} 50%{box-shadow:0 0 35px rgba(239,68,68,.35)} 100%{box-shadow:0 0 0 rgba(0,0,0,0)} }
    </style>
</head>
<body class="bg-gray-100">
<?php include 'header.php'; ?>

<div class="container mx-auto mt-10 p-4">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800">防御ダッシュボード <span class="text-gray-400 text-xl">— ランサムウェア検知</span></h1>
        <div class="flex items-center gap-3">
            <form method="GET" class="flex items-center gap-2">
                <label class="text-sm text-gray-600">オートリフレッシュ</label>
                <select name="refresh" class="border rounded px-2 py-1 text-sm">
                    <option value="0"<?= $auto_refresh===0?' selected':''; ?>>OFF</option>
                    <option value="5"<?= $auto_refresh===5?' selected':''; ?>>5s</option>
                    <option value="10"<?= $auto_refresh===10?' selected':''; ?>>10s</option>
                    <option value="30"<?= $auto_refresh===30?' selected':''; ?>>30s</option>
                    <option value="60"<?= $auto_refresh===60?' selected':''; ?>>60s</option>
                </select>
                <button class="bg-blue-600 text-white text-sm px-3 py-1 rounded">適用</button>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-5 rounded-xl shadow border">
            <div class="text-sm text-gray-500">ディレクトリ</div>
            <div class="mt-1 font-mono text-sm break-all"><?= h($simulation_dir) ?></div>
            <div class="mt-2 text-sm">
                状態: <span class="font-semibold <?= $dir_exists? 'text-green-600':'text-red-600' ?>"><?= $dir_exists?'存在':'なし' ?></span>
                / 書込: <span class="font-semibold <?= $dir_writable? 'text-green-600':'text-red-600' ?>"><?= $dir_writable?'可':'不可' ?></span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-xl shadow border">
            <div class="text-sm text-gray-500">ファイル統計</div>
            <div class="mt-2 text-2xl font-bold">
                <?= (int)$locked_files ?><span class="text-sm text-gray-500"> / <?= (int)($locked_files+$normal_files) ?></span>
            </div>
            <div class="text-sm text-gray-500">暗号化ファイル / 監視対象</div>
        </div>
        <div class="bg-white p-5 rounded-xl shadow border">
            <div class="text-sm text-gray-500">暗号化率</div>
            <div class="mt-2">
                <div class="w-full bg-gray-200 rounded h-3">
                    <div class="h-3 rounded <?= $encryption_rate>=0.5?'bg-red-500':($encryption_rate>=0.2?'bg-amber-500':'bg-green-500') ?>" style="width: <?= (int)round($encryption_rate*100) ?>%"></div>
                </div>
                <div class="mt-1 text-sm font-semibold"><?= (int)round($encryption_rate*100) ?>%</div>
            </div>
        </div>
        <div class="bg-white p-5 rounded-xl shadow border">
            <div class="text-sm text-gray-500">深刻度</div>
            <div class="mt-2 inline-block px-3 py-1 rounded-full text-white font-bold <?= $sev_color ?>" style="animation: <?= $severity==='CRITICAL'?'pulseGlow 2s infinite':'' ?>;">
                <?= h($severity) ?> (<?= (int)$score ?>)
            </div>
            <div class="mt-2 text-xs text-gray-500">メモ: <?= $ransom_note_exists?'<span class="text-red-600 font-semibold">あり</span>':'なし' ?></div>
        </div>
    </div>

    <!-- Indicators -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow border">
            <div class="p-5 border-b flex items-center justify-between">
                <h2 class="text-lg font-semibold">検知インジケータ</h2>
                <?php if ($ransom_note_exists): ?>
                    <span class="text-red-600 text-sm font-semibold">身代金メモ検出</span>
                <?php endif; ?>
            </div>
            <div class="p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-600">最新変更ファイル</div>
                    <div class="text-sm font-mono"><?= h($latest_mtime_file ?: '-') ?></div>
                </div>
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-600">最終更新</div>
                    <div class="text-sm"><?= $latest_mtime? date('Y-m-d H:i:s', $latest_mtime) : '-' ?></div>
                </div>
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-600">身代金メモ</div>
                    <div class="text-sm font-mono"><?= $ransom_note_exists? 'README_DECRYPT.txt' : '-' ?></div>
                </div>
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-600">暗号化ファイル数</div>
                    <div class="text-sm font-semibold text-red-600"><?= (int)$locked_files ?></div>
                </div>
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-600">未暗号化ファイル数</div>
                    <div class="text-sm font-semibold text-green-600"><?= (int)$normal_files ?></div>
                </div>
            </div>
            <?php if (!empty($locked_list)): ?>
            <div class="px-5 pb-5">
                <div class="text-sm text-gray-600 mb-2">暗号化ファイル一覧</div>
                <div class="bg-gray-50 border rounded p-3 max-h-40 overflow-y-auto font-mono text-xs">
                    <?php foreach ($locked_list as $f): ?>
                        <div class="text-red-600">🔒 <?= h($f) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Response Actions (NO direct restore; pay ransom instead) -->
        <div class="bg-white rounded-xl shadow border">
            <div class="p-5 border-b">
                <h2 class="text-lg font-semibold">対応アクション（防御側）</h2>
                <p class="text-sm text-gray-500 mt-1">※ 防御側からの直接復旧はできません。身代金を支払うと攻撃者が確認後に復旧されます（演習）。</p>
            </div>
            <div class="p-5 space-y-4">
                <div class="p-3 bg-gray-50 rounded border text-sm">
                    あなたの残高：<span class="font-bold"><?= (int)$balance ?> 円</span>
                </div>

                <form action="pay_ransom.php" method="POST"
                      onsubmit="return confirm('身代金 10円 を支払います。よろしいですか？（模擬）');">
                    <input type="hidden" name="amount" value="10">
                    <button class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2 rounded <?= $locked_files===0?' opacity-50 cursor-not-allowed':'' ?>"
                            <?= $locked_files===0?'disabled':'' ?>>
                        身代金 10円 を支払う（Pending）
                    </button>
                </form>

                <div class="p-3 bg-gray-50 rounded border text-sm">
                    最新支払いステータス：
                    <?php if (!$last_payment): ?>
                        <span class="font-mono">なし</span>
                    <?php else: ?>
                        <span class="font-mono">
                          #<?= (int)$last_payment['id'] ?> /
                          <?= h($last_payment['status']) ?> /
                          <?= (int)$last_payment['amount'] ?>円 /
                          <?= h($last_payment['created_at']) ?>
                          <?php if (!empty($last_payment['confirmed_at'])): ?>
                            / 確認: <?= h($last_payment['confirmed_at']) ?>
                          <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <a href="ids_dashboard.php" class="text-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded">IDSログへ</a>
                    <a href="enhanced_ransomware_exercise.php" class="text-center bg-gray-700 hover:bg-gray-800 text-white font-semibold py-2 rounded">演習画面へ</a>
                </div>

                <div class="text-xs text-gray-500">
                    ※ この支払い・復旧は演習用の模擬処理です。実運用ではバックアップ復元・端末隔離・証跡保全・通報などの手順を優先してください。
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Logs -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow border overflow-hidden">
            <div class="p-5 border-b flex items-center justify-between">
                <h2 class="text-lg font-semibold">最近のランサムウェア関連ログ</h2>
                <span class="text-sm text-gray-500">attack_logs (最新50)</span>
            </div>
            <div class="p-0 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left">日時</th>
                            <th class="px-4 py-2 text-left">タイプ</th>
                            <th class="px-4 py-2 text-left">IP</th>
                            <th class="px-4 py-2 text-left">ステータス</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($recent_logs)): ?>
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">ログがありません</td></tr>
                    <?php else: foreach ($recent_logs as $row): ?>
                        <tr class="border-t hover:bg-gray-50">
                            <td class="px-4 py-2 font-mono"><?= h($row['detected_at'] ?? '') ?></td>
                            <td class="px-4 py-2"><?= h($row['attack_type'] ?? '') ?></td>
                            <td class="px-4 py-2 font-mono"><?= h($row['ip_address'] ?? '') ?></td>
                            <td class="px-4 py-2">
                                <?php $st = (int)($row['status_code'] ?? 0); ?>
                                <span class="px-2 py-0.5 rounded text-xs <?= $st===200?'bg-green-100 text-green-800':($st===403?'bg-red-100 text-red-800':'bg-gray-100 text-gray-800') ?>"><?= h($st) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow border overflow-hidden">
            <div class="p-5 border-b flex items-center justify-between">
                <h2 class="text-lg font-semibold">ファイル活動タイムライン（演習）</h2>
                <span class="text-sm text-gray-500">Ransomware File Activity</span>
            </div>
            <div class="p-0 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left">日時</th>
                            <th class="px-4 py-2 text-left">概要</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($recent_activity)): ?>
                        <tr><td colspan="2" class="px-4 py-6 text-center text-gray-500">記録がありません</td></tr>
                    <?php else: foreach ($recent_activity as $row): ?>
                        <tr class="border-t hover:bg-gray-50">
                            <td class="px-4 py-2 font-mono"><?= h($row['detected_at'] ?? '') ?></td>
                            <td class="px-4 py-2 break-words">
                                <?php
                                $msg = (string)($row['malicious_input'] ?? '');
                                // simple emphasis for ENCRYPT/RESTORE tokens
                                $msg = str_replace('ENCRYPT', '<strong class="text-red-600">ENCRYPT</strong>', h($msg));
                                $msg = str_replace('RESTORE', '<strong class="text-green-700">RESTORE</strong>', $msg);
                                echo $msg;
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
</body>
</html>
