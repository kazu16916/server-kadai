<?php
require_once __DIR__ . '/common_init.php';
require 'db.php';

if (!isset($_SESSION['role'])  && $current !== 'logout.php') {
    header('Location: list.php');
    exit;
}

// 各攻撃演習モードの状態を取得
$dictionary_attack_enabled      = $_SESSION['dictionary_attack_enabled']      ?? false;
$bruteforce_enabled             = $_SESSION['bruteforce_enabled']             ?? false;
$trusted_admin_bypass_enabled   = $_SESSION['trusted_admin_bypass_enabled']   ?? false;
$keylogger_enabled              = $_SESSION['keylogger_enabled']              ?? false;
$ransomware_enabled             = $_SESSION['ransomware_enabled']             ?? false;
$tamper_enabled                 = $_SESSION['tamper_enabled']                 ?? false;
$reverse_bruteforce_enabled     = $_SESSION['reverse_bruteforce_enabled']     ?? false;
$joe_account_attack_enabled     = $_SESSION['joe_account_attack_enabled']     ?? false; // ★ 追加

// サンプルの攻撃者プロファイル
$attackers = [
    ['name' => 'アメリカのWebサーバー', 'ip' => '204.79.197.200', 'type' => 'Datacenter (US)', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36'],
    ['name' => 'ヨーロッパのVPNサービス', 'ip' => '89.187.167.53', 'type' => 'VPN (EU)', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; rv:102.0) Gecko/20100101 Firefox/102.0'],
    ['name' => 'Tor匿名ネットワーク', 'ip' => '185.220.101.30', 'type' => 'Tor Exit Node', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; rv:102.0) Gecko/20100101 Firefox/102.0'],
    ['name' => 'アジアのボットネット', 'ip' => '103.137.186.25', 'type' => 'Botnet (Asia)', 'user_agent' => 'curl/7.68.0'],
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>攻撃シミュレーションツール</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<?php include 'header.php'; ?>
<div class="container mx-auto mt-10 p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-2">攻撃シミュレーションツール</h1>
    <p class="text-gray-600 mb-8">IPアドレスの偽装や、特定の攻撃演習を有効化できます。</p>

    <!-- IPシミュレーション -->
    <div class="bg-white p-8 rounded-lg shadow-md mb-8">
        <h2 class="text-2xl font-bold text-center mb-6">IPアドレス シミュレーション</h2>
        <?php if (isset($_SESSION['simulated_ip'])): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
                <p class="font-bold">シミュレーション実行中:</p>
                <p>現在、あなたは <strong><?= htmlspecialchars($_SESSION['simulated_ip']) ?> (<?= htmlspecialchars($_SESSION['simulated_type']) ?>)</strong> として記録されています。</p>
            </div>
        <?php endif; ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($attackers as $attacker): ?>
                <div class="bg-white p-6 rounded-lg border">
                    <h3 class="text-lg font-semibold"><?= htmlspecialchars($attacker['name']) ?></h3>
                    <code class="block bg-gray-100 p-2 rounded mt-2 text-sm"><?= htmlspecialchars($attacker['ip']) ?></code>
                    <form action="set_simulation_ip.php" method="POST" class="mt-4">
                        <input type="hidden" name="ip" value="<?= htmlspecialchars($attacker['ip']) ?>">
                        <input type="hidden" name="type" value="<?= htmlspecialchars($attacker['type']) ?>">
                        <input type="hidden" name="user_agent" value="<?= htmlspecialchars($attacker['user_agent']) ?>">
                        <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 text-sm">この攻撃者になる</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-6 text-center">
            <form action="set_simulation_ip.php" method="POST">
                <input type="hidden" name="stop" value="true">
                <button type="submit" class="text-gray-600 hover:underline">IPシミュレーションを停止</button>
            </form>
        </div>
    </div>

    <!-- 高度な攻撃演習の許可 -->
    <div class="bg-white p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-center mb-6">高度な攻撃演習の有効化</h2>
        <div class="mb-6 text-center">
            <form action="toggle_attack_mode.php" method="POST" class="inline-block">
                <input type="hidden" name="attack_type" value="all_enable">
                <button type="submit" class="bg-green-600 text-white py-2 px-6 rounded-lg hover:bg-green-700">全て有効化</button>
            </form>
            <form action="toggle_attack_mode.php" method="POST" class="inline-block">
                <input type="hidden" name="attack_type" value="all_disable">
                <button type="submit" class="bg-gray-600 text-white py-2 px-6 rounded-lg hover:bg-gray-700">全て無効化</button>
            </form>
        </div>

        <div class="space-y-4 border-t pt-6">
            <!-- 辞書攻撃 -->
            <div class="flex justify-between items-center p-4 border rounded-lg">
                <div>
                    <h3 class="font-semibold text-lg">辞書攻撃</h3>
                    <p class="text-sm text-gray-600">ログインページに辞書攻撃ボタンを表示します。</p>
                </div>
                <form action="toggle_attack_mode.php" method="POST">
                    <input type="hidden" name="attack_type" value="dictionary_attack">
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold <?= $dictionary_attack_enabled ? 'bg-gray-500 text-white' : 'bg-red-500 text-white' ?>">
                        <?= $dictionary_attack_enabled ? '無効化' : '有効化' ?>
                    </button>
                </form>
            </div>

            <!-- 総当たり攻撃 -->
            <div class="flex justify-between items-center p-4 border rounded-lg">
                <div>
                    <h3 class="font-semibold text-lg">総当たり攻撃 (ビジュアル)</h3>
                    <p class="text-sm text-gray-600">ログインページに総当たり攻撃のシミュレーターを表示します。</p>
                </div>
                <form action="toggle_attack_mode.php" method="POST">
                    <input type="hidden" name="attack_type" value="bruteforce">
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold <?= $bruteforce_enabled ? 'bg-gray-500 text-white' : 'bg-red-500 text-white' ?>">
                        <?= $bruteforce_enabled ? '無効化' : '有効化' ?>
                    </button>
                </form>
            </div>

            <!-- 逆ブルートフォース攻撃 -->
            <div class="flex justify-between items-center p-4 border rounded-lg">
                <div>
                    <h3 class="font-semibold text-lg">逆総当たり攻撃</h3>
                    <p class="text-sm text-gray-600">
                        1つのパスワードに対して複数のユーザー名を試行する攻撃手法の演習です。
                        一般的なユーザー名辞書を使用してアカウント発見を試みます。
                    </p>
                </div>
                <form action="toggle_attack_mode.php" method="POST">
                    <input type="hidden" name="attack_type" value="reverse_bruteforce">
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold <?= $reverse_bruteforce_enabled ? 'bg-gray-500 text-white' : 'bg-red-600 text-white' ?>">
                        <?= $reverse_bruteforce_enabled ? '無効化' : '有効化' ?>
                    </button>
                </form>
            </div>

            <!-- ★ ジョーアカウント攻撃（スプレー） -->
            <div class="flex justify-between items-center p-4 border rounded-lg">
                <div>
                    <h3 class="font-semibold text-lg">ジョーアカウント攻撃（スプレー）</h3>
                    <p class="text-sm text-gray-600">
                        既定名（joe/jdoe/john 等）や指定パターンのユーザーに対して、よくあるパスワードを薄く広く試行する演習です。
                        ログインページに専用UIを表示します。
                    </p>
                </div>
                <form action="toggle_attack_mode.php" method="POST">
                    <input type="hidden" name="attack_type" value="joe_account_attack">
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold <?= $joe_account_attack_enabled ? 'bg-gray-500 text-white' : 'bg-red-600 text-white' ?>">
                        <?= $joe_account_attack_enabled ? '無効化' : '有効化' ?>
                    </button>
                </form>
            </div>

            <!-- バックドア -->
            <div class="flex justify-between items-center p-4 border rounded-lg">
                <div>
                    <h3 class="font-semibold text-lg">バックドア</h3>
                    <p class="text-sm text-gray-600">
                        有効の場合: 信頼済みIPから <code>admin</code> はパスワード無しでログイン（自動ログイン/ワンクリック再ログイン）できます。<br>
                        無効の場合: たとえ信頼済みIPでも <code>admin</code> はパスワード必須になります。
                    </p>
                </div>
                <form action="toggle_attack_mode.php" method="POST">
                    <input type="hidden" name="attack_type" value="trusted_admin_bypass">
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold <?= $trusted_admin_bypass_enabled ? 'bg-gray-500 text-white' : 'bg-red-500 text-white' ?>">
                        <?= $trusted_admin_bypass_enabled ? '無効化' : '有効化' ?>
                    </button>
                </form>
            </div>

            <!-- キーロガー -->
            <div class="flex justify-between items-center p-4 border rounded-lg">
                <div>
                    <h3 class="font-semibold text-lg">キーロガー（演習）</h3>
                    <p class="text-sm text-gray-600">
                        有効の場合、ログインページの「ユーザー名」「パスワード」入力フィールドのキー入力を記録します（演習用途）。
                        ヘッダーにも「キーロガー有効（演習）」の注意を表示します。
                    </p>
                </div>
                <form action="toggle_attack_mode.php" method="POST">
                    <input type="hidden" name="attack_type" value="keylogger">
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold <?= $keylogger_enabled ? 'bg-gray-500 text-white' : 'bg-red-500 text-white' ?>">
                        <?= $keylogger_enabled ? '無効化' : '有効化' ?>
                    </button>
                </form>
            </div>

            <!-- ランサムウェア演習 -->
            <div class="flex justify-between items-center p-4 border rounded-lg">
                <div>
                    <h3 class="font-semibold text-lg">ランサムウェア演習</h3>
                    <p class="text-sm text-gray-600">
                        有効の場合、特定のページにランサムウェアの疑似攻撃コンテンツを表示する演習メニューを表示します。
                        シグネチャ検知も有効になります（演習用途）。
                    </p>
                </div>
                <form action="toggle_attack_mode.php" method="POST">
                    <input type="hidden" name="attack_type" value="ransomware">
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold <?= $ransomware_enabled ? 'bg-gray-500 text-white' : 'bg-red-600 text-white' ?>">
                        <?= $ransomware_enabled ? '無効化' : '有効化' ?>
                    </button>
                </form>
            </div>

            <!-- 改ざん攻撃 -->
            <div class="flex justify-between items-center p-4 border rounded-lg">
                <div>
                    <h3 class="font-semibold text-lg">改ざん攻撃（演習）</h3>
                    <p class="text-sm text-gray-600">
                        有効の場合、<code>simulation_files</code> 内の模擬ファイル改ざんと、
                        防御側のハッシュ検証画面（ベースライン作成/更新・検証）を利用できます。
                    </p>
                </div>
                <form action="toggle_attack_mode.php" method="POST">
                    <input type="hidden" name="attack_type" value="tamper">
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold <?= $tamper_enabled ? 'bg-gray-500 text-white' : 'bg-red-600 text-white' ?>">
                        <?= $tamper_enabled ? '無効化' : '有効化' ?>
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>
</body>
</html>
