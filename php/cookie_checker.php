<?php
// Cookieのオプションを定義
$cookie_options = [
    'expires' => time() + (86400 * 365), // 1年間
    'path' => '/',
    'samesite' => 'Lax'
];

// 既に "test_cookie" が存在するかチェック
if (isset($_COOKIE['test_cookie'])) {
    $message = "Cookieが既に存在します。";
    $value = $_COOKIE['test_cookie'];
} else {
    // 存在しない場合、新しい値を生成してセット
    $new_value = "persistent_id_" . rand(1000, 9999);
    setcookie('test_cookie', $new_value, $cookie_options);
    $message = "Cookieを新しくセットしました。ページを再読み込みしてください。";
    $value = $new_value . " (セットされたばかり)";
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Cookie 永続化テスト</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="text-center bg-white p-8 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold mb-4">Cookie 永続化テスト</h1>
        <p class="text-lg mb-2"><?= htmlspecialchars($message) ?></p>
        <p class="text-lg font-mono bg-gray-200 p-2 rounded">
            現在のCookieの値: <strong><?= htmlspecialchars($value) ?></strong>
        </p>
        <p class="mt-6 text-gray-600">
            このページを一度閉じて、再度アクセスしても同じIDが表示されれば、Cookieは正しく保存されています。
        </p>
    </div>
</body>
</html>
