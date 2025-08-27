<?php
require_once __DIR__ . '/common_init.php';
require 'db.php';

// ランサムウェア演習が有効でない場合は利用不可
if (empty($_SESSION['ransomware_enabled'])) {
    header('Location: list.php?error=' . urlencode('ランサムウェア演習は現在無効です。'));
    exit;
}

// 管理者のみアクセス可能
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit;
}

// ランサムウェア攻撃のシミュレーション処理
$simulation_executed = false;
$attack_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attack_type = $_POST['attack_type'] ?? '';
    
    // ランサムウェア関連のペイロードをWAFで検知させる
    $ransomware_payloads = [
        'encrypt_all' => '.locky encryption started',
        'file_scan' => 'scanning for *.doc *.pdf *.jpg files',
        'crypto_demand' => 'send 0.5 bitcoin to unlock your files',
        'system_lockdown' => 'all files encrypted with RSA-2048',
        'network_spread' => 'spreading to network shares via SMB'
    ];
    
    if (array_key_exists($attack_type, $ransomware_payloads)) {
        $payload = $ransomware_payloads[$attack_type];
        $simulation_executed = true;
        
        // WAFの検知をトリガーするため、URLパラメータとして送信
        $trigger_url = $_SERVER['REQUEST_URI'] . '?malware_payload=' . urlencode($payload);
        
        // ログ記録（演習として）
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO attack_logs (ip_address, user_id, attack_type, malicious_input, request_uri, user_agent, status_code, source_type)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            
            $ip_address = $_SESSION['simulated_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'localhost');
            $user_agent = $_SESSION['simulated_user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Ransomware-Exercise');
            $source_type = $_SESSION['simulated_type'] ?? 'Exercise';
            
            $stmt->execute([
                $ip_address,
                $_SESSION['user_id'] ?? null,
                'Ransomware Exercise: ' . ucfirst(str_replace('_', ' ', $attack_type)),
                'Payload: ' . $payload,
                $_SERVER['REQUEST_URI'] ?? '',
                $user_agent,
                200,
                $source_type
            ]);
        } catch (PDOException $e) {
            error_log("Failed to log ransomware exercise: " . $e->getMessage());
        }
        
        // WAFトリガーのためリダイレクト（GET パラメータでペイロードを送信）
        header('Location: ' . $trigger_url . '&executed=1');
        exit;
    }
}

