<?php
$forceHamburger = !empty($_SESSION['force_hamburger']);
$mobileMenuLgClass = $forceHamburger ? '' : 'lg:hidden';
?>
<header class="bg-white shadow-md">
  <nav class="container mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex h-14 items-center justify-between">
      <!-- Left: Brand -->
      <div class="flex items-center gap-3">
        <a href="list.php" class="text-lg sm:text-xl font-bold text-gray-800 whitespace-nowrap">投票アプリ</a>

        <!-- Desktop: primary links -->
        <div class="<?php echo $forceHamburger ? 'hidden' : 'hidden lg:flex'; ?> items-center gap-4">
          <a href="view_doc.php?page=usage.txt" class="text-gray-600 hover:text-blue-600">ヘルプ</a>
          <a href="driveby_landing.php" class="text-gray-600 hover:text-blue-600">提供プログラム</a>
          <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="diag.php" class="text-blue-600 hover:text-blue-800 font-semibold">ユーザー活動ログ検索</a>
            <a href="simulation_tools.php" class="text-green-600 hover:text-green-800 font-semibold">攻撃シミュレーション</a>
            <a href="ids_dashboard.php" class="text-red-600 hover:text-red-800 font-semibold">IDSダッシュボード</a>
            <a href="waf_settings.php" class="text-yellow-600 hover:text-yellow-800 font-semibold">WAF/IDS設定</a>
            <?php if (!empty($_SESSION['keylogger_enabled'])): ?>
              <a href="attacker_console.php" class="text-indigo-600 hover:text-indigo-800 font-semibold">攻撃者コンソール</a>
            <?php endif; ?>
            <?php if (!empty($_SESSION['ransomware_enabled'])): ?>
              <a href="enhanced_ransomware_exercise.php" class="text-red-600 hover:text-red-800 font-semibold">ランサムウェア演習</a>
            <?php endif; ?>
            <?php if (!empty($_SESSION['tamper_enabled'])): ?>
              <a href="tamper_attack.php" class="text-purple-700 hover:text-purple-900 font-semibold">改ざん攻撃（演習）</a>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right: user area (desktop) -->
      <div class="<?php echo $forceHamburger ? 'hidden' : 'hidden lg:flex'; ?> items-center gap-3">
        <?php if (isset($_SESSION['user_id'])): ?>
          <a href="profile.php" class="text-sm text-gray-700 truncate max-w-[16rem]">
            ようこそ, <span class="font-medium"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></span> さん
          </a>
          <a href="logout.php"
             class="inline-flex items-center rounded-md bg-red-500 px-3 py-2 text-sm font-semibold text-white hover:bg-red-600">
            ログアウト
          </a>
        <?php else: ?>
          <a href="login.php" class="text-sm text-gray-700 hover:text-blue-600">ログイン</a>
          <a href="register.php"
             class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            新規登録
          </a>
        <?php endif; ?>
      </div>

      <!-- Mobile: hamburger -->
      <div class="<?php echo $forceHamburger ? 'flex' : 'flex lg:hidden'; ?>">
        <button id="navToggle"
                class="inline-flex items-center justify-center rounded-md p-2 text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                aria-controls="mobileMenu" aria-expanded="false" aria-label="メニューを開く/閉じる">
          <svg id="iconOpen" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
          <svg id="iconClose" class="hidden h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>
  </nav>

  <!-- Mobile menu -->
  <div id="mobileMenu" class="hidden <?php echo $mobileMenuLgClass; ?> bg-white border-t border-gray-200 shadow-md">
    <div class="px-4 py-3 space-y-2">
      <a href="view_doc.php?page=usage.txt" class="block text-gray-700 hover:text-blue-600">ヘルプ</a>
      <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <a href="diag.php" class="block text-blue-600 hover:text-blue-800 font-semibold">ユーザー活動ログ検索</a>
        <a href="simulation_tools.php" class="block text-green-600 hover:text-green-800 font-semibold">攻撃シミュレーション</a>
        <a href="ids_dashboard.php" class="block text-red-600 hover:text-red-800 font-semibold">IDSダッシュボード</a>
        <a href="waf_settings.php" class="block text-yellow-600 hover:text-yellow-800 font-semibold">WAF/IDS設定</a>
        <?php if (!empty($_SESSION['keylogger_enabled'])): ?>
          <a href="attacker_console.php" class="block text-indigo-600 hover:text-indigo-800 font-semibold">攻撃者コンソール</a>
        <?php endif; ?>
        <?php if (!empty($_SESSION['ransomware_enabled'])): ?>
          <a href="enhanced_ransomware_exercise.php" class="block text-red-600 hover:text-red-800 font-semibold">ランサムウェア演習</a>
        <?php endif; ?>
        <?php if (!empty($_SESSION['tamper_enabled'])): ?>
          <a href="tamper_attack.php" class="block text-purple-700 hover:text-purple-900 font-semibold">改ざん攻撃（演習）</a>
        <?php endif; ?>
      <?php endif; ?>

      <div class="border-t border-gray-200 pt-3">
        <?php if (isset($_SESSION['user_id'])): ?>
          <a href="profile.php" class="block text-gray-700">ようこそ, <?= htmlspecialchars($_SESSION['username'] ?? '') ?> さん</a>
          <a href="logout.php" class="mt-2 block bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 text-center">ログアウト</a>
        <?php else: ?>
          <a href="login.php" class="block text-gray-700 hover:text-blue-600">ログイン</a>
          <a href="register.php" class="mt-2 block bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 text-center">新規登録</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if (!empty($_SESSION['ransomware_enabled'])): ?>
    <!-- ランサムウェア演習有効時の注意表示 -->
    <div class="bg-red-600 text-white text-center py-2 text-sm">
      ⚠️ ランサムウェア演習モードが有効です（演習用途） - 実際の攻撃ではありません
    </div>
  <?php endif; ?>
</header>

<script>
  const toggleBtn = document.getElementById('navToggle');
  const mobileMenu = document.getElementById('mobileMenu');
  const iconOpen = document.getElementById('iconOpen');
  const iconClose = document.getElementById('iconClose');
  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      const expanded = mobileMenu.classList.contains('hidden');
      mobileMenu.classList.toggle('hidden');
      iconOpen.classList.toggle('hidden');
      iconClose.classList.toggle('hidden');
      toggleBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    });
  }
</script>
