<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body>
<nav class="bg-white border-b border-gray-200">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between h-16">

      <!-- 左側：アプリ名 -->
      <div class="flex-shrink-0 flex items-center">
        <a href="index.php" class="text-xl font-bold text-gray-800">投票アプリ</a>
      </div>

      <!-- ハンバーガーボタン（md以下で表示） -->
      <div class="flex items-center md:hidden">
        <button id="menu-btn" class="text-gray-600 hover:text-gray-800 focus:outline-none">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
      </div>

      <!-- PC用メニュー（md以上で表示） -->
      <div class="hidden md:flex md:items-center md:space-x-6">
        <a href="help.php" class="text-gray-600 hover:text-gray-900">ヘルプ</a>
        <a href="driveby_landing.php" class="text-gray-600 hover:text-gray-900">提供プログラム</a>
        <a href="user_logs.php" class="text-blue-600 hover:underline">ユーザー活動ログ検索</a>
        <a href="simulation_tools.php" class="text-green-600 hover:underline">攻撃シミュレーション</a>
        <a href="ids_dashboard.php" class="text-red-600 hover:underline">IDSダッシュボード</a>
        <a href="waf_ids_config.php" class="text-yellow-600 hover:underline">WAF/IDS設定</a>

        <!-- 右端にユーザー情報 -->
        <?php if (!empty($_SESSION['username'])): ?>
          <span class="text-gray-700">ようこそ、<strong><?= htmlspecialchars($_SESSION['username']) ?></strong> さん</span>
          <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded">ログアウト</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- モバイル/タブレット用メニュー -->
  <div id="mobile-menu" class="hidden md:hidden px-4 pt-2 pb-3 space-y-2">
    <a href="help.php" class="block text-gray-600 hover:text-gray-900">ヘルプ</a>
    <a href="driveby_landing.php" class="block text-gray-600 hover:text-gray-900">提供プログラム</a>
    <a href="user_logs.php" class="block text-blue-600 hover:underline">ユーザー活動ログ検索</a>
    <a href="simulation_tools.php" class="block text-green-600 hover:underline">攻撃シミュレーション</a>
    <a href="ids_dashboard.php" class="block text-red-600 hover:underline">IDSダッシュボード</a>
    <a href="waf_ids_config.php" class="block text-yellow-600 hover:underline">WAF/IDS設定</a>

    <?php if (!empty($_SESSION['username'])): ?>
      <div class="mt-2 border-t pt-2">
        <span class="block text-gray-700">ようこそ、<strong><?= htmlspecialchars($_SESSION['username']) ?></strong> さん</span>
        <a href="logout.php" class="mt-2 block bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded">ログアウト</a>
      </div>
    <?php endif; ?>
  </div>
</nav>

<script>
  // ハンバーガーメニュー開閉
  document.getElementById('menu-btn').addEventListener('click', function () {
    document.getElementById('mobile-menu').classList.toggle('hidden');
  });
</script>
</body>
</html>
