<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$current_user_id = $_SESSION['user_id'];

$token = $_GET['token'];
$poll_stmt = $pdo->prepare("SELECT * FROM polls WHERE token = ?");
$poll_stmt->execute([$token]);
$poll = $poll_stmt->fetch();

if (!$poll) die("投票が見つかりません。");

$choices_stmt = $pdo->prepare("SELECT * FROM choices WHERE poll_id = ? ORDER BY id ASC");
$choices_stmt->execute([$poll['id']]);
$choices = $choices_stmt->fetchAll(PDO::FETCH_ASSOC);

$comments_stmt = $pdo->prepare("
    SELECT
        c.id, c.poll_id, c.user_id, c.choice_id, c.content, c.created_at,
        u.username,
        COALESCE(cl.like_count, 0) AS likes,
        EXISTS(SELECT 1 FROM comment_likes WHERE comment_id = c.id AND user_id = ?) AS liked_by_user
    FROM comments c
    JOIN users u ON c.user_id = u.id
    LEFT JOIN (
        SELECT comment_id, COUNT(*) AS like_count
        FROM comment_likes
        GROUP BY comment_id
    ) cl ON c.id = cl.comment_id
    WHERE c.poll_id = ?
    ORDER BY likes DESC, c.created_at DESC
");
$comments_stmt->execute([$current_user_id, $poll['id']]);
$all_comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);

$general_comments = [];
$choice_comments = [];
foreach ($choices as $choice) {
    $choice_comments[$choice['id']] = [];
}

