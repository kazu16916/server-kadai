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
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ログイン</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .char-slot{font-family:'Courier New',monospace;font-size:1.5rem;width:2.5rem;height:3rem;display:inline-flex;align-items:center;justify-content:center;border:2px solid #dc2626;background:#1f1f1f;color:#ef4444;margin:0 1px;position:relative;overflow:hidden;border-radius:4px;}
        .char-slot.cracking{animation:glow-red .5s infinite alternate;border-color:#fbbf24;color:#fbbf24;}
        .char-slot.found{background:#065f46;border-color:#10b981;color:#6ee7b7;animation:none;}
        .char-slot.testing{background:#1e40af;border-color:#3b82f6;color:#93c5fd;animation:pulse 1s infinite;}
        @keyframes glow-red{from{box-shadow:0 0 5px #dc2626;background:#1f1f1f;}to{box-shadow:0 0 15px #dc2626,0 0 25px #dc2626;background:#2d1b1b;}}
        @keyframes pulse{0%{opacity:1;}50%{opacity:0.5;}100%{opacity:1;}}
        .attack-display{background:#111827;border-radius:8px;padding:16px;margin:16px 0;display:none;}
        .attack-display.active{display:block;}
        .progress-bar{height:6px;background:#374151;border-radius:3px;overflow:hidden;margin:8px 0;}
        .progress-fill{height:100%;background:linear-gradient(90deg,#dc2626,#ef4444);transition:width .3s ease;width:0%;}
        .attack-log{background:#000;color:#ef4444;font-family:'Courier New',monospace;font-size:.875rem;padding:12px;border-radius:4px;max-height:150px;overflow-y:auto;margin-top:8px;}
        .attack-stats{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin:8px 0;font-size:.875rem;}
        .stat-item{background:#374151;padding:8px;border-radius:4px;text-align:center;}
        .stat-value{font-weight:bold;color:#ef4444;}
    </style>
</head>
<body class="bg-gray-100">
<div class="container mx-auto mt-10 p-4 max-w-md">
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

        <?php if ($bruteforce_enabled || $dictionary_attack_enabled): ?>
        <div class="mt-6 border-t pt-4">
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
        <?php endif; ?>

        <p class="text-center mt-4">
            アカウントがありませんか？ <a href="register.php" class="text-blue-500">新規登録</a>
        </p>

        <div id="attack-display" class="attack-display">
            <div class="text-center mb-4">
                <h3 class="text-lg font-bold text-red-600 mb-2">🔓 パスワード解析</h3>
                <div id="password-slots" class="flex justify-center mb-3"></div>
                <div class="progress-bar"><div id="progress-fill" class="progress-fill"></div></div>
                <p class="text-xs text-gray-600 mt-2">進捗状況がここに表示されます</p>
            </div>
            <div class="attack-stats">
                <div class="stat-item"><div class="text-gray-600">試行回数</div><div id="attempt-count" class="stat-value">0</div></div>
                <div class="stat-item"><div class="text-gray-600">現在位置</div><div id="current-position" class="stat-value">-</div></div>
                <div class="stat-item"><div class="text-gray-600">解析率</div><div id="crack-percentage" class="stat-value">0%</div></div>
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
  createPasswordSlots(l){
    this.passwordSlots.innerHTML='';
    for(let i=0;i<l;i++){
      const d=document.createElement('div');
      d.className='char-slot';
      d.id=`slot-${i}`;
      d.textContent='?';
      this.passwordSlots.appendChild(d);
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

            const testChar = charset[i];
            visualizer.totalAttempts++;

            const slot = document.getElementById(`slot-${position}`);
            if (slot) {
                slot.textContent = testChar.toUpperCase();
                slot.classList.remove('found');
                slot.classList.add('testing');
            }

            const currentGuess = [...crackedPassword, testChar].join('');
            if (debugMode) visualizer.log(`テスト: ${currentGuess}`, 'info');

            const loginSuccess = await testLogin(username, currentGuess);

            // 【重要】loginSuccessがtrueになったら、その文字が正解！
            if (loginSuccess) {
                foundChar = testChar;
                crackedPassword.push(testChar);

                if (slot) {
                    slot.classList.remove('testing');
                    slot.classList.add('found');
                }
                visualizer.log(`位置 ${position + 1}: '${testChar}' が正解`, 'success');
                if (debugMode) visualizer.log(`現在の進捗: ${crackedPassword.join('')}${'?'.repeat(targetLength - position - 1)}`, 'system');
                
                // 正しい文字を見つけたら、次の位置の解析に移る
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
</script>
</body>
</html>