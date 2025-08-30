<?php
// cli_monitor.php — 受信側（防御）モニタ
session_start();
$is_admin   = (($_SESSION['role'] ?? '') === 'admin');
$cli_enable = !empty($_SESSION['cli_attack_mode_enabled']);
if (!$is_admin || !$cli_enable) { http_response_code(403); exit('forbidden'); }
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>攻撃演習モニタ</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
.badge { font-size:.7rem; padding:.15rem .4rem; border-radius:.3rem }
.badge-scan{ background:#dbeafe; color:#1e40af }
.badge-brute{ background:#fee2e2; color:#991b1b }
.badge-spray{ background:#fef3c7; color:#92400e }
.badge-sqli{ background:#e9d5ff; color:#6b21a8 }
</style>
</head>
<body class="bg-slate-100">
<div class="max-w-5xl mx-auto py-8 px-4">
  <div class="mb-4 flex items-center justify-between">
    <h1 class="text-2xl font-bold">🛡 攻撃演習モニタ（防御側）</h1>
    <a href="cli_console.php" class="text-indigo-600 underline text-sm">攻撃コンソールへ →</a>
  </div>

  <div class="bg-white rounded-xl shadow p-4">
    <div id="feed" class="space-y-2 max-h-[520px] overflow-y-auto"></div>
  </div>
</div>

<script>
let since = 0;
const feed = document.getElementById('feed');

function badge(type){
  type = (type||'').toLowerCase();
  if (type.includes('scan')) return '<span class="badge badge-scan">scan</span>';
  if (type.includes('brute')) return '<span class="badge badge-brute">bruteforce</span>';
  if (type.includes('spray')) return '<span class="badge badge-spray">spray</span>';
  if (type.includes('sqli')) return '<span class="badge badge-sqli">sqli</span>';
  return '<span class="badge bg-slate-200 text-slate-700">event</span>';
}

function add(ev){
  const div = document.createElement('div');
  div.className = 'border rounded-md px-3 py-2 text-sm';
  div.innerHTML =
    `<div class="flex items-center justify-between">
       <div class="font-mono">${badge(ev.event_type)} <span class="text-slate-500">#${ev.id}</span></div>
       <div class="text-xs text-slate-500">${ev.created_at} / ${ev.ip||'-'}</div>
     </div>
     <div class="mt-1">
       <div class="text-slate-800">${ev.event_type}</div>
       <div class="text-slate-500 break-words">${(ev.meta||'').replaceAll('<','&lt;')}</div>
     </div>`;
  feed.appendChild(div);
  feed.scrollTop = feed.scrollHeight;
}

async function tick(){
  try{
    const res = await fetch('cli_events_fetch.php?since_id='+since);
    const js  = await res.json();
    if (js.ok){
      (js.events||[]).forEach(e => { add(e); since = Math.max(since, e.id); });
    }
  }catch(e){ /* noop */ }
}

tick();
setInterval(tick, 2000);
</script>
</body>
</html>