// GET パラメータからの実行結果確認
if (isset($_GET['executed']) && $_GET['executed'] === '1') {
    $simulation_executed = true;
    $malware_payload = $_GET['malware_payload'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ランサムウェア演習 - セキュリティ教育</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.3; }
        }
        .blink-animation {
            animation: blink 2s infinite;
        }
        .malware-warning {
            background: linear-gradient(45deg, #dc2626, #b91c1c);
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body class="bg-gray-900 text-white">
<?php include 'header.php'; ?>

<div class="container mx-auto mt-10 p-4">
    <!-- 警告ヘッダー -->
    <div class="malware-warning p-4 rounded-lg mb-8 text-center">
        <h1 class="text-3xl font-bold mb-2">⚠️ ランサムウェア演習環境 ⚠️</h1>
        <p class="text-lg">これは教育目的のシミュレーションです。実際のマルウェアではありません。</p>
    </div>

    <?php if ($simulation_executed): ?>
        <!-- 実行結果表示 -->
        <div class="bg-red-900 border border-red-700 p-6 rounded-lg mb-8">
            <div class="flex items-center mb-4">
                <div class="text-red-400 mr-3 text-2xl blink-animation">🔒</div>
                <h2 class="text-xl font-bold text-red-300">ランサムウェア攻撃シミュレーション実行完了</h2>
            </div>
            <?php if (isset($malware_payload)): ?>
                <p class="text-sm text-gray-300 mb-4">検知されたペイロード: <code class="bg-gray-800 p-1 rounded"><?= htmlspecialchars($malware_payload) ?></code></p>
            <?php endif; ?>
            <div class="bg-black p-4 rounded font-mono text-green-400 text-sm">
                <p>[SIMULATION] Ransomware attack pattern executed</p>
                <p>[WARNING] Malicious payload detected by IDS/WAF system</p>
                <p>[STATUS] Exercise completed successfully</p>
                <p class="blink-animation">[ALERT] Check IDS dashboard for detection logs</p>
            </div>
            <div class="mt-4">
                <a href="ids_dashboard.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">IDSダッシュボードで確認</a>
                <a href="ransomware_exercise.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 ml-2">別の演習を実行</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- 攻撃シミュレーションメニュー -->
    <div class="bg-gray-800 p-8 rounded-lg shadow-lg">
        <h2 class="text-2xl font-bold mb-6 text-center text-red-400">ランサムウェア攻撃パターン演習</h2>
        <p class="text-gray-300 mb-8 text-center">以下のボタンをクリックして、ランサムウェアの典型的な攻撃パターンをシミュレートします。<br>
        各攻撃はWAF/IDSシステムによって検知され、ログに記録されます。</p>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- ファイル暗号化 -->
            <div class="bg-red-900 p-6 rounded-lg border border-red-700">
                <h3 class="text-lg font-bold mb-3 text-red-300">🔐 ファイル暗号化開始</h3>
                <p class="text-sm text-gray-300 mb-4">典型的なランサムウェアの暗号化プロセスをシミュレート</p>
                <form action="" method="POST">
                    <input type="hidden" name="attack_type" value="encrypt_all">
                    <button type="submit" class="w-full bg-red-600 text-white py-2 rounded hover:bg-red-700 font-semibold">
                        暗号化攻撃を実行
                    </button>
                </form>
            </div>

            <!-- ファイルスキャン -->
            <div class="bg-orange-900 p-6 rounded-lg border border-orange-700">
                <h3 class="text-lg font-bold mb-3 text-orange-300">📁 ファイルスキャン</h3>
                <p class="text-sm text-gray-300 mb-4">重要ファイルの検索・特定プロセス</p>
                <form action="" method="POST">
                    <input type="hidden" name="attack_type" value="file_scan">
                    <button type="submit" class="w-full bg-orange-600 text-white py-2 rounded hover:bg-orange-700 font-semibold">
                        ファイルスキャン実行
                    </button>
                </form>
            </div>

            <!-- 身代金要求 -->
            <div class="bg-yellow-900 p-6 rounded-lg border border-yellow-700">
                <h3 class="text-lg font-bold mb-3 text-yellow-300">💰 身代金要求</h3>
                <p class="text-sm text-gray-300 mb-4">暗号通貨による身代金要求メッセージ</p>
                <form action="" method="POST">
                    <input type="hidden" name="attack_type" value="crypto_demand">
                    <button type="submit" class="w-full bg-yellow-600 text-white py-2 rounded hover:bg-yellow-700 font-semibold">
                        身代金要求を送信
                    </button>
                </form>
            </div>

            <!-- システム制御 -->
            <div class="bg-purple-900 p-6 rounded-lg border border-purple-700">
                <h3 class="text-lg font-bold mb-3 text-purple-300">🔒 システムロックダウン</h3>
                <p class="text-sm text-gray-300 mb-4">システム全体の制御と暗号化完了通知</p>
                <form action="" method="POST">
                    <input type="hidden" name="attack_type" value="system_lockdown">
                    <button type="submit" class="w-full bg-purple-600 text-white py-2 rounded hover:bg-purple-700 font-semibold">
                        システム制御実行
                    </button>
                </form>
            </div>

            <!-- ネットワーク拡散 -->
            <div class="bg-indigo-900 p-6 rounded-lg border border-indigo-700">
                <h3 class="text-lg font-bold mb-3 text-indigo-300">🌐 ネットワーク拡散</h3>
                <p class="text-sm text-gray-300 mb-4">他のシステムへの横展開・拡散攻撃</p>
                <form action="" method="POST">
                    <input type="hidden" name="attack_type" value="network_spread">
                    <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700 font-semibold">
                        ネットワーク拡散実行
                    </button>
                </form>
            </div>
        </div>

        <!-- 教育的情報 -->
        <div class="mt-8 bg-gray-700 p-6 rounded-lg">
            <h3 class="text-lg font-bold mb-4 text-blue-400">📚 ランサムウェア対策について</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-gray-300">
                <div>
                    <h4 class="font-semibold text-white mb-2">予防策</h4>
                    <ul class="list-disc list-inside space-y-1">
                        <li>定期的なバックアップの実施</li>
                        <li>セキュリティソフトの導入・更新</li>
                        <li>不審なメール・添付ファイルの回避</li>
                        <li>システムの定期的なアップデート</li>
                        <li>従業員への教育・訓練</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold text-white mb-2">検知・対応</h4>
                    <ul class="list-disc list-inside space-y-1">
                        <li>IDS/IPSによる異常通信の監視</li>
                        <li>ファイル活動の監視</li>
                        <li>ネットワークセグメンテーション</li>
                        <li>インシデント対応計画の策定</li>
                        <li>復旧手順の事前準備</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 警告音效果（ブラウザ対応時のみ）
function playWarningSound() {
    if (typeof(Audio) !== "undefined") {
        try {
            // Web Audio APIを使用した簡単なビープ音
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'square';
            
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.5);
        } catch (e) {
            // 音声再生に失敗しても処理続行
        }
    }
}

// 演習実行時の視覚効果
document.addEventListener('DOMContentLoaded', function() {
    const executeButtons = document.querySelectorAll('button[type="submit"]');
    
    executeButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // 警告確認
            const confirmed = confirm('ランサムウェア攻撃パターンを実行しますか？\n（これは演習用のシミュレーションです）');
            
            if (confirmed) {
                playWarningSound();
                
                // ボタンの状態変更
                this.textContent = '実行中...';
                this.disabled = true;
                this.classList.add('opacity-50');
                
                // 少し遅延してフォーム送信（視覚効果のため）
                setTimeout(() => {
                    this.closest('form').submit();
                }, 500);
                
                e.preventDefault();
            } else {
                e.preventDefault();
            }
        });
    });
});
</script>
</body>
</html>