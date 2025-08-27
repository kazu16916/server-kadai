<?php
require_once __DIR__ . '/common_init.php';
require 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

/**
 * 文字列ルール（WAFブラックリスト）
 * - is_custom = TRUE … 管理画面で追加したカスタム
 * - is_custom = FALSE … デフォルト内蔵シグネチャ（閲覧のみ）
 */
$custom_stmt   = $pdo->query("SELECT * FROM waf_blacklist WHERE is_custom = TRUE ORDER BY created_at DESC");
$custom_rules  = $custom_stmt->fetchAll();

$default_stmt  = $pdo->query("SELECT * FROM waf_blacklist WHERE is_custom = FALSE ORDER BY id ASC");
$default_rules = $default_stmt->fetchAll();

/**
 * IPブロックルール（IPS）
 * - テーブル例: waf_ip_blocklist(id, ip_pattern, action, description, is_custom, created_at)
 *   ip_pattern … 正確一致 / ワイルドカード（例: 203.0.113.*）/ CIDR（例: 203.0.113.0/24, 2001:db8::/32）
 *   action     … 'block'（即時遮断） / 'monitor'（通過させつつ記録）
 */
try {
    $ip_custom_stmt   = $pdo->query("SELECT * FROM waf_ip_blocklist WHERE is_custom = TRUE ORDER BY created_at DESC");
    $ip_custom_rules  = $ip_custom_stmt->fetchAll();
} catch (Throwable $e) {
    $ip_custom_rules = [];
}
try {
    $ip_default_stmt  = $pdo->query("SELECT * FROM waf_ip_blocklist WHERE is_custom = FALSE ORDER BY id ASC");
    $ip_default_rules = $ip_default_stmt->fetchAll();
} catch (Throwable $e) {
    $ip_default_rules = [];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>WAF/IDS設定</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<?php include 'header.php'; ?>
<div class="container mx-auto mt-10 p-4">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">WAF/IDS設定 - ルールの管理</h1>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- 左カラム：追加フォーム -->
        <div class="md:col-span-1 space-y-8">

            <!-- 文字列（ペイロード）検知ルール 追加 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">新しいカスタムルールを追加（文字列検知）</h2>
                <form action="add_blocked_word.php" method="POST">
                    <div class="mb-4">
                        <label for="pattern" class="block text-gray-700">検知する文字列</label>
                        <input type="text" id="pattern" name="pattern" class="w-full px-3 py-2 border rounded-lg" required>
                    </div>
                    <div class="mb-4">
                        <label for="description" class="block text-gray-700">説明</label>
                        <input type="text" id="description" name="description" class="w-full px-3 py-2 border rounded-lg" placeholder="例）SQLiっぽいペイロード など">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700">アクション</label>
                        <select name="action" class="w-full px-3 py-2 border rounded-lg">
                            <option value="detect" selected>検知のみ (IDS)</option>
                            <option value="block">ブロック (IPS)</option>
                        </select>
                    </div>
                    <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600">追加</button>
                </form>
            </div>

            <!-- IPブロック 追加 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">新しいIPルールを追加（IPS）</h2>
                <form action="add_blocked_ip.php" method="POST">
                    <div class="mb-4">
                        <label for="ip_pattern" class="block text-gray-700">IPパターン</label>
                        <input type="text" id="ip_pattern" name="ip_pattern" class="w-full px-3 py-2 border rounded-lg" required placeholder="例）203.0.113.5 / 203.0.113.* / 203.0.113.0/24 / 2001:db8::/32">
                        <p class="text-xs text-gray-500 mt-1">
                            正確一致・ワイルドカード（IPv4のみ）・CIDR（IPv4/IPv6）に対応
                        </p>
                    </div>
                    <div class="mb-4">
                        <label for="ip_description" class="block text-gray-700">説明</label>
                        <input type="text" id="ip_description" name="description" class="w-full px-3 py-2 border rounded-lg" placeholder="例）怪しいスキャン元、テスト用 など">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700">アクション</label>
                        <select name="action" class="w-full px-3 py-2 border rounded-lg">
                            <option value="block" selected>ブロック（403で遮断）</option>
                            <option value="monitor">モニタ（許可して記録）</option>
                        </select>
                    </div>
                    <button type="submit" class="w-full bg-red-600 text-white py-2 rounded-lg hover:bg-red-700">IPルールを追加</button>
                </form>
            </div>
        </div>

        <!-- 右カラム：一覧 -->
        <div class="md:col-span-2 space-y-8">

            <!-- カスタム 文字列ルール一覧 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">カスタムルール一覧（文字列検知）</h2>
                <div class="space-y-3">
                    <?php if (empty($custom_rules)): ?>
                        <p class="text-gray-500">カスタムルールはまだありません。</p>
                    <?php else: ?>
                        <?php foreach ($custom_rules as $item): ?>
                            <div class="flex justify-between items-center p-3 border rounded-lg">
                                <div>
                                    <code class="bg-gray-200 p-1 rounded font-mono"><?= htmlspecialchars($item['pattern']) ?></code>
                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($item['description']) ?></p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $item['action'] === 'block' ? 'bg-red-200 text-red-800' : 'bg-yellow-200 text-yellow-800' ?>">
                                        <?= $item['action'] === 'block' ? 'ブロック' : '検知のみ' ?>
                                    </span>
                                    <form action="delete_blocked_word.php" method="POST" onsubmit="return confirm('このルールを削除しますか？');">
                                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                        <button type="submit" class="text-red-500 hover:text-red-700">削除</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- デフォルト 文字列ルール -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <button id="toggle-default-rules" class="text-blue-600 hover:underline font-semibold">
                    デフォルトの検知シグネチャ (<?= count($default_rules) ?>件) を表示する
                </button>
                <div id="default-rules-container" class="hidden mt-4 space-y-3">
                    <?php foreach ($default_rules as $item): ?>
                        <div class="flex justify-between items-center p-3 border rounded-lg bg-gray-50">
                            <div>
                                <code class="bg-gray-200 p-1 rounded font-mono"><?= htmlspecialchars($item['pattern']) ?></code>
                                <p class="text-sm text-gray-600"><?= htmlspecialchars($item['description']) ?></p>
                            </div>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-200 text-gray-800">デフォルト (検知のみ)</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- カスタム IP ルール一覧 -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">IPルール一覧（IPS）</h2>
                <div class="space-y-3">
                    <?php if (empty($ip_custom_rules)): ?>
                        <p class="text-gray-500">カスタムIPルールはまだありません。</p>
                    <?php else: ?>
                        <?php foreach ($ip_custom_rules as $ip): ?>
                            <div class="flex justify-between items-center p-3 border rounded-lg">
                                <div>
                                    <code class="bg-gray-200 p-1 rounded font-mono"><?= htmlspecialchars($ip['ip_pattern']) ?></code>
                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($ip['description'] ?? '') ?></p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <?php
                                        $isBlock = (isset($ip['action']) && $ip['action'] === 'block');
                                    ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $isBlock ? 'bg-red-200 text-red-800' : 'bg-yellow-200 text-yellow-800' ?>">
                                        <?= $isBlock ? 'ブロック' : 'モニタ' ?>
                                    </span>
                                    <form action="delete_blocked_ip.php" method="POST" onsubmit="return confirm('このIPルールを削除しますか？');">
                                        <input type="hidden" name="id" value="<?= (int)$ip['id'] ?>">
                                        <button type="submit" class="text-red-500 hover:text-red-700">削除</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- デフォルト IP ルール一覧（任意・閲覧のみ） -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <button id="toggle-default-ip-rules" class="text-blue-600 hover:underline font-semibold">
                    デフォルトのIPシグネチャ (<?= count($ip_default_rules) ?>件) を表示する
                </button>
                <div id="default-ip-rules-container" class="hidden mt-4 space-y-3">
                    <?php foreach ($ip_default_rules as $ip): ?>
                        <div class="flex justify-between items-center p-3 border rounded-lg bg-gray-50">
                            <div>
                                <code class="bg-gray-200 p-1 rounded font-mono"><?= htmlspecialchars($ip['ip_pattern']) ?></code>
                                <p class="text-sm text-gray-600"><?= htmlspecialchars($ip['description'] ?? '') ?></p>
                            </div>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-200 text-gray-800">
                                デフォルト（<?= ($ip['action'] === 'block' ? 'ブロック' : 'モニタ') ?>）
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.getElementById('toggle-default-rules')?.addEventListener('click', function() {
    const container = document.getElementById('default-rules-container');
    container.classList.toggle('hidden');
    this.textContent = container.classList.contains('hidden')
        ? 'デフォルトの検知シグネチャを表示する'
        : 'デフォルトの検知シグネチャを隠す';
});

document.getElementById('toggle-default-ip-rules')?.addEventListener('click', function() {
    const container = document.getElementById('default-ip-rules-container');
    container.classList.toggle('hidden');
    this.textContent = container.classList.contains('hidden')
        ? 'デフォルトのIPシグネチャを表示する'
        : 'デフォルトのIPシグネチャを隠す';
});
</script>
</body>
</html>
