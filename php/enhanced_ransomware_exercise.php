<?php
// enhanced_ransomware_exercise.php

// ---- デバッグログ関数（OS非依存の一時ディレクトリを使用） ----
function debug_log($message) {
    $log_file = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ransomware_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}
$DEBUG_LOG_PATH = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ransomware_debug.log';

debug_log("=== Script Start ===");

require_once __DIR__ . '/common_init.php';
require 'db.php';

$last_op = $_GET['done'] ?? '';
if ($last_op === 'encrypt' || $last_op === 'restore') {
    $simulation_executed = true;
    $attack_type = ($last_op === 'encrypt') ? 'encrypt_files' : 'restore_files';
}

debug_log("Session ransomware_enabled: " . var_export($_SESSION['ransomware_enabled'] ?? null, true));
debug_log("User role: " . var_export($_SESSION['role'] ?? null, true));

// ランサムウェア演習が有効でない場合は利用不可
if (empty($_SESSION['ransomware_enabled'])) {
    debug_log("Ransomware exercise not enabled, redirecting");
    header('Location: list.php?error=' . urlencode('ランサムウェア演習は現在無効です。'));
    exit;
}

// 管理者のみアクセス可能
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    debug_log("User not admin, redirecting");
    header('Location: list.php');
    exit;
}

// ファイル暗号化シミュレーション用のディレクトリ
$simulation_dir = __DIR__ . '/simulation_files';
debug_log("Simulation directory: " . $simulation_dir);

if (!is_dir($simulation_dir)) {
    debug_log("Creating simulation directory");
    $created = @mkdir($simulation_dir, 0755, true);
    debug_log("Directory created: " . var_export($created, true));
}

debug_log("Directory exists: " . var_export(is_dir($simulation_dir), true));
debug_log("Directory writable: " . var_export(is_writable($simulation_dir), true));

// 現在のディレクトリ内容を詳細確認
if (is_dir($simulation_dir)) {
    debug_log("=== Current Directory Contents ===");
    $files = @scandir($simulation_dir);
    if (is_array($files)) {
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $filepath = $simulation_dir . '/' . $file;
                $size = @filesize($filepath);
                $mtime = @filemtime($filepath);
                debug_log("File: $file | Size: " . var_export($size, true) . " bytes | Modified: " . ($mtime ? date('Y-m-d H:i:s', $mtime) : 'N/A'));
            }
        }
    }
    debug_log("=== End Directory Contents ===");
}

// サンプルファイルの作成（初回のみ）
$sample_files = [
    'document1.txt'      => 'これは重要な文書です。',
    'photo.jpg'          => 'FAKE_JPEG_DATA_FOR_SIMULATION',
    'spreadsheet.xlsx'   => 'FAKE_EXCEL_DATA_FOR_SIMULATION',
    'presentation.pptx'  => 'FAKE_POWERPOINT_DATA_FOR_SIMULATION'
];

// ディレクトリが完全に空のときだけ全作成、
// そうでなければ「原本も .locked も存在しないファイルのみ」作成
$existing_any = glob($simulation_dir . '/*');
$dir_is_empty = empty($existing_any) || $existing_any === false;

foreach ($sample_files as $filename => $content) {
    $orig   = $simulation_dir . '/' . $filename;
    $locked = $orig . '.locked';

    if ($dir_is_empty) {
        // 初回：全部作る
        if (!file_exists($orig)) {
            debug_log("Creating sample file (initial): " . $filename);
            @file_put_contents($orig, $content);
        }
    } else {
        // 2回目以降：原本も .locked も無いものだけ作る
        if (!file_exists($orig) && !file_exists($locked)) {
            debug_log("Creating missing sample file (no orig/locked): " . $filename);
            @file_put_contents($orig, $content);
        }
    }
}

