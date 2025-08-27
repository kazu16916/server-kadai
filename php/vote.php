<?php
session_start();
require 'db.php';

// ログインしていなければログインページへリダイレクト
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];

// 投票テーマの情報を取得
$poll_token = $_GET['token'];
$poll_stmt = $pdo->prepare("SELECT * FROM polls WHERE token = ?");
$poll_stmt->execute([$poll_token]);
$poll = $poll_stmt->fetch();

if (!$poll) {
    die("テーマが見つかりません。");
}

// このユーザーが既に投票済みかDBで確認
$vote_check_stmt = $pdo->prepare("SELECT id FROM votes WHERE poll_id = ? AND user_id = ?");
$vote_check_stmt->execute([$poll['id'], $user_id]);
$already_voted = $vote_check_stmt->fetch();

// 選択肢の情報を取得
$choices_stmt = $pdo->prepare("SELECT * FROM choices WHERE poll_id = ? ORDER BY id ASC");
$choices_stmt->execute([$poll['id']]);
$choices = $choices_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>投票 - <?= htmlspecialchars($poll['title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<?php include 'header.php'; ?>

<div class="container mx-auto mt-10 p-4 max-w-lg">
    <header class="text-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($poll['title']) ?></h1>
    </header>

    <div class="bg-white p-8 rounded-lg shadow-md">
        <?php if ($already_voted): ?>
            <div class="text-center">
                <p class="text-xl text-gray-700 mb-4">あなたはこの投票に投票済みです。</p>
                <a href="result.php?token=<?= htmlspecialchars($poll_token) ?>" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded transition duration-300">
                    結果を見る
                </a>
            </div>
        <?php else: ?>
            <div id="vote-form-area">
                <div class="space-y-4">
                    <?php foreach ($choices as $c): ?>
                        <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 transition cursor-pointer">
                            <input type="radio" name="choice" value="<?= $c['id'] ?>" class="h-5 w-5 text-blue-600">
                            <span class="ml-3 text-lg text-gray-800"><?= htmlspecialchars($c['text']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="mt-8">
                    <button id="vote-button" class="w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded">
                        投票する
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    const voteButton = document.getElementById('vote-button');
    if (voteButton) {
        const socket = new WebSocket('ws://localhost:8080');
        voteButton.addEventListener('click', () => {
            const selectedChoice = document.querySelector('input[name="choice"]:checked');
            if (selectedChoice) {
                const voteData = {
                    action: 'vote',
                    poll_id: <?= $poll['id'] ?>,
                    choice_id: selectedChoice.value,
                    user_id: <?= $user_id ?> // user_idを送信
                };
                socket.send(JSON.stringify(voteData));

                const formArea = document.getElementById('vote-form-area');
                formArea.innerHTML = `<div class="text-center"><p class="text-xl text-green-600 font-semibold">投票しました！</p><a href="result.php?token=<?= htmlspecialchars($poll_token) ?>" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded inline-block mt-2">結果を見る</a></div>`;
            } else {
                alert('選択肢を選んでください。');
            }
        });
    }
</script>

</body>
</html>
