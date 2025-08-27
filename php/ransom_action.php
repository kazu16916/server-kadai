<?php
// ransom_action.php (修正版)

function rl_debug($msg) {
    $log = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ransomware_debug.log';
    @file_put_contents($log, '['.date('Y-m-d H:i:s')."] [ransom_action] $msg\n", FILE_APPEND | LOCK_EX);
}

require_once __DIR__ . '/common_init.php';
require 'db.php';

// 権限・フラグチェック
if (empty($_SESSION['ransomware_enabled'])) {
    rl_debug('ransomware_enabled = false -> redirect');
    header('Location: enhanced_ransomware_exercise.php?error=disabled');
    exit;
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    rl_debug('role != admin -> redirect');
    header('Location: list.php');
    exit;
}

// POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rl_debug('non-POST -> redirect');
    header('Location: enhanced_ransomware_exercise.php');
    exit;
}

$op = isset($_POST['op']) ? (int)$_POST['op'] : 0;
$simulation_dir = __DIR__ . '/simulation_files';
if (!is_dir($simulation_dir)) {
    @mkdir($simulation_dir, 0755, true);
}

set_time_limit(30);
ignore_user_abort(true);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$file_activities = [];

$log_activity = function($pdo, $activity_type, $activities) {
    // ... (この関数は変更なし) ...
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO attack_logs (ip_address, user_id, attack_type, malicious_input, request_uri, user_agent, status_code, source_type)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $ip_address = $_SESSION['simulated_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'localhost');
        $user_agent = $_SESSION['simulated_user_agent'] ?? 'Ransomware-File-Monitor';
        $source_type = $_SESSION['simulated_type'] ?? 'File-System';
        $lines = [];
        foreach ($activities as $a) {
            $lines[] = "[{$a['timestamp']}] {$a['action']}: {$a['file']} -> {$a['new_file']}";
        }
        $stmt->execute([
            $ip_address,
            $_SESSION['user_id'] ?? null,
            'Ransomware File Activity: ' . $activity_type,
            implode(' | ', $lines),
            $_SERVER['REQUEST_URI'] ?? '',
            $user_agent,
            200,
            $source_type
        ]);
    } catch (Throwable $e) {
        rl_debug('log_activity error: '.$e->getMessage());
    }
};

