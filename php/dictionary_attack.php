<?php
// dictionary_attack.php
header('Content-Type: application/json');

// 大きな辞書ファイルを逐次読み込みするため
$filename = 'dictionary.txt';
$batchSize = 1000; // 1度に処理する行数

$username = $_POST['username'] ?? '';
$start = isset($_POST['start']) ? (int)$_POST['start'] : 0;

if (!$username) {
    echo json_encode(['success' => false, 'error' => 'ユーザー名が指定されていません']);
    exit;
}

// ハッシュを取得
$userHashFile = __DIR__ . '/hashes/' . $username . '.txt';
if (!file_exists($userHashFile)) {
    echo json_encode(['success' => false, 'error' => '対象ユーザーが存在しません']);
    exit;
}
$targetHash = trim(file_get_contents($userHashFile));

// 辞書ファイルを開く
if (!file_exists($filename)) {
    echo json_encode(['success' => false, 'error' => '辞書ファイルが存在しません']);
    exit;
}

$handle = fopen($filename, 'r');
if (!$handle) {
    echo json_encode(['success' => false, 'error' => '辞書ファイルを開けません']);
    exit;
}

// 指定行までスキップ
for ($i = 0; $i < $start; $i++) {
    if (feof($handle)) break;
    fgets($handle);
}

$found = false;
$currentLine = $start;
while (($line = fgets($handle)) !== false) {
    $password = trim($line);
    $currentLine++;

    if (hash('sha256', $password) === $targetHash) {
        $found = true;
        echo json_encode([
            'success' => true,
            'password' => $password
        ]);
        fclose($handle);
        exit;
    }

    // バッチ処理で途中経過を返す
    if (($currentLine - $start) >= $batchSize) {
        echo json_encode([
            'success' => false,
            'next' => $currentLine,
            'done' => feof($handle)
        ]);
        fclose($handle);
        exit;
    }
}

// ファイル末尾に到達
echo json_encode([
    'success' => false,
    'next' => null,
    'done' => true
]);
fclose($handle);
