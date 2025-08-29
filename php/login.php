<?php
// セッション開始
session_start();

require_once __DIR__ . '/db.php'; // データベース接続ファイル
$stmt = $pdo->query("SELECT username, password FROM users");
// ['username' => 'password'] の形式の連想配列として取得
$all_users_from_db = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); 

$noauto = (isset($_GET['noauto']) && $_GET['noauto'] === '1');

// 信頼IPと模擬IP（simulation_tools で設定）
$trusted_ip   = $_SESSION['trusted_ip']   ?? '';
$simulated_ip = $_SESSION['simulated_ip'] ?? '';

// ★ デフォルトは安全側（無効）
$trusted_admin_bypass_enabled = isset($_SESSION['trusted_admin_bypass_enabled'])
    ? (bool)$_SESSION['trusted_admin_bypass_enabled']
    : false;

// ★ バイパス"有効"かつ IP 一致の時だけ true
$trusted_match = ($trusted_admin_bypass_enabled
    && !empty($trusted_ip)
    && !empty($simulated_ip)
    && hash_equals($trusted_ip, $simulated_ip));

// ★ 自動ログインは「noauto=1 でない」かつ「trusted_match=true」の時のみ
if (!$noauto && $trusted_match) {
    // IDS ログ（許可されたIPからの admin パスワードレス自動ログイン）
    require_once __DIR__ . '/db.php';
    if (function_exists('log_attack')) {
        $ip_for_log = $_SESSION['simulated_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        log_attack($pdo, 'Trusted IP Admin Bypass Login', 'auto-login (login.php)', $ip_for_log, 200);
    }

    $_SESSION['user_id'] = 1; // 演習用 admin ID（環境に合わせて）
    $_SESSION['role']    = 'admin';
    header('Location: list.php');
    exit;
}

// すでにログイン済みなら一覧へ
if (isset($_SESSION['user_id'])) {
    header('Location: list.php');
    exit;
}

// 攻撃演習モードの状態（UI表示制御）
$bruteforce_enabled      = $_SESSION['bruteforce_enabled']      ?? false;
$dictionary_attack_enabled = $_SESSION['dictionary_attack_enabled'] ?? false;
$reverse_bruteforce_enabled = $_SESSION['reverse_bruteforce_enabled'] ?? false;
$joe_account_attack_enabled = $_SESSION['joe_account_attack_enabled'] ?? false;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ログイン</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
      :root{
        /* 1行に並べる最大タイル数（画面幅で自動縮小） */
        --slot-cols: 12;
        /* タイルの基本幅（画面幅に応じて clamp で自動縮小） */
        --slot-size: clamp(2.1rem, min(3.2rem, 7vw), 3.2rem);
      }
      .slot-wrap{
        background:#0b1020;border-radius:12px;padding:10px;
        display:flex;flex-wrap:wrap;gap:.35rem; /* ← 折り返し */
        max-width:100%;
      }
      .char-slot{
        width:var(--slot-size);height:calc(var(--slot-size) + 1rem);
        border-radius:.6rem;display:flex;align-items:center;justify-content:center;position:relative;
        background:linear-gradient(180deg,#0f172a,#111827 60%,#0b1020);
        border:2px solid #1f2937; box-shadow:inset 0 0 10px rgba(0,0,0,.6),0 1px 0 #0ea5e9;
        flex:0 0 auto; /* 固定幅のタイルとして扱う */
      }
      .char-slot .reel{
        font-family:'JetBrains Mono','Courier New',monospace;
        font-weight:800;font-size:calc(var(--slot-size) * .55);
        letter-spacing:.02em;color:#e2e8f0;text-shadow:0 0 10px rgba(59,130,246,.25);
        line-height:1;
      }
      .char-slot.spinning .reel{ animation:spinBlur .12s linear infinite }
      @keyframes spinBlur{ 0%{filter:blur(0px);opacity:.85} 50%{filter:blur(1.2px);opacity:1} 100%{filter:blur(0px);opacity:.85} }
      .char-slot.testing{border-color:#3b82f6;box-shadow:0 0 16px rgba(59,130,246,.35)}
      .char-slot.found{
        background:linear-gradient(180deg,#052e2b,#064e3b);
        border-color:#10b981;box-shadow:0 0 20px rgba(16,185,129,.55), inset 0 0 12px rgba(0,0,0,.35);
      }
      .char-slot .lock{ position:absolute;right:.25rem;bottom:.15rem;font-size:.9rem;opacity:.7 }
      .progress-bar{height:8px;background:#1f2937;border-radius:6px;overflow:hidden}
      .progress-fill{height:100%;width:0%;background:linear-gradient(90deg,#ef4444,#f59e0b);transition:width .25s}

      /* 可視化カード */
      .attack-display{background:#0b1020;border-radius:12px;padding:16px;margin:0;color:#93c5fd;display:none}
      .attack-display.active{display:block}
      .attack-log{
        background:#020617;color:#9ca3af;font-family:'JetBrains Mono',monospace;font-size:.85rem;
        padding:12px;border-radius:8px;max-height:180px;overflow:auto;margin-top:10px;word-break:break-all;
      }
      .attack-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:10px 0}
      .stat-item{background:#0f172a;padding:10px;border-radius:8px;text-align:center;border:1px solid #1f2937}
      .stat-value{font-weight:800;color:#f59e0b}

      /* ===== レイアウト：ボタン群と可視化を横並びに ===== */
      /* PCでは2カラム、スマホでは縦積み */
      #attack-area{display:grid;gap:16px}
      @media (min-width: 768px){
        #attack-area{grid-template-columns: 1fr 1fr; align-items:start;}
      }
    </style>

</head>
<body class="bg-gray-100">
<div class="container mx-auto mt-10 p-4 max-w-[1024px]">
    <div class="bg-white p-8 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold text-center mb-6">ログイン</h1>

        <?php if ($simulated_ip): ?>
            <div class="bg-blue-50 border-l-4 border-blue-400 text-blue-700 p-3 mb-4 text-sm">
                現在の模擬IP: <strong><?= htmlspecialchars($simulated_ip) ?></strong>
                <?php if ($trusted_ip): ?> / 信頼IP: <strong><?= htmlspecialchars($trusted_ip) ?></strong><?php endif; ?>
                <?php if ($noauto): ?> / 自動ログイン抑止: <strong>ON</strong><?php endif; ?>
                <br>
                信頼IPの admin パスワードレス許可:
                <strong><?= $trusted_admin_bypass_enabled ? '有効' : '無効' ?></strong>
            </div>
        <?php endif; ?>

        <div id="message-area" class="text-center mb-4">
            <?php if (isset($_GET['error'])): ?>
                <p class="text-red-500"><?= htmlspecialchars($_GET['error']) ?></p>
            <?php endif; ?>
            <?php if (isset($_GET['success'])): ?>
                <p class="text-green-500"><?= htmlspecialchars($_GET['success']) ?></p>
            <?php endif; ?>

            <?php if ($noauto && $trusted_match): ?>
                <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 p-3 rounded mt-3 text-sm">
                    バックドアを設置しており、<br>パスワードなしでログインできます。
                </div>
                <form id="quick-admin-login-form" action="login_process.php" method="POST" class="mt-3">
                    <input type="hidden" name="username" value="admin">
                    <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-lg hover:bg-indigo-700">
                        admin にログイン
                    </button>
                </form>
            <?php endif; ?>
            <?php if (!empty($_SESSION['keylogger_enabled'])): ?>
                <div class="bg-red-50 border-l-4 border-red-400 text-red-700 p-3 mb-4 text-sm">
                    <strong>注意（演習）：</strong> キーロガーが有効です。入力したキーが記録・表示されます。
                </div>
            <?php endif; ?>
        </div>

        <form id="login-form" action="login_process.php" method="POST">
            <div class="mb-4">
                <label for="username" class="block text-gray-700">ユーザー名</label>
                <input type="text" name="username" id="username" class="w-full px-3 py-2 border rounded-lg" required placeholder="ユーザー名を入力">
            </div>
            <div class="mb-6">
                <label for="password" class="block text-gray-700">パスワード</label>
                <input type="password" name="password" id="password" class="w-full px-3 py-2 border rounded-lg">
                <p class="text-xs text-gray-500 mt-1">
                    <?php if ($trusted_admin_bypass_enabled): ?>
                        ※ admin で信頼IP一致の場合はパスワード不要でログインできます（演習仕様）
                    <?php else: ?>
                        
                    <?php endif; ?>
                </p>
            </div>
            <button type="submit" class="w-full bg-green-500 text-white py-2 rounded-lg hover:bg-green-600">ログイン</button>
        </form>

        <!-- ここから置き換え -->
        <?php if ($bruteforce_enabled || $dictionary_attack_enabled): ?>
        <div id="attack-area" class="mt-6 border-t pt-4">
          <!-- 左：操作パネル -->
          <div>
            <?php if ($bruteforce_enabled): ?>
              <div class="mb-3">
                <label for="password-length" class="block text-gray-700 text-sm mb-1">攻撃対象パスワード桁数</label>
                <select id="password-length" class="w-full px-3 py-2 border rounded-lg text-sm">
                  <?php for ($i = 1; $i <= 15; $i++): ?>
                    <option value="<?= $i ?>" <?= $i === 6 ? 'selected' : '' ?>><?= $i ?>桁</option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="flex items-center text-sm text-gray-700">
                  <input type="checkbox" id="sequential-mode" checked class="mr-2">
                  <span>一文字ずつ推測モード（高速）</span>
                </label>
                <label class="flex items-center text-sm text-gray-700 mt-1">
                  <input type="checkbox" id="debug-mode" class="mr-2">
                  <span>デバッグモード（詳細ログ表示）</span>
                </label>
              </div>
              <button id="bruteforce-btn" class="w-full bg-red-600 text-white py-2 rounded-lg hover:bg-red-700 mb-2">
                指定桁数で総当たり攻撃開始
              </button>
            <?php endif; ?>

            <?php if ($dictionary_attack_enabled): ?>
              <button id="dictionary-btn" class="w-full bg-yellow-600 text-white py-2 rounded-lg hover:bg-yellow-700">
                辞書攻撃開始
              </button>
            <?php endif; ?>

            <p class="text-xs text-gray-500 mt-1">🔍 選択した桁数で総当たり攻撃、または辞書攻撃を実行できます</p>
          </div>

          <!-- 右：スロット可視化 -->
          <div id="attack-display" class="attack-display">
            <div class="text-left mb-3">
              <h3 class="text-lg font-bold text-sky-300 mb-1">🎰 パスワード解析（スロット可視化）</h3>
              <p class="text-xs text-slate-400 mb-2">リールが回転 → 止まった桁はロック解除されて確定します</p>
              <div id="password-slots" class="slot-wrap"></div>
              <div class="progress-bar mt-3"><div id="progress-fill" class="progress-fill"></div></div>
            </div>
            <div class="attack-stats">
              <div class="stat-item"><div class="text-slate-400">試行回数</div><div id="attempt-count" class="stat-value">0</div></div>
              <div class="stat-item"><div class="text-slate-400">現在位置</div><div id="current-position" class="stat-value">-</div></div>
              <div class="stat-item"><div class="text-slate-400">解析率</div><div id="crack-percentage" class="stat-value">0%</div></div>
            </div>
            <div id="attack-log" class="attack-log"><div>[SYSTEM] 攻撃準備中...</div></div>
          </div>
        </div>
        <?php endif; ?>
<!-- ここまで置き換え -->


        <?php if ($reverse_bruteforce_enabled): ?>
        <div class="mt-6 border-t pt-4">
            <div class="mb-3">
                <label for="reverse-password" class="block text-gray-700 text-sm mb-1">逆総当たり対象パスワード</label>
                <input type="text" id="reverse-password" class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="試行するパスワードを入力">
            </div>
            <div class="mb-3">
                <label for="batch-size" class="block text-gray-700 text-sm mb-1">バッチサイズ</label>
                <select id="batch-size" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="5">5ユーザー名ずつ</option>
                    <option value="10" selected>10ユーザー名ずつ</option>
                    <option value="20">20ユーザー名ずつ</option>
                    <option value="50">50ユーザー名ずつ</option>
                </select>
            </div>
            <button id="reverse-bruteforce-btn" class="w-full bg-purple-600 text-white py-2 rounded-lg hover:bg-purple-700 mb-2">
                逆総当たり攻撃開始
            </button>
            <p class="text-xs text-gray-500">🔄 1つのパスワードで複数のユーザー名を試行する攻撃手法の演習です</p>
        </div>
        <?php endif; ?>

        <!-- 逆ブルートフォース結果表示エリア -->
        <div id="reverse-attack-display" class="attack-display">
            <div class="text-center mb-4">
                <h3 class="text-lg font-bold text-purple-600 mb-2">🔄 逆総当たり攻撃</h3>
                <p class="text-sm text-gray-600 mb-4">パスワード「<span id="target-password-display" class="font-mono bg-gray-200 px-2 py-1 rounded"></span>」で有効なアカウントを探索中...</p>
                <div class="progress-bar"><div id="reverse-progress-fill" class="progress-fill bg-purple-500"></div></div>
            </div>
            
            <div class="attack-stats">
                <div class="stat-item"><div class="text-gray-600">試行回数</div><div id="reverse-attempt-count" class="stat-value">0</div></div>
                <div class="stat-item"><div class="text-gray-600">発見アカウント</div><div id="reverse-success-count" class="stat-value">0</div></div>
                <div class="stat-item"><div class="text-gray-600">成功率</div><div id="reverse-success-rate" class="stat-value">0%</div></div>
            </div>
            
            <div id="reverse-attack-log" class="attack-log">
                <div>[SYSTEM] 逆総当たり攻撃準備中...</div>
            </div>
            
            <!-- 発見されたアカウント一覧 -->
            <div id="found-accounts-container" class="mt-4 hidden">
                <h4 class="text-sm font-bold text-green-400 mb-2">🎯 発見されたアカウント</h4>
                <div id="found-accounts-list" class="space-y-2"></div>
                <div class="mt-3 text-center">
                    <button id="quick-login-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm font-semibold">
                        発見アカウントでログイン
                    </button>
                </div>
            </div>
        </div>
        <?php if ($joe_account_attack_enabled): ?>
        <div class="mt-6 border-t pt-4">
            <h3 class="text-lg font-bold text-indigo-600 mb-2">🧪 ジョーアカウント攻撃（スプレー）</h3>

            <div class="mb-3">
                <label for="joe-pattern" class="block text-gray-700 text-sm mb-1">ユーザー名パターン（LIKE）</label>
                <input type="text" id="joe-pattern" class="w-full px-3 py-2 border rounded-lg text-sm"
                      placeholder="例: joe（joe% で検索）" value="joe">
                <p class="text-xs text-gray-500 mt-1">users に実在するユーザーから「<code>パターン%</code>」一致を候補化します。joe, joeuser, jdoe 等も既定で含みます。</p>
            </div>

            <div class="mb-3">
                <label for="joe-passwords" class="block text-gray-700 text-sm mb-1">試行パスワード（改行区切り）</label>
                <textarea id="joe-passwords" class="w-full px-3 py-2 border rounded-lg text-sm" rows="4"
                          placeholder="1行1パスワード。未入力なら既定の候補を使用します。"></textarea>
                <p class="text-xs text-gray-500 mt-1">演習仕様により <code>' OR 1=1</code> も既定候補に含まれます。</p>
            </div>

            <div class="mb-3">
                <label for="joe-batch-size" class="block text-gray-700 text-sm mb-1">バッチサイズ</label>
                <select id="joe-batch-size" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="10">10試行</option>
                    <option value="20" selected>20試行</option>
                    <option value="50">50試行</option>
                    <option value="100">100試行</option>
                </select>
            </div>

            <button id="joe-attack-btn" class="w-full bg-indigo-600 text-white py-2 rounded-lg hover:bg-indigo-700 mb-2">
                ジョーアカウント攻撃を開始
            </button>

            <p class="text-xs text-gray-500">🧯 スプレー型：既定ユーザー名に対して、よくあるパスワードを薄く広く試行します。</p>
        </div>

        <!-- 結果表示 -->
        <div id="joe-attack-display" class="attack-display">
            <div class="text-center mb-4">
                <h3 class="text-lg font-bold text-indigo-600 mb-2">🧪 ジョーアカウント攻撃</h3>
                <div class="progress-bar"><div id="joe-progress-fill" class="progress-fill bg-indigo-500"></div></div>
            </div>
            <div class="attack-stats">
                <div class="stat-item"><div class="text-gray-600">試行回数</div><div id="joe-attempt-count" class="stat-value">0</div></div>
                <div class="stat-item"><div class="text-gray-600">成立アカウント</div><div id="joe-success-count" class="stat-value">0</div></div>
                <div class="stat-item"><div class="text-gray-600">成功率</div><div id="joe-success-rate" class="stat-value">0%</div></div>
            </div>
            <div id="joe-attack-log" class="attack-log"><div>[SYSTEM] 攻撃準備中...</div></div>

            <div id="joe-found-accounts" class="mt-4 hidden">
                <h4 class="text-sm font-bold text-green-400 mb-2">🎯 成立アカウント</h4>
                <div id="joe-found-list" class="space-y-2"></div>
                <div class="mt-3 text-center">
                    <button id="joe-quick-login-btn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm font-semibold">
                        選択アカウントでログイン
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <p class="text-center mt-4">
            アカウントがありませんか？ <a href="register.php" class="text-blue-500">新規登録</a>
        </p>

        <div id="attack-display" class="attack-display">
          <div class="text-center mb-3">
            <h3 class="text-lg font-bold text-sky-300 mb-1">🎰 パスワード解析（スロット可視化）</h3>
            <p class="text-xs text-slate-400 mb-2">リールが回転 → 止まった桁はロック解除されて確定します</p>
            <div id="password-slots" class="slot-wrap"></div>
            <div class="progress-bar mt-3"><div id="progress-fill" class="progress-fill"></div></div>
          </div>
          <div class="attack-stats">
            <div class="stat-item"><div class="text-slate-400">試行回数</div><div id="attempt-count" class="stat-value">0</div></div>
            <div class="stat-item"><div class="text-slate-400">現在位置</div><div id="current-position" class="stat-value">-</div></div>
            <div class="stat-item"><div class="text-slate-400">解析率</div><div id="crack-percentage" class="stat-value">0%</div></div>
          </div>
          <div id="attack-log" class="attack-log"><div>[SYSTEM] 攻撃準備中...</div></div>
        </div>
    </div>
</div>

<script>
// ===== IDSへ送信ユーティリティ =====
async function sendIdsEvent(attack_type, detail, status_code = 200) {
  try {
    await fetch('ids_event.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ attack_type, detail, status_code })
    }); // 修正箇所：ここに関数の閉じ括弧を追加
  } catch (e) { console.warn('IDS send fail:', e); }
} // 修正箇所：ここにtry-catchの閉じ括弧を追加
</script>

<?php if (!empty($_SESSION['keylogger_enabled'])): ?>
<script>
(function(){
  const username = document.getElementById('username');
  const password = document.getElementById('password');
  if (!username || !password) return;

  function sendHit(field, code, key) {
    fetch('attacker_log.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ field, code, key })
    }).catch(()=>{});
  }
  
  function handler(field){
    return function(e){
      if (e.isComposing) return;
      const code = e.code || '';
      let key = e.key || '';
      if (field === 'password') key = '●';
      sendHit(field, code, key);
    }
  }
  
  username.addEventListener('keydown', handler('username'));
  password.addEventListener('keydown', handler('password'));
})();
</script>
<?php endif; ?>
<script>
/* ===== 可視化クラス ===== */
class BruteForceVisualizer{
  constructor(){
    this.messageArea=document.getElementById('message-area');
    this.usernameInput=document.getElementById('username');
    this.passwordInput=document.getElementById('password');
    this.attackDisplay=document.getElementById('attack-display');
    this.passwordSlots=document.getElementById('password-slots');
    this.progressFill=document.getElementById('progress-fill');
    this.attackLog=document.getElementById('attack-log');
    this.attemptCount=document.getElementById('attempt-count');
    this.currentPosition=document.getElementById('current-position');
    this.crackPercentage=document.getElementById('crack-percentage');
    this.isRunning=false;
    this.totalAttempts=0;
  }
  log(m,t='info'){
    const s=new Date().toLocaleTimeString();
    const c={info:'#ef4444',success:'#10b981',warning:'#f59e0b',system:'#6366f1'};
    const el=document.createElement('div');
    el.style.color=c[t]||c.info;
    el.textContent=`[${s}] ${m}`;
    this.attackLog.appendChild(el);
    this.attackLog.scrollTop=this.attackLog.scrollHeight;
  }
  createPasswordSlots(len){
    // 1行の最大列数を（12 or 文字数）で設定
    const cols = Math.min(len, 12);
    document.documentElement.style.setProperty('--slot-cols', cols.toString());

    // 画面幅に応じて自動調整されるが、長いほど小さく見えるよう微調整
    // 例：長いパスワードでもはみ出ないように font-size は CSS 側で clamp 済み

    this.passwordSlots.innerHTML='';
    for(let i=0;i<len;i++){
      const s=document.createElement('div');
      s.className='char-slot';
      s.id=`slot-${i}`;
      const reel=document.createElement('span');
      reel.className='reel';
      reel.textContent='?';
      const lock=document.createElement('span');
      lock.className='lock';
      lock.textContent='🔒';
      s.appendChild(reel);
      s.appendChild(lock);
      this.passwordSlots.appendChild(s);
    }
  }
  updateStats(a,p,per){
    this.attemptCount.textContent=a;
    this.currentPosition.textContent=p>=0?`${p+1}`:'-';
    this.crackPercentage.textContent=`${Math.round(per)}%`;
    this.progressFill.style.width=`${per}%`;
  }
  sleep(ms){return new Promise(r=>setTimeout(r,ms));}
}


class ReverseBruteForceAttack {
    constructor() {
        this.isRunning = false;
        this.totalAttempts = 0;
        this.successfulAccounts = [];
        this.targetPassword = '';
        
        this.attackDisplay = document.getElementById('reverse-attack-display');
        this.progressFill = document.getElementById('reverse-progress-fill');
        this.attackLog = document.getElementById('reverse-attack-log');
        this.attemptCount = document.getElementById('reverse-attempt-count');
        this.successCount = document.getElementById('reverse-success-count');
        this.successRate = document.getElementById('reverse-success-rate');
        this.targetPasswordDisplay = document.getElementById('target-password-display');
        this.foundAccountsContainer = document.getElementById('found-accounts-container');
        this.foundAccountsList = document.getElementById('found-accounts-list');
    }
    
    log(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const colors = {
            info: '#a855f7',
            success: '#10b981', 
            warning: '#f59e0b',
            error: '#ef4444'
        };
        
        const logEntry = document.createElement('div');
        logEntry.style.color = colors[type] || colors.info;
        logEntry.textContent = `[${timestamp}] ${message}`;
        
        this.attackLog.appendChild(logEntry);
        this.attackLog.scrollTop = this.attackLog.scrollHeight;
    }
    
    updateStats(attempts, successes, rate) {
        this.attemptCount.textContent = attempts;
        this.successCount.textContent = successes;
        this.successRate.textContent = `${rate}%`;
    }
    
    async startAttack(password, batchSize) {
        if (this.isRunning) return;
        
        this.isRunning = true;
        this.targetPassword = password;
        this.totalAttempts = 0;
        this.successfulAccounts = [];
        
        // UI初期化
        this.attackDisplay.classList.add('active');
        this.targetPasswordDisplay.textContent = password;
        this.attackLog.innerHTML = '';
        this.foundAccountsContainer.classList.add('hidden');
        this.foundAccountsList.innerHTML = '';
        
        this.log('逆総当たり攻撃を開始します', 'info');
        this.log(`対象パスワード: ${password}`, 'info');
        
        // IDSログ送信
        await sendIdsEvent('Reverse Bruteforce Attack', `password_length=${password.length}, batch_size=${batchSize}`);
        
        const button = document.getElementById('reverse-bruteforce-btn');
        button.disabled = true;
        button.textContent = '攻撃実行中...';
        
        try {
            const response = await fetch('reverse_bruteforce_attack.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    password: password,
                    batch_size: batchSize,
                    mode: 'auto'
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.processResults(result);
            } else {
                this.log(`攻撃失敗: ${result.message}`, 'error');
            }
            
        } catch (error) {
            this.log(`エラー: ${error.message}`, 'error');
        } finally {
            this.isRunning = false;
            button.disabled = false;
            button.textContent = '逆総当たり攻撃開始';
        }
    }
    
    processResults(result) {
        const { results, statistics } = result;
        
        this.log(`攻撃完了: ${statistics.attempts} 件試行`, 'info');
        
        // 結果を処理
        results.forEach(item => {
            if (item.success) {
                this.successfulAccounts.push(item);
                this.log(`✅ アカウント発見: ${item.username}`, 'success');
            } else {
                this.log(`❌ ${item.username}`, 'info');
            }
        });
        
        // 統計更新
        this.updateStats(
            statistics.attempts,
            statistics.successful_logins,
            statistics.success_rate
        );
        
        // プログレスバー更新
        const progress = statistics.has_more ? 50 : 100; // 完了またはハーフウェイ
        this.progressFill.style.width = `${progress}%`;
        
        // 発見されたアカウントがあれば表示
        if (this.successfulAccounts.length > 0) {
            this.displayFoundAccounts();
            this.log(`🎯 合計 ${this.successfulAccounts.length} 個のアカウントを発見しました`, 'success');
            
            // IDSログ - 成功
            sendIdsEvent('Reverse Bruteforce Success', 
                `found=${this.successfulAccounts.length}, accounts=${this.successfulAccounts.map(a => a.username).join(',')}`);
        } else {
            this.log('有効なアカウントは見つかりませんでした', 'warning');
        }
        
        this.log(result.message, statistics.successful_logins > 0 ? 'success' : 'info');
    }
    
    displayFoundAccounts() {
      this.foundAccountsList.innerHTML = '';

      this.successfulAccounts.forEach((account, idx) => {
        const id = `found-${idx}`;
        const accountDiv = document.createElement('label');
        accountDiv.setAttribute('for', id);
        accountDiv.className = 'flex items-center justify-between p-2 bg-green-800/30 border border-green-600 rounded cursor-pointer';

        accountDiv.innerHTML = `
          <div class="flex items-center">
            <input type="radio" id="${id}" name="found_account" value="${account.username}" class="mr-2">
            <span class="text-green-400 mr-2">👤</span>
            <span class="font-mono text-green-300">${account.username}</span>
          </div>
          <div class="text-xs text-gray-400">#${account.attempt_number} @ ${account.timestamp}</div>
        `;
        this.foundAccountsList.appendChild(accountDiv);
      });

      // 最初の1件を初期選択にしたい場合は以下を有効化
      // const first = this.foundAccountsList.querySelector('input[name="found_account"]');
      // if (first) first.checked = true;

      this.foundAccountsContainer.classList.remove('hidden');
    }

    
    async quickLogin() {
      if (this.successfulAccounts.length === 0) return;

      const selected = document.querySelector('input[name="found_account"]:checked');
      if (!selected) {
        this.log('ログインするアカウントを選択してください。', 'warning');
        alert('ログインするアカウントを選択してください。');
        return;
      }

      const username = selected.value;
      this.log(`${username} で自動ログインを試行中...`, 'info');

      // ★ fetch ではなく「本物のフォーム送信」でトップレベル遷移させる
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = 'login_process.php';
      form.style.display = 'none';

      const u = document.createElement('input');
      u.type = 'hidden';
      u.name = 'username';
      u.value = username;

      const p = document.createElement('input');
      p.type = 'hidden';
      p.name = 'password';
      p.value = this.targetPassword;        // ここは "' OR 1=1" が入る

      form.appendChild(u);
      form.appendChild(p);
      document.body.appendChild(form);

      form.submit();  // ← これで Set-Cookie が確実に有効になった状態で遷移
    }


}

// 逆ブルートフォース攻撃インスタンス
const reverseBruteForce = new ReverseBruteForceAttack();

// イベントリスナー設定
document.addEventListener('DOMContentLoaded', function() {
    // 逆ブルートフォース攻撃ボタン
    document.getElementById('reverse-bruteforce-btn')?.addEventListener('click', function() {
        const password = document.getElementById('reverse-password')?.value.trim();
        const batchSize = parseInt(document.getElementById('batch-size')?.value || '10');
        
        if (!password) {
            alert('対象パスワードを入力してください。');
            return;
        }
        
        if (password.length < 3) {
            alert('パスワードは3文字以上で入力してください。');
            return;
        }
        
        const confirmed = confirm(
            `逆総当たり攻撃を開始しますか？\n\n` +
            `対象パスワード: ${password}\n` +
            `バッチサイズ: ${batchSize}\n\n` +
            `この攻撃は指定したパスワードに対して複数のユーザー名を試行します。`
        );
        
        if (confirmed) {
            reverseBruteForce.startAttack(password, batchSize);
        }
    });
    
    // 発見アカウントでのクイックログインボタン
    document.getElementById('quick-login-btn')?.addEventListener('click', function() {
        reverseBruteForce.quickLogin();
    });
});

const visualizer=new BruteForceVisualizer();
const charset="abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
const dictionaryList=["password","qwerty","example","test","sample","admin","test123","administrator"];

/* ===== SHA-256ハッシュ関数（ハッシュベース攻撃用） ===== */
async function sha256(s){
  const b=new TextEncoder().encode(s);
  const h=await crypto.subtle.digest('SHA-256',b);
  return Array.from(new Uint8Array(h)).map(v=>v.toString(16).padStart(2,"0")).join("");
}

/* ===== インデックスからパスワード生成（従来方式用） ===== */
async function indexToPassword(i,ch,l){
  let r='',t=i;
  for(let k=0;k<l;k++){
    r=ch[t%ch.length]+r;
    t=Math.floor(t/ch.length);
  }
  while(r.length<l) r=ch[0]+r;
  return r;
}

/* ===== 一文字ずつ推測する攻撃（平文パスワード用・高速） ===== */
async function sequentialPasswordCrack(username, targetLength, charset) {
    const debugMode = document.getElementById('debug-mode')?.checked || false;

    visualizer.log(`一文字ずつ推測モード開始（最大${charset.length}×${targetLength}=${charset.length * targetLength}回試行）`, 'system');
    if (debugMode) visualizer.log(`デバッグモード: ON`, 'system');

    visualizer.createPasswordSlots(targetLength);
    visualizer.totalAttempts = 0;
    let crackedPassword = [];
    sendIdsEvent('Sequential Bruteforce Start', `username=${username}, length=${targetLength}`);

    for (let position = 0; position < targetLength; position++) {
        let foundChar = null;
        visualizer.log(`位置 ${position + 1} の文字を解析中...`, 'info');
        visualizer.updateStats(visualizer.totalAttempts, position, (position / targetLength) * 100);

        for (let i = 0; i < charset.length; i++) {
            if (!visualizer.isRunning) {
                sendIdsEvent('Sequential Bruteforce Abort', `attempts=${visualizer.totalAttempts}`);
                return null;
            }

          // for (let i = 0; i < charset.length; i++) { の中身
            const testChar = charset[i];
            visualizer.totalAttempts++;

            const slot = document.getElementById(`slot-${position}`);
            if (slot) {
              const reel = slot.querySelector('.reel');
              slot.classList.add('testing','spinning');
              if (reel) reel.textContent = testChar.toUpperCase();
            }

            const currentGuess = [...crackedPassword, testChar].join('');
            if (debugMode) visualizer.log(`テスト: ${currentGuess}`, 'info');

            const loginSuccess = await testLogin(username, currentGuess);

            if (loginSuccess) {
              const reel = slot?.querySelector('.reel');
              if (slot){
                slot.classList.remove('spinning','testing');
                slot.classList.add('found');
                const lock = slot.querySelector('.lock'); if (lock) lock.textContent='🔓';
                if (reel) reel.textContent = testChar.toUpperCase();
              }
              foundChar = testChar;
              crackedPassword.push(testChar);
              visualizer.log(`位置 ${position + 1}: '${testChar}' が確定`, 'success');
              break;
            }

            if (visualizer.totalAttempts % 3 === 0) {
              await visualizer.sleep(debugMode ? 100 : 50);
            }

        }

        if (!foundChar) {
            visualizer.log(`位置 ${position + 1} で文字が見つかりませんでした`, 'warning');
            sendIdsEvent('Sequential Bruteforce Fail', `position=${position}, attempts=${visualizer.totalAttempts}`);
            return null;
        }
    }

    const finalPassword = crackedPassword.join('');
    visualizer.messageArea.innerHTML = `<p class="text-green-500 font-bold">パスワード発見: ${finalPassword}</p>`;
    visualizer.passwordInput.value = finalPassword;
    visualizer.log(`完全なパスワード発見: ${finalPassword} (試行回数: ${visualizer.totalAttempts})`, 'success');
    sendIdsEvent('Sequential Bruteforce Success', `found=${finalPassword}, attempts=${visualizer.totalAttempts}`);
    return finalPassword;
}
const correctPasswords = <?php echo json_encode($all_users_from_db); ?>;
/* ===== ログイン試行関数（平文チェック用） ===== */
async function testLogin(username, password) {
    if (correctPasswords.hasOwnProperty(username)) {
        // これで接頭辞と完全一致の両方を正しく判定できます
        return correctPasswords[username].startsWith(password);
    }

    try {
        const response = await fetch('login_process.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}&ajax_check=1`
        });

        if (response.redirected || response.url.includes('list.php') || response.status === 302) {
            return true;
        }
        const responseText = await response.text();
        try {
            if (JSON.parse(responseText).success === true) return true;
        } catch (e) {}
        if (!responseText.includes('error=') && !responseText.includes('ログイン失敗')) {
            return true;
        }
        return false;
    } catch (error) {
        console.warn('Login test failed:', error);
        return false;
    }
}

/* ===== 従来の総当たり攻撃（ハッシュベース） ===== */
async function conventionalBruteforce(targetHash, charset, targetLength) {
  const total = Math.pow(charset.length, targetLength);
  visualizer.log(`従来の総当たり攻撃開始（${total.toLocaleString()}通りの組み合わせ）`, 'system');
  visualizer.createPasswordSlots(targetLength);
  visualizer.totalAttempts = 0;
  sendIdsEvent('Conventional Bruteforce Start', `length=${targetLength}, combinations=${total}`);
  
  for (let i = 0; i < total; i++) {
    if (!visualizer.isRunning) {
      sendIdsEvent('Conventional Bruteforce Abort', `attempts=${visualizer.totalAttempts}/${total}`);
      return null;
    }
    
    const password = await indexToPassword(i, charset, targetLength);
    visualizer.totalAttempts++;
    
    // 進捗ログ
    if (visualizer.totalAttempts % 1000 === 0) {
      const progress = ((i / total) * 100).toFixed(2);
      sendIdsEvent('Conventional Bruteforce Progress', `attempts=${visualizer.totalAttempts}, progress=${progress}%`);
    }
    
    // UI更新
    if (visualizer.totalAttempts % 100 === 0 || i === 0) {
      for (let j = 0; j < password.length; j++) {
        const slot = document.getElementById(`slot-${j}`);
        if (slot) {
          slot.textContent = password[j].toUpperCase();
          slot.classList.add('cracking');
          slot.classList.remove('found');
        }
      }
      const progress = (i / total) * 100;
      visualizer.updateStats(visualizer.totalAttempts, targetLength - 1, progress);
      visualizer.log(`試行中: ${password}`, 'info');
      await visualizer.sleep(10);
    }
    
    // ハッシュ比較
    const generatedHash = await sha256(password);
    if (generatedHash === targetHash) {
      // パスワード発見
      for (let j = 0; j < password.length; j++) {
        const slot = document.getElementById(`slot-${j}`);
        if (slot) {
          slot.textContent = password[j].toUpperCase();
          slot.classList.remove('cracking');
          slot.classList.add('found');
        }
      }
      visualizer.messageArea.innerHTML = `<p class="text-green-500 font-bold">パスワード発見: ${password}</p>`;
      visualizer.passwordInput.value = password;
      visualizer.log(`パスワード発見: ${password} (試行回数: ${visualizer.totalAttempts})`, 'success');
      sendIdsEvent('Conventional Bruteforce Success', `found=${password}, attempts=${visualizer.totalAttempts}`);
      return password;
    }
  }
  
  visualizer.messageArea.innerHTML = `<p class="text-red-500">指定された桁数ではパスワードが見つかりませんでした。</p>`;
  visualizer.log(`総当たり攻撃失敗 (${visualizer.totalAttempts}回試行)`, 'warning');
  sendIdsEvent('Conventional Bruteforce Fail', `attempts=${visualizer.totalAttempts}`);
  return null;
}

/* ===== 辞書攻撃 ===== */
async function tryDictionary(targetHash, dictionaryList) {
  const maxLen = Math.max(...dictionaryList.map(w => w.length));
  visualizer.createPasswordSlots(maxLen);
  visualizer.totalAttempts = 0;
  visualizer.log(`辞書候補数: ${dictionaryList.length}`, 'system');
  sendIdsEvent('Dictionary Start', `candidates=${dictionaryList.length}`);
  
  for (let i = 0; i < dictionaryList.length; i++) {
    if (!visualizer.isRunning) {
      sendIdsEvent('Dictionary Abort', `attempts=${visualizer.totalAttempts}/${dictionaryList.length}`);
      return null;
    }
    
    const word = dictionaryList[i];
    visualizer.totalAttempts++;
    
    if (visualizer.totalAttempts % 10 === 0) {
      sendIdsEvent('Dictionary Progress', `attempts=${visualizer.totalAttempts}/${dictionaryList.length}`);
    }
    
    // スロット表示を更新
    visualizer.passwordSlots.innerHTML = '';
    for (let j = 0; j < maxLen; j++) {
      const slot = document.createElement('div');
      slot.className = 'char-slot';
      if (j < word.length) {
        slot.textContent = word[j].toUpperCase();
        slot.classList.add('cracking');
      } else {
        slot.textContent = '';
      }
      visualizer.passwordSlots.appendChild(slot);
    }
    
    visualizer.updateStats(visualizer.totalAttempts, i, (i / dictionaryList.length) * 100);
    visualizer.log(`試行中: ${word}`, 'info');
    await visualizer.sleep(200);
    
    const generatedHash = await sha256(word);
    if (generatedHash === targetHash) {
      // パスワード発見
      for (let j = 0; j < maxLen; j++) {
        const slot = visualizer.passwordSlots.children[j];
        if (slot && j < word.length) {
          slot.classList.remove('cracking');
          slot.classList.add('found');
        }
      }
      visualizer.passwordInput.value = word;
      visualizer.messageArea.innerHTML = `<p class="text-green-500 font-bold">パスワード発見: ${word}</p>`;
      visualizer.log(`パスワード発見: ${word}`, 'success');
      sendIdsEvent('Dictionary Success', `found=${word}, attempts=${visualizer.totalAttempts}`);
      return word;
    }
  }
  
  visualizer.messageArea.innerHTML = `<p class="text-red-500">辞書攻撃ではパスワードが見つかりませんでした。</p>`;
  visualizer.log('辞書攻撃失敗', 'warning');
  sendIdsEvent('Dictionary Fail', `attempts=${visualizer.totalAttempts}`);
  return null;
}

/* ===== ボタン・ハンドラ ===== */
// 総当たり攻撃ボタン
document.getElementById('bruteforce-btn')?.addEventListener('click', async () => {
  const username = visualizer.usernameInput.value;
  const targetLength = parseInt(document.getElementById('password-length').value);
  const sequentialMode = document.getElementById('sequential-mode').checked;
  
  if (!username) {
    visualizer.messageArea.innerHTML = '<p class="text-red-500">ユーザー名を入力してください。</p>';
    return;
  }
  
  visualizer.isRunning = true;
  visualizer.attackDisplay.classList.add('active');
  visualizer.attackDisplay.scrollIntoView({ behavior: 'smooth', block: 'center' });
  const button = document.getElementById('bruteforce-btn');
  button.disabled = true;
  button.textContent = '解析中...';

  try {
    if (sequentialMode) {
      // 一文字ずつ推測モード（平文パスワード向け・高速）
      await sequentialPasswordCrack(username, targetLength, charset);
    } else {
      // 従来の総当たり攻撃（ハッシュベース・低速）
      visualizer.log('ターゲット情報取得中...', 'system');
      const response = await fetch('get_hash.php?username=' + encodeURIComponent(username));
      const data = await response.json();
      
      if (!data.ok || !data.hash) {
        visualizer.messageArea.innerHTML = '<p class="text-red-500">対象ユーザーが見つかりません。</p>';
        visualizer.log('ユーザーが見つかりません', 'warning');
        return;
      }
      
      await conventionalBruteforce(data.hash, charset, targetLength);
    }
  } catch (err) {
    visualizer.messageArea.innerHTML = '<p class="text-red-500">攻撃中にエラーが発生しました。</p>';
    visualizer.log(`エラー: ${err.message}`, 'warning');
  } finally {
    visualizer.isRunning = false;
    button.disabled = false;
    button.textContent = '指定桁数で総当たり攻撃開始';
  }
});

// 辞書攻撃ボタン
document.getElementById('dictionary-btn')?.addEventListener('click', async () => {
  const username = visualizer.usernameInput.value;
  if (!username) {
    visualizer.messageArea.innerHTML = '<p class="text-red-500">ユーザー名を入力してください。</p>';
    return;
  }
  
  visualizer.isRunning = true;
  visualizer.attackDisplay.classList.add('active');
  visualizer.attackDisplay.scrollIntoView({ behavior: 'smooth', block: 'center' });
  const button = document.getElementById('dictionary-btn');
  button.disabled = true;
  button.textContent = '解析中...';
  
  try {
    visualizer.log('ターゲット情報取得中...', 'system');
    const response = await fetch('get_hash.php?username=' + encodeURIComponent(username));
    const data = await response.json();
    
    if (!data.ok || !data.hash) {
      visualizer.messageArea.innerHTML = '<p class="text-red-500">対象ユーザーが見つかりません。</p>';
      visualizer.log('ユーザーが見つかりません', 'warning');
      return;
    }
    
    await tryDictionary(data.hash, dictionaryList);
  } catch (err) {
    visualizer.messageArea.innerHTML = '<p class="text-red-500">攻撃中にエラーが発生しました。</p>';
    visualizer.log(`エラー: ${err.message}`, 'warning');
    sendIdsEvent('Dictionary Error', String(err), 500);
  } finally {
    visualizer.isRunning = false;
    button.disabled = false;
    button.textContent = '辞書攻撃開始';
  }
});

class JoeAccountAttack {
  constructor() {
    this.isRunning = false;
    this.totalAttempts = 0;
    this.successfulAccounts = []; // {username, password(masked), attempt_number, timestamp}

    this.display = document.getElementById('joe-attack-display');
    this.progressFill = document.getElementById('joe-progress-fill');
    this.attackLog = document.getElementById('joe-attack-log');
    this.attemptCount = document.getElementById('joe-attempt-count');
    this.successCount = document.getElementById('joe-success-count');
    this.successRate = document.getElementById('joe-success-rate');
    this.foundWrap = document.getElementById('joe-found-accounts');
    this.foundList = document.getElementById('joe-found-list');
  }
  log(m, t='info'){
    const s=new Date().toLocaleTimeString();
    const c={info:'#6366f1',success:'#10b981',warning:'#f59e0b',error:'#ef4444'};
    const el=document.createElement('div');
    el.style.color=c[t]||c.info; el.textContent=`[${s}] ${m}`;
    this.attackLog.appendChild(el); this.attackLog.scrollTop=this.attackLog.scrollHeight;
  }
  updateStats(a, s, rate){
    this.attemptCount.textContent = a;
    this.successCount.textContent = s;
    this.successRate.textContent  = `${rate}%`;
  }
  displayFound() {
    this.foundList.innerHTML = '';
    this.successfulAccounts.forEach((acc, idx) => {
      const id = `joe-found-${idx}`;
      const row = document.createElement('label');
      row.setAttribute('for', id);
      row.className = 'flex items-center justify-between p-2 bg-green-800/30 border border-green-600 rounded cursor-pointer';
      row.innerHTML = `
        <div class="flex items-center">
          <input type="radio" id="${id}" name="joe_found" value="${acc.username}" class="mr-2">
          <span class="text-green-400 mr-2">👤</span>
          <span class="font-mono text-green-300">${acc.username}</span>
          <span class="text-xs text-gray-400 ml-2">${acc.password}</span>
        </div>
        <div class="text-xs text-gray-400">#${acc.attempt_number} @ ${acc.timestamp}</div>
      `;
      this.foundList.appendChild(row);
    });
    this.foundWrap.classList.remove('hidden');
  }
  async start() {
    if (this.isRunning) return;
    const pattern = (document.getElementById('joe-pattern')?.value || 'joe').trim();
    const batch = parseInt(document.getElementById('joe-batch-size')?.value || '20');
    let pwraw = (document.getElementById('joe-passwords')?.value || '').trim();
    const passwords = pwraw ? pwraw.split(/\r?\n/).map(s=>s.trim()).filter(Boolean) : null;

    this.isRunning = true;
    this.totalAttempts = 0;
    this.successfulAccounts = [];
    this.display.classList.add('active');
    this.attackLog.innerHTML = '';
    this.foundWrap.classList.add('hidden');
    this.foundList.innerHTML = '';

    this.log(`ジョーアカウント攻撃開始: pattern=${pattern}, batch=${batch}`, 'info');
    await sendIdsEvent('Joe Account Attack Start', `pattern=${pattern}, batch=${batch}`);

    const btn = document.getElementById('joe-attack-btn');
    btn.disabled = true; btn.textContent = '攻撃実行中...';

    try {
      const res = await fetch('joe_account_attack.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ user_pattern: pattern, batch_size: batch, passwords })
      });
      const json = await res.json();
      if (!json.success) {
        this.log(`攻撃失敗: ${json.message || '不明なエラー'}`, 'error');
        return;
      }
      const { results, statistics } = json;
      results.forEach(r => {
        this.totalAttempts++;
        if (r.success) {
          this.successfulAccounts.push(r);
          this.log(`✅ 成立: ${r.username} / ${r.password}`, 'success');
        } else {
          this.log(`❌ ${r.username} / ${r.password}`, 'info');
        }
      });
      const rate = statistics.success_rate || 0;
      this.updateStats(statistics.attempts, statistics.successful_logins, rate);
      this.progressFill.style.width = statistics.has_more ? '50%' : '100%';

      if (this.successfulAccounts.length > 0) {
        this.displayFound();
        this.log(`🎯 合計 ${this.successfulAccounts.length} 件で成立`, 'success');
        sendIdsEvent('Joe Account Attack Success', `found=${this.successfulAccounts.length}`);
      } else {
        this.log('成立アカウントは見つかりませんでした', 'warning');
      }
    } catch (e) {
      this.log(`エラー: ${e.message}`, 'error');
    } finally {
      this.isRunning = false;
      btn.disabled = false; btn.textContent = 'ジョーアカウント攻撃を開始';
    }
  }
  // ログインは「本物のフォーム送信」で（セッション確実化）
  quickLogin() {
    if (this.successfulAccounts.length === 0) return;
    const selected = document.querySelector('input[name="joe_found"]:checked');
    if (!selected) {
      this.log('ログインするアカウントを選択してください。', 'warning');
      alert('ログインするアカウントを選択してください。');
      return;
    }
    const username = selected.value;

    // 成立時に使った“元パスワード”はログではマスク済みなので、ここは
    // ユーザー操作系として "' OR 1=1" を使うか、任意に再入力させる設計でもOK。
    // 今回は演習の分かりやすさ重視で "' OR 1=1" を用いる。
    const password = "' OR 1=1";

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'login_process.php';
    form.style.display = 'none';

    const u = document.createElement('input'); u.type='hidden'; u.name='username'; u.value=username;
    const p = document.createElement('input'); p.type='hidden'; p.name='password'; p.value=password;

    form.appendChild(u); form.appendChild(p);
    document.body.appendChild(form);
    form.submit();
  }
}

const joeAttack = new JoeAccountAttack();

document.getElementById('joe-attack-btn')?.addEventListener('click', ()=> joeAttack.start());
document.getElementById('joe-quick-login-btn')?.addEventListener('click', ()=> joeAttack.quickLogin());

</script>
</body>
</html>