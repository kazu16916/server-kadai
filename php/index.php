<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>投票テーマ作成</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<?php include 'header.php'; ?>
<div class="container mx-auto mt-10 p-4 max-w-lg">
    <div class="bg-white p-8 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold text-center mb-6">新しい投票を作成</h1>
        <form method="POST" action="create.php">
            <div class="mb-6">
                <label for="title" class="block text-gray-700 font-bold mb-2">投票テーマ:</label>
                <input type="text" id="title" name="title" class="w-full py-2 px-3 border rounded" required>
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">選択肢:</label>
                <!-- 選択肢の入力欄を格納するコンテナ -->
                <div id="choices-container" class="space-y-3">
                    <input type="text" name="choices[]" placeholder="選択肢 1 (必須)" class="w-full py-2 px-3 border rounded" required>
                    <input type="text" name="choices[]" placeholder="選択肢 2 (必須)" class="w-full py-2 px-3 border rounded" required>
                    <input type="text" name="choices[]" placeholder="選択肢 3 (任意)" class="w-full py-2 px-3 border rounded">
                </div>
            </div>

            <!-- 選択肢を追加するボタン -->
            <button type="button" id="add-choice-btn" class="text-blue-500 hover:text-blue-700 font-semibold">
                + 選択肢を追加
            </button>
            
            <button type="submit" class="w-full bg-blue-500 text-white font-bold py-2 px-4 rounded mt-6">
                作成する
            </button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const addChoiceBtn = document.getElementById('add-choice-btn');
        const choicesContainer = document.getElementById('choices-container');
        
        // 現在の選択肢の数を初期化
        let choiceCount = 3;

        addChoiceBtn.addEventListener('click', function() {
            // 選択肢が10個未満の場合のみ追加
            if (choiceCount < 10) {
                choiceCount++;
                
                // 新しいinput要素を作成
                const newChoiceInput = document.createElement('input');
                newChoiceInput.type = 'text';
                newChoiceInput.name = 'choices[]';
                newChoiceInput.placeholder = `選択肢 ${choiceCount} (任意)`;
                newChoiceInput.className = 'w-full py-2 px-3 border rounded';
                
                // コンテナに追加
                choicesContainer.appendChild(newChoiceInput);

                // 10個に達したらボタンを非表示にする
                if (choiceCount >= 10) {
                    addChoiceBtn.style.display = 'none';
                }
            }
        });
    });
</script>

</body>
</html>