// ランサムウェア攻撃のシミュレーション処理
$simulation_executed = false;
$attack_type = '';
$encrypted_files = [];
$file_activities = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    debug_log("POST request received");
    debug_log("POST data: " . print_r($_POST, true));

    $attack_type = $_POST['attack_type'] ?? '';
    debug_log("Attack type: " . $attack_type);

    // タイムアウト対策 & セッションロック解放（重い処理の前に）
    set_time_limit(30);
    ignore_user_abort(true);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close(); // 書き込みロックを解放して他リクエストの足止めを避ける
    }

    // ファイル暗号化シミュレーション
    if ($attack_type === 'encrypt_files') {
        debug_log("Starting file encryption simulation");
        $simulation_executed = true;
        $attack_type = ($_GET['done'] === 'encrypt') ? 'encrypt_files'
                : (($_GET['done'] === 'restore') ? 'restore_files' : '');

        try {
            $files = glob($simulation_dir . '/*');
            debug_log("Glob result: " . print_r($files, true));

            if (empty($files)) {
                debug_log("No files found, creating sample files");
                foreach ($sample_files as $filename => $content) {
                    $filepath = $simulation_dir . '/' . $filename;
                    $written = @file_put_contents($filepath, $content);
                    debug_log("Created file $filename: $written bytes");
                }
                $files = glob($simulation_dir . '/*');
                debug_log("Files after creation: " . print_r($files, true));
            }

            foreach ($files as $file) {
                debug_log("Processing file: " . $file);

                if (!is_file($file)) {
                    debug_log("Skipping non-file: " . $file);
                    continue;
                }

                // PHP 7互換: str_ends_with の代替
                $is_locked = (substr($file, -7) === '.locked');
                debug_log("File $file is locked: " . var_export($is_locked, true));

                if (!$is_locked) {
                    debug_log("Encrypting file: " . $file);

                    $original_content = @file_get_contents($file);
                    if ($original_content === false) {
                        debug_log("Failed to read file: " . $file);
                        continue;
                    }
                    debug_log("Read " . strlen($original_content) . " bytes from " . $file);

                    $new_file = $file . '.locked';
                    $encrypted_content = base64_encode($original_content . '_ENCRYPTED_BY_SIMULATION');

                    $written = file_put_contents($new_file, $encrypted_content);
                    debug_log("Encrypted file written: " . var_export($written, true) . " bytes to $new_file");

                    if ($written !== false) {
                        // 書き込み成功後に元ファイル削除
                        if (file_exists($file)) {
                            $deleted = @unlink($file);
                            debug_log("Delete original file $file: " . var_export($deleted, true));
                        }
                        $encrypted_files[] = basename($new_file);
                        $file_activities[] = [
                            'action' => 'ENCRYPT',
                            'file' => basename($file),
                            'new_file' => basename($new_file),
                            'timestamp' => date('H:i:s')
                        ];
                    }
                }
            }

            debug_log("Encrypted files: " . print_r($encrypted_files, true));

            // 身代金メモの作成
            $ransom_note = "🔒 YOUR FILES HAVE BEEN ENCRYPTED! 🔒\n\n";
            $ransom_note .= "All your important files have been encrypted with strong encryption.\n";
            $ransom_note .= "To recover your files, you need to pay 0.5 Bitcoin to:\n";
            $ransom_note .= "1A2B3C4D5E6F7G8H9I0J1K2L3M4N5O6P7Q8R9S\n\n";
            $ransom_note .= "This is a SIMULATION for educational purposes only!\n";
            $ransom_note .= "Your real files are safe.";

            $ransom_written = @file_put_contents($simulation_dir . '/README_DECRYPT.txt', $ransom_note);
            debug_log("Ransom note written: " . var_export($ransom_written, true) . " bytes");

            // ファイル活動ログの記録
            debug_log("Recording file activity log");
            log_file_activity($pdo, 'Mass File Encryption', $file_activities);

            debug_log("Encryption simulation completed successfully");

        } catch (Exception $e) {
            debug_log("Encryption simulation failed: " . $e->getMessage());
            debug_log("Stack trace: " . $e->getTraceAsString());
        }

    } elseif ($attack_type === 'restore_files') {
        debug_log("Starting file restoration");
        $simulation_executed = true;

        try {
            $locked_files = glob($simulation_dir . '/*.locked');
            debug_log("Found locked files: " . print_r($locked_files, true));

            foreach ($locked_files as $locked_file) {
                $original_name = str_replace('.locked', '', $locked_file);
                $encrypted_content = @file_get_contents($locked_file);
                $decoded = base64_decode($encrypted_content, true);
                if ($decoded === false) {
                    debug_log("Failed to base64 decode: " . $locked_file);
                    continue;
                }
                $decrypted_content = str_replace('_ENCRYPTED_BY_SIMULATION', '', $decoded);

                // 復元
                @unlink($locked_file);
                @file_put_contents($original_name, $decrypted_content);

                $file_activities[] = [
                    'action'    => 'RESTORE',
                    'file'      => basename($locked_file),
                    'new_file'  => basename($original_name),
                    'timestamp' => date('H:i:s')
                ];
            }

            // 身代金メモ削除
            $ransom_file = $simulation_dir . '/README_DECRYPT.txt';
            if (file_exists($ransom_file)) {
                @unlink($ransom_file);
            }

            log_file_activity($pdo, 'File Restoration', $file_activities);
            debug_log("File restoration completed");

        } catch (Exception $e) {
            debug_log("File restoration failed: " . $e->getMessage());
        }
    }

    // 従来のペイロード検知も実行
    $ransomware_payloads = [
        'encrypt_files'  => 'mass file encryption initiated',
        'file_scan'      => 'scanning for *.doc *.pdf *.jpg files',
        'crypto_demand'  => 'send 0.5 bitcoin to unlock your files',
        'system_lockdown'=> 'all files encrypted with RSA-2048',
        'network_spread' => 'spreading to network shares via SMB'
    ];

    if (array_key_exists($attack_type, $ransomware_payloads)) {
        $payload = $ransomware_payloads[$attack_type];
        debug_log("Logging payload: " . $payload);

        // ログ記録
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO attack_logs (ip_address, user_id, attack_type, malicious_input, request_uri, user_agent, status_code, source_type)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $ip_address = $_SESSION['simulated_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'localhost');
            $user_agent = $_SESSION['simulated_user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Ransomware-Exercise');
            $source_type = $_SESSION['simulated_type'] ?? 'Exercise';

            $malicious_input = 'Payload: ' . $payload;
            if (!empty($encrypted_files)) {
                $malicious_input .= ' | Encrypted files: ' . implode(', ', $encrypted_files);
            }

            $executed = $stmt->execute([
                $ip_address,
                $_SESSION['user_id'] ?? null,
                'Ransomware Exercise: ' . ucfirst(str_replace('_', ' ', $attack_type)),
                $malicious_input,
                $_SERVER['REQUEST_URI'] ?? '',
                $user_agent,
                200,
                $source_type
            ]);

            debug_log("Log inserted: " . var_export($executed, true));

        } catch (PDOException $e) {
            debug_log("Failed to log ransomware exercise: " . $e->getMessage());
        }
    }
}