try {
    if ($op === 1) {
        // --- 暗号化処理 ---
        // --- 暗号化処理 ---
        rl_debug('ENCRYPT start');

        clearstatcache();

        $files = glob($simulation_dir . '/*');
        if (empty($files)) {
            $sample = [
                'document1.txt'     => 'これは重要な文書です。',
                'photo.jpg'         => 'FAKE_JPEG_DATA_FOR_SIMULATION',
                'spreadsheet.xlsx'  => 'FAKE_EXCEL_DATA_FOR_SIMULATION',
                'presentation.pptx' => 'FAKE_POWERPOINT_DATA_FOR_SIMULATION',
            ];
            foreach ($sample as $n => $c) {
                @file_put_contents($simulation_dir . "/$n", $c);
            }
            $files = glob($simulation_dir . '/*');
        }

        $encrypted_files_log = [];

        foreach ($files as $file) {
            if (!is_file($file)) continue;

            $base = basename($file);
            if (substr($base, -7) === '.locked') continue;          // 既に暗号化済み
            if ($base === 'README_DECRYPT.txt') continue;            // 身代金メモは暗号化しない

            $content = @file_get_contents($file);
            if ($content === false) { rl_debug("read fail: $file"); continue; }

            $enc = base64_encode($content . '_ENCRYPTED_BY_SIMULATION');
            $locked = $file . '.locked';

            // ① 先に .locked を作る
            $written = @file_put_contents($locked, $enc);
            rl_debug("write locked $locked: " . var_export($written, true));

            if ($written === false) continue;

            // ② 書けたら原本を削除（結果をログ）
            $deleted = @unlink($file);
            rl_debug("unlink original $file: " . var_export($deleted, true));

            $encrypted_files_log[] = basename($locked);
            $file_activities[] = [
                'action'    => 'ENCRYPT',
                'file'      => $base,
                'new_file'  => basename($locked),
                'timestamp' => date('H:i:s')
            ];
        }

        /* --- ここが重要：クリーンアップパス ---
        .locked が存在するのに原本が残っていたら、ここで必ず消す */
        $locked_list = glob($simulation_dir . '/*.locked');
        foreach ($locked_list as $locked) {
            $orig = substr($locked, 0, -7); // ".locked" を除去
            if (is_file($orig)) {
                $d = @unlink($orig);
                rl_debug("cleanup unlink original $orig: " . var_export($d, true));
            }
        }

        /* 身代金メモ + ログは既存のまま */
        $note = "🔒 YOUR FILES HAVE BEEN ENCRYPTED! 🔒\n\n".
                "All your important files have been encrypted with strong encryption.\n".
                "To recover your files, you need to pay 0.5 Bitcoin to:\n".
                "1A2B3C4D5E6F7G8H9I0J1K2L3M4N5O6P7Q8R9S\n\n".
                "This is a SIMULATION for educational purposes only!\n".
                "Your real files are safe.";
        @file_put_contents($simulation_dir.'/README_DECRYPT.txt', $note);

        $log_activity($pdo, 'Mass File Encryption', $file_activities);

        try {
            $stmt = $pdo->prepare(
            "INSERT INTO attack_logs (ip_address, user_id, attack_type, malicious_input, request_uri, user_agent, status_code, source_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $ip_address = $_SESSION['simulated_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'localhost');
            $user_agent = $_SESSION['simulated_user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Ransomware-Exercise');
            $source_type = $_SESSION['simulated_type'] ?? 'Exercise';
            $payload = 'Payload: mass file encryption initiated';
            if (!empty($encrypted_files_log)) $payload .= ' | Encrypted files: ' . implode(', ', $encrypted_files_log);
            $stmt->execute([$ip_address, $_SESSION['user_id'] ?? null, 'Ransomware Exercise: Encrypt Files',
                            $payload, $_SERVER['REQUEST_URI'] ?? '', $user_agent, 200, $source_type]);
        } catch (Throwable $e) {
            rl_debug('insert overview log error: '.$e->getMessage());
        }

        rl_debug('ENCRYPT done');
        header('Location: enhanced_ransomware_exercise.php?done=encrypt');
        exit;

    } elseif ($op === 2) {
        // --- 復旧処理 ---
        rl_debug('RESTORE start');
        $locked_files = glob($simulation_dir . '/*.locked');

        foreach ($locked_files as $locked_file) {
            $original_name = str_replace('.locked', '', $locked_file);
            $encrypted_content = @file_get_contents($locked_file);
            if ($encrypted_content === false) continue;

            $decoded = base64_decode($encrypted_content, true);
            if ($decoded === false) continue;

            $decrypted_content = str_replace('_ENCRYPTED_BY_SIMULATION', '', $decoded);
            
            // 復元ファイルの書き込み
            @file_put_contents($original_name, $decrypted_content);
            
            // 暗号化ファイルの削除
            @unlink($locked_file);

            // ★変更点3: 重複していた file_put_contents の呼び出しを削除
            // @file_put_contents($orig, $plain); // <- この行は不要なため削除

            $file_activities[] = [
                'action'   => 'RESTORE',
                'file'     => basename($locked_file),
                'new_file' => basename($original_name),
                'timestamp' => date('H:i:s')
            ];
        }
        
        // メモ削除 (変更なし)
        $note_file = $simulation_dir . '/README_DECRYPT.txt';
        if (file_exists($note_file)) @unlink($note_file);

        $log_activity($pdo, 'File Restoration', $file_activities);
        rl_debug('RESTORE done');

        header('Location: enhanced_ransomware_exercise.php?done=restore');
        exit;

    } else {
        rl_debug('invalid op -> redirect');
        header('Location: enhanced_ransomware_exercise.php?error=op');
        exit;
    }
} catch (Throwable $e) {
    rl_debug('fatal: '.$e->getMessage());
    header('Location: enhanced_ransomware_exercise.php?error=exception');
    exit;
}