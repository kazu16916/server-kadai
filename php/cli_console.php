<?php
require_once __DIR__ . '/common_init.php';

// adminのみアクセス可能・有効時のみ表示
if (($_SESSION['role'] ?? '') !== 'admin' || empty($_SESSION['cli_attack_mode_enabled'])) {
    header('Location: simulation_tools.php');
    exit;
}
$CLI_TOKEN = $_SESSION['cli_attack_api_token'] ?? '';
?><!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>CLI攻撃演習コンソール（擬似）</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  .term { background:#0b1020; color:#e5e7eb; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
  .term .muted { color:#9ca3af }
  .term .err   { color:#fca5a5 }
  .term .ok    { color:#86efac }
</style>
</head>
<body class="bg-gray-100">
<div class="container mx-auto max-w-5xl px-4 py-6">
  <div class="flex items-center justify-between mb-2">
    <h1 class="text-2xl font-bold">🧪 CLI攻撃演習コンソール（擬似）</h1>
    <a href="ids_dashboard.php" class="text-indigo-600 hover:underline">防御モニタを見る →</a>
  </div>

  <div id="terminal" class="term rounded-xl shadow border border-slate-800 p-4 h-[480px] overflow-y-auto"></div>

  <div class="mt-3 flex gap-2">
    <input id="cli-input" class="flex-1 border rounded-lg px-3 py-2" placeholder="help, scan 22-25, bruteforce alice 6, spray ad --pw P@ssw0rd, sqlinj /login?id=1 ..." />
    <button id="run"   class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">実行</button>
    <button id="clear" class="px-4 py-2 rounded-lg bg-gray-700 text-white hover:bg-gray-800">クリア</button>
  </div>

  <p class="text-xs text-gray-500 mt-2">※ 本コンソールは擬似です。実ネットワークスキャンや侵入は一切行いません。入力はサーバに送られ、cli_events と IDS ログに記録され、防御モニタに即反映されます。</p>
</div>

<script>
const TOKEN = <?= json_encode($CLI_TOKEN) ?>;
const term  = document.getElementById('terminal');
const input = document.getElementById('cli-input');

function appendLine(text, cls='') {
  const div = document.createElement('div');
  if (cls) div.className = cls;
  div.textContent = text;
  term.appendChild(div);
  term.scrollTop = term.scrollHeight;
}
function renderLines(lines) {
  for (const ln of (lines||[])) {
    if (ln === '__CLEAR__') { term.innerHTML=''; continue; }
    appendLine(ln);
  }
}
async function sendCmd(cmd) {
  if (!cmd.trim()) return;
  appendLine(`sim@lab:$ ${cmd}`, 'muted');
  try {
    const res = await fetch('cli_cmd.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CLI-TOKEN': TOKEN
      },
      body: JSON.stringify({ cmd })
    });
    const raw = await res.text();
    let json;
    try { json = JSON.parse(raw); }
    catch (e) {
      appendLine('通信エラー: サーバがJSON以外を返しました', 'err');
      appendLine(raw.slice(0,500), 'err');
      return;
    }
    if (!json.ok) {
      appendLine(`エラー: ${json.error ?? 'unknown'} - ${json.msg ?? ''}`, 'err');
      return;
    }
    renderLines(json.lines);
  } catch (err) {
    appendLine(`通信例外: ${err.message}`, 'err');
  }
}

document.getElementById('run').addEventListener('click', ()=> sendCmd(input.value));
document.getElementById('clear').addEventListener('click', ()=> { term.innerHTML=''; });
input.addEventListener('keydown', (e)=> { if (e.key === 'Enter') sendCmd(input.value); });

// 起動時にバナー＋help
appendLine('*** 模擬 CLI へようこそ（実攻撃は行いません）***', 'muted');
appendLine('利用可能コマンド（擬似）: help, scan, bruteforce, spray, sqlinj, echo, clear', 'muted');
sendCmd('help');
</script>
</body>
</html>