// 現在のファイル状況を取得
$current_files = [];
$is_encrypted = false;
$ransom_note_exists = false;

if (is_dir($simulation_dir)) {
    $files = glob($simulation_dir . '/*');
    debug_log("Current files in directory: " . print_r($files, true));

    foreach ($files as $file) {
        if (is_file($file)) {
            $filename = basename($file);
            $current_files[] = $filename;
            // PHP 7互換
            if (substr($filename, -7) === '.locked') {
                $is_encrypted = true;
            }
            if ($filename === 'README_DECRYPT.txt') {
                $ransom_note_exists = true;
            }
        }
    }
}

debug_log("Current files array: " . print_r($current_files, true));
debug_log("Is encrypted: " . var_export($is_encrypted, true));
debug_log("Ransom note exists: " . var_export($ransom_note_exists, true));

/**
 * ファイル活動ログの記録
 */
function log_file_activity($pdo, $activity_type, $activities) {
    debug_log("Logging file activity: " . $activity_type);

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO attack_logs (ip_address, user_id, attack_type, malicious_input, request_uri, user_agent, status_code, source_type)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $ip_address = $_SESSION['simulated_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'localhost');
        $user_agent = $_SESSION['simulated_user_agent'] ?? 'Ransomware-File-Monitor';
        $source_type = $_SESSION['simulated_type'] ?? 'File-System';

        $activity_log = [];
        foreach ($activities as $activity) {
            $activity_log[] = "[{$activity['timestamp']}] {$activity['action']}: {$activity['file']} -> {$activity['new_file']}";
        }

        $executed = $stmt->execute([
            $ip_address,
            $_SESSION['user_id'] ?? null,
            'Ransomware File Activity: ' . $activity_type,
            implode(' | ', $activity_log),
            $_SERVER['REQUEST_URI'] ?? '',
            $user_agent,
            200,
            $source_type
        ]);

        debug_log("File activity logged: " . var_export($executed, true));

    } catch (PDOException $e) {
        debug_log("Failed to log file activity: " . $e->getMessage());
    }
}

debug_log("=== Script End ===");
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ランサムウェア演習 - ファイル暗号化シミュレーション（カスタムログ版）</title>
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
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .slide-in {
            animation: slideIn 0.5s ease-out;
        }
    </style>
</head>
<body class="bg-gray-900 text-white">
<?php include 'header.php'; ?>