foreach ($all_comments as $comment) {
    if ($comment['choice_id'] === null) {
        $general_comments[] = $comment;
    } else {
        if (isset($choice_comments[$comment['choice_id']])) {
            $choice_comments[$comment['choice_id']][] = $comment;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>結果 - <?= htmlspecialchars($poll['title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
<?php include 'header.php'; ?>
<div class="container mx-auto mt-10 p-4 max-w-6xl">
    <!-- 投票結果エリア -->
    <header class="text-center mb-6">
        <p class="text-gray-600">投票結果</p>
        <h1 class="text-3xl font-bold text-gray-800">「<?= htmlspecialchars($poll['title']) ?>」</h1>
    </header>
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
        <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-md flex flex-col items-center justify-center h-full">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">得票率</h2>
            <div class="w-full max-w-xs"><canvas id="voteChart"></canvas></div>
        </div>
        <div class="lg:col-span-3 bg-white p-8 rounded-lg shadow-md">
            <div class="flex justify-between items-baseline mb-6 border-b pb-4">
                <h2 class="text-xl font-semibold">各選択肢の票数とコメント</h2>
                <p class="font-bold">合計: <span id="total-votes" class="text-2xl text-blue-600">0</span> 票</p>
            </div>
            <div id="details-area" class="space-y-6"></div>
        </div>
    </div>

    <!-- 全体コメントエリア -->
    <div class="mt-12 bg-white p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">投票全体へのコメント</h2>
        <div class="mb-8">
            <textarea id="general-comment-input" class="w-full p-3 border rounded-lg" rows="3" placeholder="この投票全体へのコメントを入力..."></textarea>
            <button id="post-general-comment-btn" class="mt-2 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">コメントを投稿</button>
        </div>
        <div>
            <h3 class="text-xl font-semibold text-gray-700 mb-4">人気のコメント Top 5</h3>
            <div id="top-comments-container" class="space-y-4"></div>
            <div class="text-center mt-6">
                <button id="show-all-comments-btn" class="text-blue-500 hover:underline">全てのコメントを見る</button>
            </div>
            <div id="all-comments-container" class="space-y-4 mt-6 hidden"></div>
        </div>
    </div>
</div>

<script>
const pollId = <?= $poll['id'] ?>;
const choicesData = <?= json_encode($choices) ?>;
let generalCommentsData = <?= json_encode($general_comments) ?>;
let choiceCommentsData = <?= json_encode($choice_comments) ?>;

const detailsArea = document.getElementById('details-area');
const totalVotesSpan = document.getElementById('total-votes');
const ctx = document.getElementById('voteChart').getContext('2d');
let voteChart;

const socket = new WebSocket('ws://localhost:8080');
socket.onopen = () => {
    socket.send(JSON.stringify({ action: 'request_update', poll_id: pollId }));
};
socket.onmessage = (event) => {
    const message = JSON.parse(event.data);
    if (message.type === 'update') {
        renderChartAndDetails(message.labels, message.data);
    }
};

function createCommentElement(comment) {
    const likedClass = comment.liked_by_user ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700';
    // 【脆弱なコード】 comment.content をエスケープせず直接HTMLに埋め込む
    return `
        <div class="comment-item border-t pt-3" data-comment-id="${comment.id}">
            <div class="flex justify-between items-center">
                <p class="font-semibold text-gray-800">${comment.username} <span class="text-sm font-normal text-gray-500">${comment.created_at}</span></p>
                <button class="like-btn ${likedClass} text-sm px-3 py-1 rounded-full" data-comment-id="${comment.id}">
                    👍 <span class="like-count">${comment.likes}</span>
                </button>
            </div>
            <p class="text-gray-700 mt-1">${comment.content}</p>
        </div>`;
}

function renderGeneralComments() {
    const topCommentsContainer = document.getElementById('top-comments-container');
    const allCommentsContainer = document.getElementById('all-comments-container');
    topCommentsContainer.innerHTML = '';
    allCommentsContainer.innerHTML = '';

    if (generalCommentsData.length === 0) {
        topCommentsContainer.innerHTML = '<p class="text-gray-500">まだコメントはありません。</p>';
        document.getElementById('show-all-comments-btn').style.display = 'none';
    } else {
        generalCommentsData.forEach((comment, index) => {
            const commentHtml = createCommentElement(comment);
            if (index < 5) {
                topCommentsContainer.innerHTML += commentHtml;
            } else {
                allCommentsContainer.innerHTML += commentHtml;
            }
        });
        document.getElementById('show-all-comments-btn').style.display = generalCommentsData.length > 5 ? 'inline' : 'none';
    }
}

function renderChoiceComments(choiceId) {
    const container = document.getElementById(`choice-comments-${choiceId}`);
    if (!container) return;
    const comments = choiceCommentsData[choiceId] || [];
    container.innerHTML = '';
    if (comments.length > 0) {
        container.innerHTML += createCommentElement(comments[0]);
        if (comments.length > 1) {
            let hiddenCommentsHtml = '';
            for (let i = 1; i < comments.length; i++) {
                hiddenCommentsHtml += createCommentElement(comments[i]);
            }
            container.innerHTML += `
                <div class="text-left mt-2"><button class="show-more-choice-comments-btn text-blue-500 text-sm hover:underline" data-choice-id="${choiceId}">詳しく見る</button></div>
                <div id="more-choice-comments-${choiceId}" class="hidden space-y-2 mt-2">${hiddenCommentsHtml}</div>`;
        }
    }
}

function renderChartAndDetails(labels, data) {
    const total = data.reduce((sum, value) => sum + value, 0);
    totalVotesSpan.textContent = total;
    detailsArea.innerHTML = '';
    labels.forEach((label, index) => {
        const choice = choicesData[index];
        const votes = data[index];
        const percentage = total > 0 ? Math.round((votes / total) * 100) : 0;
        // 【脆弱なコード】 label をエスケープしない
        const detailHtml = `
            <div>
                <div class="flex justify-between items-center mb-1"><span class="text-lg font-bold">${label}</span><span class="font-semibold">${votes} 票</span></div>
                <div class="w-full bg-gray-200 rounded-full h-6 mb-2"><div class="bg-blue-500 h-6 rounded-full flex items-center justify-center text-white text-sm" style="width: ${percentage}%;">${percentage}%</div></div>
                <div id="choice-comments-${choice.id}" class="pl-4 border-l-2 space-y-2"></div>
                <div class="pl-4 mt-2">
                    <textarea id="reply-input-${choice.id}" class="w-full p-2 border rounded-lg text-sm" rows="2" placeholder="${label}にコメント..."></textarea>
                    <button class="post-reply-btn bg-gray-500 text-white px-3 py-1 rounded text-sm mt-1" data-choice-id="${choice.id}">返信</button>
                </div>
            </div>`;
        detailsArea.innerHTML += detailHtml;
        renderChoiceComments(choice.id);
    });
    renderGeneralComments();
    if (voteChart) {
        voteChart.data.labels = labels;
        voteChart.data.datasets[0].data = data;
        voteChart.update();
    } else {
        voteChart = new Chart(ctx, { type: 'doughnut', data: { labels: labels, datasets: [{ data: data, backgroundColor: ['#3B82F6', '#EF4444', '#F59E0B', '#10B981', '#8B5CF6', '#F97316'] }] }, options: { responsive: true, plugins: { legend: { position: 'top' } } } });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const initialLabels = choicesData.map(c => c.text);
    const initialVotes = choicesData.map(c => parseInt(c.votes, 10));
    renderChartAndDetails(initialLabels, initialVotes);
    document.getElementById('show-all-comments-btn').addEventListener('click', function() {
        document.getElementById('all-comments-container').classList.toggle('hidden');
        this.textContent = this.textContent.includes('全て') ? 'コメントを閉じる' : '全てのコメントを見る';
    });
    document.body.addEventListener('click', async function(e) {
        if (e.target.id === 'post-general-comment-btn') {
            const content = document.getElementById('general-comment-input').value;
            if (!content) return;
            postComment(pollId, content, null);
            document.getElementById('general-comment-input').value = '';
        }
        if (e.target.classList.contains('post-reply-btn')) {
            const choiceId = e.target.dataset.choiceId;
            const content = document.getElementById(`reply-input-${choiceId}`).value;
            if (!content) return;
            postComment(pollId, content, choiceId);
            document.getElementById(`reply-input-${choiceId}`).value = '';
        }
        if (e.target.classList.contains('like-btn')) {
            const commentId = e.target.dataset.commentId;
            toggleLike(commentId, e.target);
        }
        if (e.target.classList.contains('show-more-choice-comments-btn')) {
            const choiceId = e.target.dataset.choiceId;
            const moreCommentsDiv = document.getElementById(`more-choice-comments-${choiceId}`);
            moreCommentsDiv.classList.toggle('hidden');
            e.target.textContent = moreCommentsDiv.classList.contains('hidden') ? '詳しく見る' : '閉じる';
        }
    });
});

async function postComment(poll_id, content, choice_id) {
    const response = await fetch('post_comment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ poll_id, content, choice_id })
    });
    const result = await response.json();
    if (result.success) {
        const newComment = result.comment;
        if (choice_id) {
            choiceCommentsData[choice_id].unshift(newComment);
            renderChoiceComments(choice_id);
        } else {
            generalCommentsData.unshift(newComment);
            renderGeneralComments();
        }
    } else {
        alert('コメントの投稿に失敗しました: ' + result.message);
    }
}

async function toggleLike(comment_id, buttonElement) {
    const response = await fetch('like_comment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ comment_id })
    });
    const result = await response.json();
    if (result.success) {
        buttonElement.querySelector('.like-count').textContent = result.likes;
        buttonElement.classList.toggle('bg-blue-500', result.liked);
        buttonElement.classList.toggle('text-white', result.liked);
        buttonElement.classList.toggle('bg-gray-200', !result.liked);
        buttonElement.classList.toggle('text-gray-700', !result.liked);
        const updateDataArray = (arr) => {
            const comment = arr.find(c => c.id == comment_id);
            if(comment) {
                comment.likes = result.likes;
                comment.liked_by_user = result.liked;
            }
        };
        updateDataArray(generalCommentsData);
        Object.values(choiceCommentsData).forEach(arr => updateDataArray(arr));
        generalCommentsData.sort((a, b) => b.likes - a.likes || new Date(b.created_at) - new Date(a.created_at));
        Object.values(choiceCommentsData).forEach(arr => arr.sort((a, b) => b.likes - a.likes || new Date(b.created_at) - new Date(a.created_at)));
        renderGeneralComments();
        choicesData.forEach(choice => renderChoiceComments(choice.id));
    } else {
        alert('いいねに失敗しました: ' + result.message);
    }
}
</script>
</body>
</html>