<div class="container mx-auto mt-10 p-4">
    <div class="mb-6">
    <div class="flex items-center gap-3 text-sm text-slate-300">
        <span class="opacity-70">演習ナビ</span>
    </div>

    <?php
        // 現在ページを active にする
        $tabs = [
        ['label' => '防御ダッシュボード', 'href' => 'ransomware_defense_dashboard.php', 'key' => 'defense'],
        ['label' => '支払い確認（攻撃者）', 'href' => 'attacker_confirm_payment.php', 'key' => 'attacker'],
        ];
        $current = 'exercise';
    ?>

    <div class="mt-3 overflow-x-auto">
        <ul class="flex flex-wrap gap-2">
        <?php foreach ($tabs as $t): 
            $active = ($t['key'] === $current);
        ?>
            <li>
            <?php if ($active): ?>
                <span class="inline-flex items-center px-4 py-2 rounded-lg bg-slate-800 border border-slate-600 text-white font-semibold shadow-sm">
                <?= htmlspecialchars($t['label']) ?>
                </span>
            <?php else: ?>
                <a href="<?= htmlspecialchars($t['href']) ?>"
                class="inline-flex items-center px-4 py-2 rounded-lg bg-slate-700/60 hover:bg-slate-700 border border-slate-600/70 text-slate-100">
                <?= htmlspecialchars($t['label']) ?>
                </a>
            <?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
    </div>
    <!-- デバッグ情報 -->
    <div class="bg-yellow-900 p-4 rounded-lg mb-4 text-yellow-100 text-sm">
        <strong>デバッグ情報:</strong><br>
        シミュレーションディレクトリ: <?= htmlspecialchars($simulation_dir) ?><br>
        ディレクトリ存在: <?= is_dir($simulation_dir) ? 'はい' : 'いいえ' ?><br>
        ディレクトリ書き込み可能: <?= is_writable($simulation_dir) ? 'はい' : 'いいえ' ?><br>
        現在のファイル数: <?= count($current_files) ?><br>
        暗号化状態: <?= $is_encrypted ? '暗号化済み' : '正常' ?><br>
        身代金メモ: <?= $ransom_note_exists ? '存在' : '存在しない' ?><br>
        最後の操作: <?= $simulation_executed ? htmlspecialchars($attack_type) : 'なし' ?><br>
        <strong>詳細ログ:</strong> <?= htmlspecialchars($DEBUG_LOG_PATH) ?>
    </div>

    <!-- 警告ヘッダー -->
    <div class="malware-warning p-4 rounded-lg mb-8 text-center">
        <h1 class="text-3xl font-bold mb-2">ランサムウェア演習環境（カスタムログ版）</h1>
        <p class="text-lg">これは教育目的のシミュレーションです。実際のマルウェアではありません。</p>
    </div>
    

    <!-- ファイル状況表示 -->
    <div class="bg-gray-800 p-6 rounded-lg mb-8">
        <h2 class="text-xl font-bold mb-4 flex items-center">
            シミュレーションファイル状況
            <?php if ($is_encrypted): ?>
                <span class="ml-3 px-3 py-1 bg-red-600 text-sm rounded-full blink-animation">暗号化済み</span>
            <?php else: ?>
                <span class="ml-3 px-3 py-1 bg-green-600 text-sm rounded-full">正常</span>
            <?php endif; ?>
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <h3 class="font-semibold mb-2">現在のファイル:</h3>
                <div class="bg-black p-3 rounded font-mono text-sm max-h-40 overflow-y-auto">
                    <?php if (empty($current_files)): ?>
                        <p class="text-gray-400">ファイルがありません</p>
                    <?php else: ?>
                        <?php foreach ($current_files as $file): ?>
                            <p class="<?= (substr($file, -7) === '.locked') ? 'text-red-400' : 'text-green-400' ?>">
                                <?= (substr($file, -7) === '.locked') ? '🔒 ' : '📄 ' ?><?= htmlspecialchars($file) ?>
                            </p>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($file_activities) && $simulation_executed): ?>
                <div class="slide-in">
                    <h3 class="font-semibold mb-2">最新のファイル活動:</h3>
                    <div class="bg-black p-3 rounded font-mono text-sm max-h-40 overflow-y-auto">
                        <?php foreach ($file_activities as $activity): ?>
                            <p class="<?= $activity['action'] === 'ENCRYPT' ? 'text-red-400' : 'text-green-400' ?>">
                                [<?= htmlspecialchars($activity['timestamp']) ?>] <?= htmlspecialchars($activity['action']) ?>: <?= htmlspecialchars($activity['file']) ?>
                            </p>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($ransom_note_exists): ?>
            <div class="mt-4 p-4 bg-red-900 border border-red-700 rounded">
                <h3 class="font-bold text-red-300 mb-2">身代金メモが検出されました:</h3>
                <pre class="text-sm text-gray-300 whitespace-pre-wrap"><?= htmlspecialchars(@file_get_contents($simulation_dir . '/README_DECRYPT.txt')) ?></pre>
            </div>
        <?php endif; ?>
    </div>

    <!-- 攻撃・復旧コントロール -->
    <div class="bg-gray-800 p-8 rounded-lg shadow-lg">
        <h2 class="text-2xl font-bold mb-6 text-center text-red-400">ランサムウェア攻撃・復旧シミュレーション</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- ファイル暗号化 -->
            <!-- ファイル暗号化 -->
            <div class="bg-red-900 p-6 rounded-lg border border-red-700">
            <h3 class="text-lg font-bold mb-3 text-red-300">ファイル暗号化攻撃</h3>
            <p class="text-sm text-gray-300 mb-4">実際にファイルを暗号化し、身代金メモを作成します</p>
            <?php if (!$is_encrypted): ?>
                <form action="ransom_action.php" method="POST">
                <!-- op=1: encrypt, op=2: restore  -->
                <input type="hidden" name="op" value="1">
                <button type="submit" class="w-full bg-red-600 text-white py-2 rounded hover:bg-red-700 font-semibold">
                    ファイルを暗号化
                </button>
                </form>
            <?php else: ?>
                <button disabled class="w-full bg-gray-600 text-gray-400 py-2 rounded cursor-not-allowed">
                既に暗号化済み
                </button>
            <?php endif; ?>
            </div>

            <!-- ファイル復旧 -->
            <div class="bg-green-900 p-6 rounded-lg border border-green-700">
            <h3 class="text-lg font-bold mb-3 text-green-300">ファイル復旧</h3>
            <p class="text-sm text-gray-300 mb-4">暗号化されたファイルを復旧します</p>
            <?php if ($is_encrypted): ?>
                <form action="ransom_action.php" method="POST">
                <input type="hidden" name="op" value="2">
                <button type="submit" class="w-full bg-green-600 text-white py-2 rounded hover:bg-green-700 font-semibold">
                    ファイルを復旧
                </button>
                </form>
            <?php else: ?>
                <button disabled class="w-full bg-gray-600 text-gray-400 py-2 rounded cursor-not-allowed">
                復旧不要
                </button>
            <?php endif; ?>
            </div>
        </div>

        <!-- ログ確認ボタン -->
        <div class="mb-6 text-center">
            <button onclick="showLogContent()" class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700">
                デバッグログを表示
            </button>
        </div>
    </div>
</div>

<script>
// ログ内容表示はそのまま
function showLogContent() {
  fetch('show_debug_log.php')
    .then(r => r.text())
    .then(t => alert('デバッグログ:\n\n' + t))
    .catch(err => {
      console.error(err);
      alert('ログファイルの読み取りに失敗しました');
    });
}

/*
  重要：デフォルト送信に依存せず、ボタンクリックで
  e.preventDefault() → confirm → form.submit() を呼ぶ。
  こうすると拡張機能/ブラウザ差異/二重バインドの影響を受けにくい。
*/
document.addEventListener('DOMContentLoaded', function () {
  // このページ内の「攻撃/復旧」のフォームにだけ限定
  document.querySelectorAll('form[method="POST"][action]').forEach(function (form) {
    const btn = form.querySelector('button[type="submit"]');
    if (!btn) return;

    btn.addEventListener('click', function (e) {
      e.preventDefault(); // 既定送信は止める

      const attackInput = form.querySelector('input[name="attack_type"]');
      const attackType = attackInput ? attackInput.value : '';

      let confirmMessage = 'この操作を実行しますか？';
      if (attackType === 'encrypt_files') {
        confirmMessage = 'ファイル暗号化攻撃を実行しますか？\n（シミュレーションファイルが暗号化されます）';
      } else if (attackType === 'restore_files') {
        confirmMessage = '暗号化されたファイルを復旧しますか？';
      }

      if (!confirm(confirmMessage)) return;

      // UI更新 → 明示的に送信
      btn.textContent = '実行中...';
      btn.disabled = true;
      btn.classList.add('opacity-50');

      // 明示的送信（ブラウザ差異の影響を受けない）
      form.submit();
    }, { passive: false });
  });
});
</script>

</body>
</html>
