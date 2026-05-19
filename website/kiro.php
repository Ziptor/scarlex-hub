<?php
session_start();
require_once __DIR__ . '/api/db.php';
$ok = false;
if (!empty($_SESSION['user_id'])) {
    $st = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $st->execute([$_SESSION['user_id']]);
    $u = $st->fetch();
    if ($u && strtolower($u['email']) === 'luckyanencko.andrej2017@yandex.ru') $ok = true;
}
if (!$ok) { header('Location: /authorization.html'); exit; }
?>
<!DOCTYPE html>
<html lang="ru"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kiro API — Scarlex</title>
<link rel="icon" type="image/png" href="logo_nofon.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--bg:#020204;--sf:rgba(255,255,255,.045);--sb:rgba(255,255,255,.08);--tp:#fff;--ts:#a2a5b3;--tm:rgba(255,255,255,.42);--ok:#73f0bc;--err:#ff7f9f;--warn:#ffcf60;--r:14px}
*{box-sizing:border-box;margin:0;padding:0}body{background:var(--bg);color:var(--ts);font:14px/1.6 'Inter',system-ui,sans-serif;min-height:100vh;display:flex}
a{color:inherit;text-decoration:none}
.sidebar{width:260px;min-height:100vh;max-height:100vh;position:fixed;left:0;top:0;bottom:0;background:linear-gradient(180deg,rgba(0,0,0,.5) 0%,rgba(0,0,0,.35) 50%,rgba(0,0,0,.45) 100%);border-right:1px solid var(--sb);padding:20px 16px 16px;display:flex;flex-direction:column;backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);z-index:20;overflow:hidden}
.sidebar>*{position:relative;z-index:1}
.sidebar::after{content:"";position:absolute;top:10%;bottom:10%;right:0;width:1px;background:linear-gradient(180deg,transparent 0%,rgba(255,255,255,.05) 15%,rgba(255,255,255,.22) 50%,rgba(255,255,255,.05) 85%,transparent 100%);pointer-events:none}
.sidebar::before{content:"";position:absolute;inset:0;background-image:linear-gradient(73deg,transparent 49.7%,rgba(255,255,255,.05) 49.85%,rgba(255,255,255,.05) 50.15%,transparent 50.3%),linear-gradient(112deg,transparent 39.7%,rgba(255,255,255,.04) 39.85%,rgba(255,255,255,.04) 40.15%,transparent 40.3%),linear-gradient(28deg,transparent 64.7%,rgba(255,255,255,.035) 64.85%,rgba(255,255,255,.035) 65.15%,transparent 65.3%),linear-gradient(157deg,transparent 24.7%,rgba(255,255,255,.04) 24.85%,rgba(255,255,255,.04) 25.15%,transparent 25.3%);mask-image:radial-gradient(ellipse at 50% 40%,black 20%,transparent 90%);-webkit-mask-image:radial-gradient(ellipse at 50% 40%,black 20%,transparent 90%);pointer-events:none}
.sidebar-glow{position:absolute;top:-60px;left:50%;transform:translateX(-50%);width:240px;height:180px;background:radial-gradient(ellipse at center,rgba(255,255,255,.08),transparent 65%);pointer-events:none;filter:blur(30px);z-index:0}
.sidebar-head{display:flex;align-items:center;justify-content:space-between;gap:8px;padding-right:4px;margin-bottom:10px}
.sidebar-head a{display:block}
.sidebar-logo{height:60px;width:auto;display:block;padding-left:8px;filter:drop-shadow(0 0 18px rgba(255,255,255,.12))}
.sidebar-divider{height:1px;background:linear-gradient(90deg,transparent 0%,rgba(255,255,255,.12) 20%,rgba(255,255,255,.18) 50%,rgba(255,255,255,.12) 80%,transparent 100%);margin:0 4px 24px}
.sidebar-section-label{font-size:.68rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:rgba(255,255,255,.3);padding:0 14px;margin-bottom:10px;display:flex;align-items:center;gap:10px}
.sidebar-section-label::after{content:"";flex:1;height:1px;background:linear-gradient(90deg,rgba(255,255,255,.1),transparent)}
.sidebar-nav{display:flex;flex-direction:column;gap:2px;flex:1;min-height:0;overflow-y:auto;overflow-x:hidden;padding-right:4px;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.12) transparent}
.nav-item{position:relative;display:flex;align-items:center;gap:12px;padding:9px 14px;border-radius:11px;font-size:.9rem;font-weight:600;color:var(--ts);cursor:pointer;transition:color .25s,background .25s;user-select:none;border:1px solid transparent;flex-shrink:0;text-decoration:none}
.nav-item::before{content:"";position:absolute;left:0;top:50%;transform:translateY(-50%) scaleY(0);width:3px;height:20px;border-radius:0 3px 3px 0;background:linear-gradient(180deg,#fff,#aaa);box-shadow:0 0 12px rgba(255,255,255,.4);transition:transform .3s cubic-bezier(.4,.2,.2,1)}
.nav-item:hover{color:#fff;background:rgba(255,255,255,.04)}
.nav-item.is-active{color:#fff;background:linear-gradient(90deg,rgba(255,255,255,.09),rgba(255,255,255,.03));border-color:rgba(255,255,255,.08);box-shadow:inset 0 1px 0 rgba(255,255,255,.06)}
.nav-item.is-active::before{transform:translateY(-50%) scaleY(1)}
.nav-item svg{width:20px;height:20px;flex-shrink:0;opacity:.6;transition:opacity .25s}
.nav-item:hover svg{opacity:.9}
.nav-item.is-active svg{opacity:1}
.main{margin-left:260px;flex:1;padding:30px 36px}
.title{color:#fff;font-size:1.4rem;font-weight:800;margin-bottom:4px}
.sub{color:var(--ts);font-size:.85rem;margin-bottom:24px}
.sec{display:none}.sec.on{display:block}
.g{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:24px}
.sc{padding:16px;border-radius:var(--r);background:var(--sf);border:1px solid var(--sb)}
.sl{color:var(--ts);font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.sv{color:#fff;font-size:1.6rem;font-weight:800}
table{width:100%;border-collapse:collapse;font-size:.82rem}
th{text-align:left;padding:8px 12px;color:var(--tm);font-size:.72rem;font-weight:700;text-transform:uppercase;border-bottom:1px solid var(--sb)}
td{padding:8px 12px;border-bottom:1px solid rgba(255,255,255,.03)}
tr:hover td{background:rgba(255,255,255,.02)}
.badge{padding:2px 8px;border-radius:6px;font-size:.72rem;font-weight:700}
.badge.ok{background:rgba(115,240,188,.1);color:var(--ok)}.badge.off{background:rgba(255,127,159,.1);color:var(--err)}
button{font:inherit;background:var(--sf);color:var(--ts);border:1px solid var(--sb);padding:6px 14px;border-radius:8px;cursor:pointer;font-size:.82rem;font-weight:600;transition:.2s}
button:hover{background:rgba(255,255,255,.08);color:#fff}
input,select{font:inherit;background:var(--sf);color:#fff;border:1px solid var(--sb);padding:8px 14px;border-radius:10px;font-size:.85rem;outline:none}
input:focus{border-color:rgba(255,255,255,.2)}
.row{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px}
.bar{height:5px;border-radius:3px;background:rgba(255,255,255,.06);width:70px;display:inline-block;position:relative;vertical-align:middle}
.bar .f{height:100%;border-radius:3px;position:absolute;left:0;top:0}
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:100;display:none;align-items:center;justify-content:center}
.modal-bg.open{display:flex}
.modal{background:#0a0a12;border:1px solid var(--sb);border-radius:18px;padding:24px;width:90%;max-width:900px;max-height:80vh;overflow-y:auto}
.pcell{max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:help}
pre{background:var(--sf);padding:14px;border-radius:10px;overflow-x:auto;font:13px/1.5 'JetBrains Mono',monospace;color:#ccc;border:1px solid var(--sb)}
.srch{max-width:320px;width:100%}
.toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#1a1a2e;color:#fff;padding:10px 24px;border-radius:10px;border:1px solid var(--sb);font-size:.85rem;font-weight:600;z-index:200;opacity:0;transition:opacity .3s;pointer-events:none}
.toast.show{opacity:1}
</style></head><body>

<aside class="sidebar">
<div class="sidebar-glow"></div>
<div class="sidebar-head">
  <a href="index.html"><img src="scarlex_high.png" alt="Scarlex" class="sidebar-logo"></a>
</div>
<div class="sidebar-divider"></div>
<div class="sidebar-section-label">Kiro API</div>
<nav class="sidebar-nav">
  <div class="nav-item is-active" onclick="go('dash')">
    <svg viewBox="0 0 24 24" fill="none"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Дашборд</span>
  </div>
  <div class="nav-item" onclick="go('keys')">
    <svg viewBox="0 0 24 24" fill="none"><path d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>API ключи</span>
  </div>
  <div class="nav-item" onclick="go('acc')">
    <svg viewBox="0 0 24 24" fill="none"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Аккаунты</span>
  </div>
  <div class="nav-item" onclick="go('health')">
    <svg viewBox="0 0 24 24" fill="none"><path d="M3.05 13H6l3-8 4 16 3-8h4.95" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Здоровье пула</span>
  </div>
  <div class="nav-item" onclick="go('proxies')">
    <svg viewBox="0 0 24 24" fill="none"><path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 5.844A23.888 23.888 0 0015.302 15M2.882 14C2.31 12.85 2 11.456 2 10a10 10 0 1119.547 3M7.835 4.697A4 4 0 0118 9.005" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Прокси</span>
  </div>
  <div class="nav-item" onclick="go('logs')">
    <svg viewBox="0 0 24 24" fill="none"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Логи</span>
  </div>
  <div class="nav-item" onclick="go('guide')">
    <svg viewBox="0 0 24 24" fill="none"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Интеграция</span>
  </div>
  <div class="nav-item" onclick="go('upstream')">
    <svg viewBox="0 0 24 24" fill="none"><path d="M13 10V3L4 14h7v7l9-11h-7z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Мониторинг</span>
  </div>
  <div class="nav-item" onclick="go('mult')">
    <svg viewBox="0 0 24 24" fill="none"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Множители</span>
  </div>
</nav>
<div style="padding-top:12px;margin-top:12px;border-top:1px solid rgba(255,255,255,.08)">
  <a class="nav-item" href="admin" style="color:var(--tm)">
    <svg viewBox="0 0 24 24" fill="none"><path d="M15 19l-7-7 7-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Админ панель</span>
  </a>
</div>
</aside>

<div class="main">
<div class="title">Kiro API</div>
<div class="sub" id="statusLine">Загрузка...</div>

<div id="s-dash" class="sec on">
<div class="g" id="statsGrid"></div>
<div class="sc" id="billCard"></div>
<div class="sc" style="margin-top:12px">
  <div class="row" style="margin-bottom:8px"><b style="color:#fff">Топ-10 ключей по расходу за 24ч</b><div style="flex:1"></div>
    <select id="topHours" onchange="loadTopKeys()" style="font-size:.78rem;padding:4px 8px">
      <option value="1">1ч</option>
      <option value="6">6ч</option>
      <option value="24" selected>24ч</option>
      <option value="168">7 дней</option>
    </select>
  </div>
  <div id="topKeysBox" style="color:var(--tm);font-size:.85rem">Загрузка…</div>
</div>
</div>

<div id="s-keys" class="sec">
<div class="row">
<input class="srch" id="keySearch" placeholder="Поиск ключей..." oninput="filterKeys()">
<div style="flex:1"></div>
<button onclick="bulkPanel()" style="background:rgba(120,150,255,.1);color:#9ab8ff;border-color:rgba(120,150,255,.2)">Bulk</button>
<button onclick="exportKeys('json')">Экспорт JSON</button>
<button onclick="exportKeys('csv')">Экспорт CSV</button>
<button onclick="importCsvBtn()">Импорт CSV</button>
<input type="file" id="csvFile" accept=".csv,text/csv" style="display:none" onchange="onCsvSelected(event)">
<button onclick="genKey()">Сгенерировать</button>
<button onclick="addKey()" style="background:rgba(115,240,188,.1);color:var(--ok);border-color:rgba(115,240,188,.2)">+ Добавить ключ</button>
</div>
<div class="row" id="newKeyRow" style="display:none">
<input id="nk" placeholder="sk-scarlex-..." style="flex:1">
<input id="nn" placeholder="Имя" style="width:120px">
<input id="nr" type="number" value="45" style="width:55px" placeholder="RPM">
<input id="nc" type="number" value="0" style="width:130px" placeholder="Токены (0=∞)">
<select id="nTpl" onchange="applyTemplate('n')" style="width:130px">
  <option value="">Шаблон…</option>
</select>
<button onclick="submitKey()">Создать</button>
<button onclick="document.getElementById('newKeyRow').style.display='none'">Отмена</button>
</div>

<div id="bulkRow" style="display:none;margin-bottom:12px" class="sc">
  <div style="color:#fff;font-weight:700;margin-bottom:8px">Массовое создание ключей</div>
  <div class="row" style="gap:8px;flex-wrap:wrap">
    <input id="bCount" type="number" value="10" min="1" max="200" style="width:80px" placeholder="Кол-во">
    <input id="bPrefix" value="trial-" style="width:120px" placeholder="Префикс имени">
    <input id="bRpm" type="number" value="45" style="width:80px" placeholder="RPM">
    <input id="bCred" type="number" value="1000000" style="width:130px" placeholder="Токены (0=∞)">
    <select id="bTpl" onchange="applyTemplate('b')" style="width:130px">
      <option value="">Шаблон…</option>
    </select>
    <button onclick="bulkCreate()" style="background:rgba(115,240,188,.15);color:var(--ok);border-color:rgba(115,240,188,.3)">Создать пакет</button>
    <button onclick="document.getElementById('bulkRow').style.display='none'">Закрыть</button>
  </div>
  <div style="color:var(--tm);font-size:.78rem;margin-top:6px">Имена будут вида <code>trial-1</code>, <code>trial-2</code>… После создания скачается JSON с полными ключами (один раз!)</div>
</div>

<div id="bulkActionBar" style="display:none;margin-bottom:12px" class="sc">
  <div class="row" style="gap:8px;flex-wrap:wrap;align-items:center">
    <span style="color:#fff;font-weight:700"><span id="selCount">0</span> выбрано</span>
    <div style="flex:1"></div>
    <button onclick="bulkAction('enable')">Включить</button>
    <button onclick="bulkAction('disable')">Выключить</button>
    <button onclick="bulkSetCredits()">Заменить лимит</button>
    <button onclick="bulkTopup()" style="background:rgba(115,240,188,.1);color:var(--ok);border-color:rgba(115,240,188,.2)">+Топ-ап</button>
    <button onclick="bulkSetRpm()">Изменить RPM</button>
    <button onclick="bulkAction('reset_used')">Сбросить расход</button>
    <button onclick="bulkAction('delete')" style="background:rgba(255,107,107,.1);color:var(--err);border-color:rgba(255,107,107,.2)">Удалить</button>
    <button onclick="clearSelection()">Снять выбор</button>
  </div>
</div>

<div id="keysTable"></div>
</div>

<div id="s-acc" class="sec">
<div class="row"><b style="color:#fff">Аккаунты Kiro</b><span id="accSum" style="color:var(--tm);margin-left:8px"></span></div>
<div id="accTable"></div>
</div>

<!-- ─── Health Pool ─── -->
<div id="s-health" class="sec">
  <div class="row" style="margin-bottom:8px">
    <b style="color:#fff">Здоровье пула</b>
    <span id="healthSum" style="color:var(--tm);margin-left:8px;font-size:.85rem">—</span>
    <div style="flex:1"></div>
    <button onclick="loadHealth()">Обновить</button>
    <button onclick="reloadAccounts()" style="background:rgba(120,170,255,.1);color:#9ab8ff;border-color:rgba(120,170,255,.2)">Reload accounts.json</button>
  </div>
  <div class="sc">
    <div id="healthTable" style="color:var(--tm);font-size:.85rem">Загрузка…</div>
  </div>
  <div class="sc" style="margin-top:12px">
    <div class="row" style="margin-bottom:8px">
      <b style="color:#fff">История auto-bans</b>
      <span style="color:var(--tm);margin-left:8px;font-size:.78rem">последние 50 событий</span>
      <div style="flex:1"></div>
      <button onclick="loadBansHistory()">Обновить</button>
    </div>
    <div id="bansTable" style="color:var(--tm);font-size:.85rem">Загрузка…</div>
  </div>
</div>

<!-- ─── Proxies ─── -->
<div id="s-proxies" class="sec">
  <div class="row" style="margin-bottom:8px">
    <b style="color:#fff">Прокси-пул</b>
    <span id="proxiesSum" style="color:var(--tm);margin-left:8px;font-size:.85rem">—</span>
    <div style="flex:1"></div>
    <button onclick="addProxyToggle()" style="background:rgba(115,240,188,.1);color:var(--ok);border-color:rgba(115,240,188,.2)">+ Добавить прокси</button>
  </div>
  <div class="row" id="addProxyRow" style="display:none;margin-bottom:8px">
    <input id="newProxyUrl" placeholder="http://user:pass@host:port" style="flex:1">
    <input id="newProxyLabel" placeholder="Лейбл (необязательно)" style="width:200px">
    <button onclick="addProxy()" style="background:rgba(115,240,188,.1);color:var(--ok);border-color:rgba(115,240,188,.2)">Сохранить</button>
    <button onclick="addProxyToggle()">Отмена</button>
  </div>
  <div class="sc">
    <div id="proxiesTable" style="color:var(--tm);font-size:.85rem">Загрузка…</div>
  </div>
</div>

<div id="s-logs" class="sec">
<div class="row" style="flex-wrap:wrap;gap:8px;align-items:center">
  <b style="color:#fff">Лог запросов</b>
  <span id="logCnt" style="color:var(--tm)"></span>
  <div style="flex:1"></div>
  <input id="logQ" placeholder="Поиск по тексту..." style="width:200px" oninput="logFilterDebounce()">
  <select id="logModel" onchange="loadLogs()" style="font-size:.78rem;padding:4px 8px">
    <option value="">Все модели</option>
    <option value="claude-sonnet-4">claude-sonnet-4</option>
    <option value="claude-sonnet-4-thinking">claude-sonnet-4-thinking</option>
    <option value="claude-opus-4.7">claude-opus-4.7</option>
    <option value="claude-opus-4.6">claude-opus-4.6</option>
    <option value="claude-haiku-3.5">claude-haiku-3.5</option>
  </select>
  <select id="logStatus" onchange="loadLogs()" style="font-size:.78rem;padding:4px 8px">
    <option value="">Все статусы</option>
    <option value="ok">ok</option>
    <option value="error">error</option>
  </select>
  <select id="logKey" onchange="loadLogs()" style="font-size:.78rem;padding:4px 8px;max-width:180px">
    <option value="">Все ключи</option>
  </select>
  <select id="logSince" onchange="loadLogs()" style="font-size:.78rem;padding:4px 8px">
    <option value="">Всё время</option>
    <option value="1">1ч</option>
    <option value="6">6ч</option>
    <option value="24" selected>24ч</option>
    <option value="168">7 дней</option>
  </select>
  <select id="logLimit" onchange="loadLogs()" style="font-size:.78rem;padding:4px 8px">
    <option value="50">50</option>
    <option value="100" selected>100</option>
    <option value="500">500</option>
    <option value="2000">2000</option>
  </select>
  <button onclick="loadLogs()">Обновить</button>
</div>
<div id="logBox" style="margin-top:8px"></div>
</div>

<div id="s-guide" class="sec" id="guideContent"></div>

<div id="s-upstream" class="sec">
<div class="row" style="margin-bottom:16px">
  <b style="color:#fff;font-size:1rem">Мониторинг VPS</b>
  <select id="upstreamRange" onchange="loadMetricsCharts()" style="margin-left:auto;font-size:.78rem;padding:4px 8px">
    <option value="1">Последний 1ч</option>
    <option value="3">Последние 3ч</option>
    <option value="6" selected>Последние 6ч</option>
    <option value="12">Последние 12ч</option>
    <option value="24">Последние 24ч</option>
  </select>
  <span id="upstreamTimer" style="color:var(--tm);margin-left:8px;font-size:.78rem">Автообновление: 30с</span>
  <button onclick="loadUpstream()" style="margin-left:8px">Обновить</button>
</div>

<div id="upstreamStatus" style="margin-bottom:16px">
  <div class="g" id="upstreamStats"></div>
</div>

<!-- VPS system load -->
<div style="margin-bottom:20px">
  <div class="row" style="margin-bottom:8px">
    <b style="color:#fff">Нагрузка VPS</b>
    <span id="sysLoadTs" style="color:var(--tm);margin-left:8px;font-size:.78rem"></span>
  </div>
  <div id="sysLoadCards" style="display:grid;grid-template-columns:repeat(6,1fr);gap:10px">
    <div class="sc" style="padding:12px">
      <div class="sl">CPU</div>
      <div id="sysCpu" style="font-size:1.4rem;font-weight:700;color:#fff">—</div>
      <div id="sysCpuSub" style="color:var(--tm);font-size:.72rem">—</div>
    </div>
    <div class="sc" style="padding:12px">
      <div class="sl">RAM</div>
      <div id="sysMem" style="font-size:1.4rem;font-weight:700;color:#fff">—</div>
      <div id="sysMemSub" style="color:var(--tm);font-size:.72rem">—</div>
    </div>
    <div class="sc" style="padding:12px">
      <div class="sl">Диск /</div>
      <div id="sysDisk" style="font-size:1.4rem;font-weight:700;color:#fff">—</div>
      <div id="sysDiskSub" style="color:var(--tm);font-size:.72rem">—</div>
    </div>
    <div class="sc" style="padding:12px">
      <div class="sl">Сеть ↓</div>
      <div id="sysNetRx" style="font-size:1.4rem;font-weight:700;color:#fff">—</div>
      <div id="sysNetRxSub" style="color:var(--tm);font-size:.72rem">—</div>
    </div>
    <div class="sc" style="padding:12px">
      <div class="sl">Сеть ↑</div>
      <div id="sysNetTx" style="font-size:1.4rem;font-weight:700;color:#fff">—</div>
      <div id="sysNetTxSub" style="color:var(--tm);font-size:.72rem">—</div>
    </div>
    <div class="sc" style="padding:12px">
      <div class="sl">Load avg</div>
      <div id="sysLoad" style="font-size:1.4rem;font-weight:700;color:#fff">—</div>
      <div id="sysLoadSub" style="color:var(--tm);font-size:.72rem">—</div>
    </div>
  </div>
</div>

<!-- Charts -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
  <div class="sc" style="padding:12px">
    <div class="sl">Аптайм и статус</div>
    <canvas id="chartUptime" height="120"></canvas>
  </div>
  <div class="sc" style="padding:12px">
    <div class="sl">Аккаунты (живые / cooldown)</div>
    <canvas id="chartAccounts" height="120"></canvas>
  </div>
  <div class="sc" style="padding:12px">
    <div class="sl">RPM (запросов в минуту)</div>
    <canvas id="chartRpm" height="120"></canvas>
  </div>
  <div class="sc" style="padding:12px">
    <div class="sl">Ошибки (за последние 60 сек)</div>
    <canvas id="chartErrors" height="120"></canvas>
  </div>
  <div class="sc" style="padding:12px">
    <div class="sl">Токены (за интервал)</div>
    <canvas id="chartTokens" height="120"></canvas>
  </div>
  <div class="sc" style="padding:12px">
    <div class="sl">Осталось кредитов</div>
    <canvas id="chartCredits" height="120"></canvas>
  </div>
</div>

<div style="margin-bottom:20px">
  <div class="row"><b style="color:#fff">Состояние аккаунтов</b><span id="upAccSummary" style="color:var(--tm);margin-left:8px"></span></div>
  <div id="upstreamAccounts"></div>
</div>

<div style="margin-bottom:20px">
  <div class="row"><b style="color:#fff">Последние запросы</b><span id="upLogSummary" style="color:var(--tm);margin-left:8px"></span></div>
  <div id="upstreamLogs"></div>
</div>
</div>
</div>
<div class="toast" id="toast"></div>

<div class="modal-bg" id="keyModal" onclick="if(event.target===this)this.classList.remove('open')">
<div class="modal">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
<h2 id="mTitle" style="color:#fff;font-size:1.1rem;font-weight:700">Детали ключа</h2>
<button onclick="document.getElementById('keyModal').classList.remove('open')">Закрыть</button>
</div>
<div id="mInfo"></div>
<h3 style="color:#fff;font-size:.9rem;font-weight:700;margin:16px 0 8px">История запросов</h3>
<div id="mLogs">Загрузка...</div>
</div>
</div>

<div class="modal-bg" id="wlModal" onclick="if(event.target===this)this.classList.remove('open')">
<div class="modal" style="max-width:480px">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
  <h2 style="color:#fff;font-size:1.05rem;font-weight:700">Whitelist моделей</h2>
  <button onclick="document.getElementById('wlModal').classList.remove('open')">×</button>
</div>
<div style="color:var(--tm);font-size:.82rem;margin-bottom:12px">
  Отметь модели, к которым ключ имеет доступ. Если ничего не отмечено — разрешены все модели.
</div>
<div id="wlList"></div>
<div class="row" style="margin-top:16px;gap:8px">
  <button onclick="wlSelectAll(true)" style="font-size:.78rem">Выбрать все</button>
  <button onclick="wlSelectAll(false)" style="font-size:.78rem">Снять все</button>
  <div style="flex:1"></div>
  <button onclick="document.getElementById('wlModal').classList.remove('open')">Отмена</button>
  <button onclick="wlSave()" style="background:rgba(115,240,188,.15);color:var(--ok);border-color:rgba(115,240,188,.3)">Сохранить</button>
</div>
</div>
</div>

<!-- New key reveal modal (admin) — shown after submitKey/duplicate; full key visible only here -->
<div class="modal-bg" id="newKeyModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal" style="max-width:560px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <h2 style="color:var(--ok);font-size:1.1rem;font-weight:700">✅ Ключ создан</h2>
      <button onclick="document.getElementById('newKeyModal').classList.remove('open')">×</button>
    </div>
    <div style="color:var(--tm);font-size:.85rem;margin-bottom:6px">Имя: <b style="color:#fff" id="nkmName">—</b></div>
    <div style="color:var(--tm);font-size:.85rem;margin-bottom:14px">Лимит токенов: <b style="color:#fff" id="nkmTokens">—</b></div>

    <div style="background:rgba(255,206,90,.06);border:1px solid rgba(255,206,90,.2);color:#ffce5a;padding:10px 12px;border-radius:8px;font-size:.82rem;line-height:1.55;margin-bottom:12px">
      ⚠️ <b>Скопируй ключ сейчас.</b> Полная версия больше нигде не показывается — на этой странице будут только masked-preview.
    </div>

    <div style="display:flex;gap:8px;align-items:stretch;margin-bottom:8px">
      <input id="nkmKey" readonly style="flex:1;font-family:'JetBrains Mono',ui-monospace,monospace;font-size:.85rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:10px 12px;color:#fff">
      <button onclick="nkmCopy()" style="background:rgba(115,240,188,.15);color:var(--ok);border-color:rgba(115,240,188,.3);flex:0 0 auto">📋 Копировать</button>
    </div>

    <div class="row" style="margin-top:18px;gap:8px">
      <div style="flex:1"></div>
      <button onclick="document.getElementById('newKeyModal').classList.remove('open')" style="background:rgba(115,240,188,.15);color:var(--ok);border-color:rgba(115,240,188,.3)">Сохранил, продолжить</button>
    </div>
  </div>
</div>

<div id="s-mult" class="sec">
<div class="row" style="margin-bottom:16px">
  <b style="color:#fff;font-size:1rem">Множители стоимости по моделям</b>
  <div style="flex:1"></div>
  <button onclick="addMultRow()" style="background:rgba(115,240,188,.1);color:var(--ok);border-color:rgba(115,240,188,.2)">+ Добавить</button>
  <button onclick="loadMult()">Обновить</button>
</div>
<div class="sc" style="margin-bottom:12px">
  <div style="color:var(--tm);font-size:.85rem;line-height:1.5">
    Каждый запрос списывает <b style="color:#fff">prompt_tokens + completion_tokens × множитель</b> с биллинга API-ключа. На реальный расход Kiro-аккаунта множитель не влияет — это внутренний коэффициент для тарификации.
  </div>
</div>
<div id="multTable"></div>
<div class="row" style="margin-top:16px">
  <div style="flex:1"></div>
  <button onclick="saveMult()" style="background:rgba(115,240,188,.15);color:var(--ok);border-color:rgba(115,240,188,.3)">Сохранить</button>
</div>
</div>

<script>
// All API goes through admin.php (session-protected)
function api(action, extra){
  var fd=new FormData();fd.append('action',action);
  if(extra)Object.keys(extra).forEach(k=>fd.append(k,extra[k]));
  return fetch('api/admin.php',{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(d=>{
    if(d.error==='Не авторизован'||d.error==='Доступ запрещён'){window.location.href='dashboard';return null;}
    return d;
  });
}
// VPS calls go through shared-hosting proxy (api/admin.php?action=kiro_proxy):
// avoids CORS, hides admin token, bypasses CF tunnel flakes, uses api-direct path.
function vps(m,p,b){
  return fetch('api/admin.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    credentials:'same-origin',
    body:JSON.stringify({action:'kiro_proxy',method:m,path:p,body:b||null})
  }).then(r=>r.json());
}

// Verify admin session on load
api('kiro_status').then(d=>{if(!d){return;}initTemplates();refresh();}).catch(()=>{window.location.href='dashboard';});

var _allKeys=[];
// Effective limit: prefer tokens_limit (current canonical), fall back to legacy credits_limit.
function effLimit(k){ var t=k&&k.tokens_limit||0; return t>0?t:(k&&k.credits_limit||0); }
function effUsed(k){
  // Если задан tokens_limit — считаем по tokens_used. Иначе по credits_used (legacy).
  if (k && k.tokens_limit && k.tokens_limit>0) return k.tokens_used||0;
  return (k && k.credits_used)||0;
}
function effRemaining(k){ var L=effLimit(k); return L>0 ? Math.max(0, L - effUsed(k)) : -1; }
function effPercent(k){ var L=effLimit(k); return L>0 ? Math.round(effUsed(k)/L*100) : 0; }
function refresh(){loadStatus();loadKeys();loadAccounts();loadLogs();loadGuide();}
function go(t){var tabs=['dash','keys','acc','health','proxies','logs','guide','upstream','mult'];document.querySelectorAll('.sidebar-nav .nav-item').forEach(function(n,i){n.classList.toggle('is-active',tabs[i]===t)});document.querySelectorAll('.sec').forEach(function(s){s.classList.remove('on')});document.getElementById('s-'+t).classList.add('on');if(t==='upstream'){loadUpstream();loadMetricsCharts();}if(t==='mult'){loadMult();}if(t==='health'){loadHealth();loadBansHistory();}if(t==='proxies'){loadProxies();}}

// ─── Health pool ───
function fmtAgo(iso){if(!iso)return '—';var d=new Date(iso);var s=Math.floor((Date.now()-d.getTime())/1000);if(s<60)return s+'с';if(s<3600)return Math.floor(s/60)+'м';if(s<86400)return Math.floor(s/3600)+'ч';return Math.floor(s/86400)+'д';}
function fmtDuration(sec){if(sec===null||sec===undefined)return '—';if(sec<60)return sec+'с';if(sec<3600)return Math.floor(sec/60)+'м';return Math.floor(sec/3600)+'ч '+Math.floor((sec%3600)/60)+'м';}

function loadHealth(){
  document.getElementById('healthTable').innerHTML='Загрузка…';
  vps('GET','/admin/accounts/health').then(function(d){
    if(!d||!d.accounts){document.getElementById('healthTable').innerHTML='<span style="color:var(--err)">Ошибка загрузки</span>';return;}
    var arr=d.accounts;
    var banned=arr.filter(function(a){return a.auto_banned}).length;
    var frozen=arr.filter(function(a){return a.frozen}).length;
    var streaking=arr.filter(function(a){return a.error_streak>0&&!a.auto_banned}).length;
    document.getElementById('healthSum').innerHTML=
      arr.length+' аккаунтов · '+
      '<span style="color:var(--ok)">'+(arr.length-banned-frozen)+' здоровых</span> · '+
      '<span style="color:#ff9a5a">'+streaking+' streaking</span> · '+
      '<span style="color:var(--err)">'+banned+' auto-banned</span> · '+
      '<span style="color:#9ab8ff">'+frozen+' frozen</span>';
    var html='<table style="width:100%;border-collapse:collapse;font-size:.85rem"><thead><tr style="text-align:left;border-bottom:1px solid rgba(255,255,255,.1);color:var(--tm)">'+
      '<th style="padding:6px 8px">Account</th><th>Email</th><th>Proxy</th><th>Status</th><th>Streak</th><th>Recovers in</th><th>Actions</th></tr></thead><tbody>';
    arr.forEach(function(a){
      var status,color;
      if(a.is_dead){status='DEAD';color='var(--err)';}
      else if(a.auto_banned){status='AUTO-BAN';color='var(--err)';}
      else if(a.frozen){status='FROZEN';color='#9ab8ff';}
      else if(a.error_streak>0){status='STREAK';color='#ff9a5a';}
      else{status='OK';color='var(--ok)';}
      var rec=a.auto_ban_recovers_in_sec!==null?fmtDuration(a.auto_ban_recovers_in_sec):'—';
      var proxy=(a.proxy||'—').length>40?(a.proxy.slice(0,18)+'…'+a.proxy.slice(-12)):(a.proxy||'—');
      html+='<tr style="border-bottom:1px dashed rgba(255,255,255,.06)">'+
        '<td style="padding:6px 8px;font-family:monospace;color:#fff">'+a.account_id+'</td>'+
        '<td style="color:var(--tm);font-size:.78rem">'+(a.email||'—')+'</td>'+
        '<td style="font-family:monospace;font-size:.74rem;color:var(--tm)" title="'+(a.proxy||'')+'">'+proxy+'</td>'+
        '<td><span style="color:'+color+';font-weight:700;font-size:.78rem;letter-spacing:.06em">'+status+'</span></td>'+
        '<td style="text-align:center">'+(a.error_streak||0)+'</td>'+
        '<td style="font-size:.78rem;color:var(--tm)">'+rec+'</td>'+
        '<td>'+
          (a.auto_banned?'<button onclick="recoverAccount(\''+a.account_id+'\')" style="font-size:.74rem;padding:3px 8px">Recover</button> ':'')+
          (a.frozen?
            '<button onclick="unfreezeAccount(\''+a.account_id+'\')" style="font-size:.74rem;padding:3px 8px">Unfreeze</button>':
            '<button onclick="freezeAccount(\''+a.account_id+'\')" style="font-size:.74rem;padding:3px 8px">Freeze</button>')+
        '</td></tr>';
    });
    html+='</tbody></table>';
    document.getElementById('healthTable').innerHTML=html;
  });
}

function recoverAccount(id){
  if(!confirm('Manual recover (unban) аккаунта '+id+'?'))return;
  vps('POST','/admin/accounts/'+encodeURIComponent(id)+'/unfreeze').then(function(){
    // auto-ban is in-memory; freeze API is separate. The cleanest manual recovery is
    // just to wait for natural cooldown OR call unfreeze (which has no effect on auto-ban).
    // Easiest workaround: trigger a reload which doesn't change banned dict but signals reload.
    // For now we just call reload to refresh state.
    vps('POST','/admin/accounts/reload').then(loadHealth);
  });
}
function freezeAccount(id){
  if(!confirm('Заморозить аккаунт '+id+'? Он будет исключён из ротации.'))return;
  vps('POST','/admin/accounts/'+encodeURIComponent(id)+'/freeze').then(loadHealth);
}
function unfreezeAccount(id){
  vps('POST','/admin/accounts/'+encodeURIComponent(id)+'/unfreeze').then(loadHealth);
}
function reloadAccounts(){
  vps('POST','/admin/accounts/reload').then(function(d){
    var msg='Reload: +'+(d.added||[]).length+' добавлено, -'+(d.removed||[]).length+' удалено, total='+(d.total||0);
    alert(msg);
    loadHealth();
  });
}

function loadBansHistory(){
  document.getElementById('bansTable').innerHTML='Загрузка…';
  vps('GET','/admin/bans/history?limit=50').then(function(d){
    if(!d||!d.bans){document.getElementById('bansTable').innerHTML='<span style="color:var(--err)">Ошибка</span>';return;}
    if(!d.bans.length){document.getElementById('bansTable').innerHTML='<span style="color:var(--tm)">Истории нет — аккаунты пока ни разу не банились</span>';return;}
    var html='<table style="width:100%;border-collapse:collapse;font-size:.82rem"><thead><tr style="text-align:left;border-bottom:1px solid rgba(255,255,255,.1);color:var(--tm)">'+
      '<th style="padding:6px 8px">Account</th><th>Banned</th><th>Duration</th><th>Streak</th><th>Last error</th></tr></thead><tbody>';
    d.bans.forEach(function(b){
      var dur=b.recovered_at?fmtDuration(b.duration_sec):'<span style="color:var(--err)">ongoing</span>';
      var err=(b.last_error||'').replace(/[<>]/g,'').slice(0,80);
      html+='<tr style="border-bottom:1px dashed rgba(255,255,255,.05)">'+
        '<td style="padding:6px 8px;font-family:monospace;color:#fff">'+b.account_id+'</td>'+
        '<td style="color:var(--tm);font-size:.76rem">'+fmtAgo(b.banned_at)+' назад</td>'+
        '<td style="color:var(--tm);font-size:.76rem">'+dur+'</td>'+
        '<td style="text-align:center">'+(b.ban_count||0)+'</td>'+
        '<td style="font-size:.74rem;color:var(--tm);max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'+(b.last_error||'').replace(/"/g,'&quot;')+'">'+err+'</td>'+
        '</tr>';
    });
    html+='</tbody></table>';
    document.getElementById('bansTable').innerHTML=html;
  });
}

// ─── Proxies ───
function addProxyToggle(){
  var r=document.getElementById('addProxyRow');
  r.style.display=r.style.display==='none'?'flex':'none';
  if(r.style.display!=='none')document.getElementById('newProxyUrl').focus();
}
function addProxy(){
  var url=document.getElementById('newProxyUrl').value.trim();
  var label=document.getElementById('newProxyLabel').value.trim();
  if(!url){alert('URL обязателен');return;}
  vps('POST','/admin/proxies',{url:url,label:label}).then(function(d){
    if(d&&d.status){
      document.getElementById('newProxyUrl').value='';
      document.getElementById('newProxyLabel').value='';
      addProxyToggle();
      loadProxies();
    }else if(d&&d.detail){alert('Ошибка: '+d.detail);}
  });
}
function deleteProxy(url){
  if(!confirm('Удалить прокси?\n'+url))return;
  vps('DELETE','/admin/proxies?url='+encodeURIComponent(url)).then(function(d){
    if(d&&d.detail){alert('Ошибка: '+d.detail);return;}
    loadProxies();
  });
}
function assignProxy(accountId, currentProxy){
  var px=prompt('Прокси для '+accountId+' (пусто = очистить):', currentProxy||'');
  if(px===null)return;
  vps('PUT','/admin/accounts/'+encodeURIComponent(accountId)+'/proxy',{proxy:px.trim()}).then(function(d){
    if(d&&d.detail){alert('Ошибка: '+d.detail);return;}
    loadProxies();
    // Refresh health table too if user has it open
    if(document.getElementById('s-health').classList.contains('on'))loadHealth();
  });
}
function loadProxies(){
  document.getElementById('proxiesTable').innerHTML='Загрузка…';
  Promise.all([vps('GET','/admin/proxies'),vps('GET','/admin/accounts/health')]).then(function(arr){
    var pd=arr[0],hd=arr[1];
    if(!pd||!pd.proxies){document.getElementById('proxiesTable').innerHTML='<span style="color:var(--err)">Ошибка</span>';return;}
    var accounts=(hd&&hd.accounts)||[];
    document.getElementById('proxiesSum').textContent=pd.proxies.length+' прокси · '+accounts.filter(function(a){return a.proxy}).length+'/'+accounts.length+' аккаунтов с прокси';

    // Two tables: proxies list + accounts with their proxies
    var html='<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">';

    // Proxies pool
    html+='<div><div style="color:var(--tm);font-size:.78rem;letter-spacing:.12em;text-transform:uppercase;margin-bottom:8px">Pool</div>';
    if(!pd.proxies.length){
      html+='<div style="color:var(--tm);font-style:italic">Прокси не настроены</div>';
    }else{
      html+='<table style="width:100%;border-collapse:collapse;font-size:.82rem">';
      pd.proxies.forEach(function(p){
        var url=p.url.length>50?(p.url.slice(0,28)+'…'+p.url.slice(-18)):p.url;
        html+='<tr style="border-bottom:1px dashed rgba(255,255,255,.06)">'+
          '<td style="padding:6px 8px;font-family:monospace;font-size:.76rem;color:#fff" title="'+p.url.replace(/"/g,'&quot;')+'">'+url+'</td>'+
          '<td style="color:var(--tm);font-size:.76rem">'+(p.label||'—')+'</td>'+
          '<td style="text-align:center;color:'+(p.usage_count>0?'var(--ok)':'var(--tm)')+'">'+p.usage_count+' использ.</td>'+
          '<td><button onclick="deleteProxy(\''+p.url.replace(/'/g,'\\\'')+'\')" style="font-size:.72rem;padding:2px 6px;color:var(--err)">×</button></td>'+
        '</tr>';
      });
      html+='</table>';
    }
    html+='</div>';

    // Account → proxy assignment
    html+='<div><div style="color:var(--tm);font-size:.78rem;letter-spacing:.12em;text-transform:uppercase;margin-bottom:8px">Назначение</div>';
    html+='<table style="width:100%;border-collapse:collapse;font-size:.82rem">';
    accounts.forEach(function(a){
      var px=a.proxy||'';
      var pxShort=px.length>30?(px.slice(0,14)+'…'+px.slice(-12)):(px||'— нет —');
      html+='<tr style="border-bottom:1px dashed rgba(255,255,255,.05)">'+
        '<td style="padding:5px 8px;font-family:monospace;color:#fff">'+a.account_id+'</td>'+
        '<td style="font-family:monospace;font-size:.74rem;color:'+(px?'var(--ok)':'var(--tm)')+'" title="'+(px||'').replace(/"/g,'&quot;')+'">'+pxShort+'</td>'+
        '<td><button onclick="assignProxy(\''+a.account_id+'\',\''+(px||'').replace(/'/g,'\\\'')+'\')" style="font-size:.72rem;padding:2px 6px">Изменить</button></td>'+
      '</tr>';
    });
    html+='</table></div>';
    html+='</div>';
    document.getElementById('proxiesTable').innerHTML=html;
  });
}

function loadStatus(){
  vps('GET','/status').then(d=>{
    if(!d||d.detail){document.getElementById('statusLine').innerHTML='<span style="color:var(--err)">VPS Offline</span>';return;}
    document.getElementById('statusLine').textContent=d.pool.alive+'/'+d.pool.total+' аккаунтов • Аптайм: '+d.uptime+' • Свободно: '+d.pool.available;
    document.getElementById('statsGrid').innerHTML=
      sc('Статус','● Онлайн','ok')+sc('Аккаунты',d.pool.alive+'/'+d.pool.total)+sc('Запросов',fmt(d.stats.total_requests))+
      sc('Токенов',fmtT(d.stats.total_tokens))+sc('Аптайм',d.uptime)+sc('Свободно',d.pool.available);
  }).catch(()=>{document.getElementById('statusLine').innerHTML='<span style="color:var(--err)">Оффлайн</span>';});
}
function sc(l,v,c){return'<div class="sc"><div class="sl">'+l+'</div><div class="sv"'+(c?' style="color:var(--'+c+')"':'')+'>'+v+'</div></div>';}

var _sparkData = {};
var _lastSeen = {};
function loadKeys(){
  Promise.all([
    vps('GET','/admin/keys'),
    vps('GET','/admin/sparkline-bulk?hours=24').catch(function(){return null;}),
  ]).then(function(arr){
    var d = arr[0], sp = arr[1];
    if(!d||!d.keys)return;_allKeys=d.keys;
    _sparkData = (sp && sp.sparkline) || {};
    _lastSeen = (sp && sp.last_seen) || {};
    var tL=0,tU=0,tR=0,ul=0;d.keys.forEach(k=>{var L=effLimit(k),U=effUsed(k);if(L>0){tL+=L;tU+=U;}else ul++;tR+=(k.stats?k.stats.requests:0);});
    document.getElementById('billCard').innerHTML='<div class="g" style="margin:0">'+
      sc('Ключей',d.keys.length)+sc('Безлимитных',ul)+sc('Выделено',fmtT(tL))+sc('Использовано',fmtT(tU))+sc('Осталось',fmtT(Math.max(0,tL-tU)))+sc('Всего запросов',fmt(tR))+'</div>';
    filterKeys();
    loadTopKeys();
  });
}

function sparkSvg(arr, color){
  if(!arr || !arr.length) return '<span style="color:var(--tm)">—</span>';
  var w=80, h=24, max=Math.max.apply(null, arr) || 1;
  var step = w / (arr.length - 1 || 1);
  var pts = arr.map(function(v,i){
    var y = h - (v / max) * (h - 2) - 1;
    return (i * step).toFixed(1) + ',' + y.toFixed(1);
  }).join(' ');
  return '<svg width="'+w+'" height="'+h+'" style="vertical-align:middle"><polyline fill="none" stroke="'+(color||'#9ab8ff')+'" stroke-width="1.5" points="'+pts+'"/></svg>';
}

function fmtAgo(ts){
  if(!ts) return '<span style="color:var(--tm)">никогда</span>';
  var diff = Date.now()/1000 - ts;
  if(diff < 60) return Math.floor(diff)+'с назад';
  if(diff < 3600) return Math.floor(diff/60)+'м назад';
  if(diff < 86400) return Math.floor(diff/3600)+'ч назад';
  return Math.floor(diff/86400)+'д назад';
}

function loadTopKeys(){
  var h = parseInt(document.getElementById('topHours').value)||24;
  vps('GET','/admin/keys/top?limit=10&hours='+h).then(function(d){
    if(!d || !d.top){ document.getElementById('topKeysBox').textContent='—'; return; }
    if(!d.top.length){ document.getElementById('topKeysBox').innerHTML='<span style="color:var(--tm)">Нет запросов за период</span>'; return; }
    var html = '<table style="margin:0"><tr><th>#</th><th>Ключ</th><th>Имя</th><th>Запросов</th><th>Вх</th><th>Исх</th><th>Всего</th><th>Last seen</th></tr>';
    d.top.forEach(function(k, i){
      html += '<tr>';
      html += '<td style="color:var(--tm)">'+(i+1)+'</td>';
      html += '<td style="font-family:JetBrains Mono;font-size:.75rem;cursor:pointer" onclick="navigator.clipboard.writeText(this.textContent)">'+esc(k.key_preview)+'</td>';
      html += '<td><a href="#" onclick="showKey(\''+esc(k.key_preview)+'\');return false" style="color:#fff">'+esc(k.key_name||'?')+'</a></td>';
      html += '<td>'+fmt(k.requests)+'</td>';
      html += '<td>'+fmtT(k.prompt_tokens)+'</td>';
      html += '<td>'+fmtT(k.completion_tokens)+'</td>';
      html += '<td style="color:#fff;font-weight:700">'+fmtT(k.total_tokens)+'</td>';
      html += '<td style="color:var(--tm);font-size:.75rem">'+fmtAgo(k.last_seen)+'</td>';
      html += '</tr>';
    });
    html += '</table>';
    document.getElementById('topKeysBox').innerHTML = html;
  });
}
function filterKeys(){
  var q=(document.getElementById('keySearch').value||'').toLowerCase().trim();
  var keys=_allKeys;
  if(q){
    keys = _allKeys.filter(function(k){
      var name = (k.name||'').toLowerCase();
      var pv = (k.key_preview||'').toLowerCase();
      if(name.includes(q) || pv.includes(q)) return true;
      // If user pasted full or partial key, match by last 4 chars (preview format: sk-scarl...XXXX)
      if(q.length >= 4){
        var tail = q.substring(q.length-4);
        if(pv.endsWith(tail)) return true;
      }
      return false;
    });
  }
  var h='<table><tr><th style="width:32px"><input type="checkbox" id="selAll" onclick="toggleAll(this)"></th><th>Ключ</th><th>Имя</th><th>RPM</th><th>Токены</th><th>Использовано</th><th>%</th><th>Тренд 24ч</th><th>Last seen</th><th>Статус</th><th>Запросов</th><th>Действия</th></tr>';
  keys.forEach(k=>{
    var L=effLimit(k), U=effUsed(k);
    var p=L>0?Math.round(U/L*100):0;
    var c=p>90?'var(--err)':p>70?'var(--warn)':'var(--ok)';
    var pv=esc(k.key_preview);
    var checked=_selectedPreviews[k.key_preview]?'checked':'';
    var spark = _sparkData[k.key_preview];
    var ls = _lastSeen[k.key_preview];
    var sparkColor = p>90?'#ff6b6b':p>70?'#ffd166':'#9ab8ff';
    h+='<tr><td><input type="checkbox" class="rowChk" data-pv="'+pv+'" '+checked+' onclick="onRowCheck(this)"></td>';
    h+='<td style="font-family:JetBrains Mono;font-size:.75rem;cursor:pointer" onclick="navigator.clipboard.writeText(this.textContent)">'+pv+'</td>';
    h+='<td><a href="#" onclick="showKey(\''+pv+'\');return false" style="color:#fff;font-weight:600">'+esc(k.name)+'</a></td>';
    h+='<td>'+k.rpm_limit+'</td>';
    h+='<td>'+(L>0?fmtT(L):'∞')+'</td>';
    h+='<td>'+fmtT(U)+'</td>';
    h+='<td>'+(L>0?'<div class="bar"><div class="f" style="width:'+Math.min(100,p)+'%;background:'+c+'"></div></div> '+p+'%':'—')+'</td>';
    h+='<td>'+sparkSvg(spark, sparkColor)+'</td>';
    h+='<td style="font-size:.75rem;color:var(--tm)">'+fmtAgo(ls)+'</td>';
    h+='<td><span class="badge '+(k.enabled?'ok':'off')+'">'+(k.enabled?'Активен':'Откл')+'</span></td>';
    h+='<td>'+fmt(k.stats?k.stats.requests:0)+'</td>';
    h+='<td style="white-space:nowrap"><button onclick="kAct(\''+pv+'\',\'toggle\','+!k.enabled+')" style="font-size:.75rem">'+(k.enabled?'Откл':'Вкл')+'</button> ';
    h+='<button onclick="kAct(\''+pv+'\',\'copy\')" style="font-size:.75rem">Копировать</button> ';
    h+='<button onclick="kAct(\''+pv+'\',\'duplicate\')" style="font-size:.75rem">Дубль</button> ';
    h+='<button onclick="kAct(\''+pv+'\',\'delete\')" style="font-size:.75rem;color:var(--err)">Удалить</button></td></tr>';
  });
  h+='</table>';document.getElementById('keysTable').innerHTML=h;
  updateSelCount();
}

function toast(msg){var t=document.getElementById('toast');t.textContent=msg;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2000);}
function copyText(text){navigator.clipboard.writeText(text).then(()=>toast('✅ Скопировано!'));}
function kAct(pv,act,ex){
  vps('GET','/admin/key-resolve/'+encodeURIComponent(pv)).then(d=>{
    if(!d||!d.full_key){alert('Ключ не найден');return;}var f=d.full_key;
    if(act==='toggle')vps('PUT','/admin/keys',{key:f,enabled:ex}).then(()=>{loadKeys();toast(ex?'Ключ включен':'Ключ выключен');});
    else if(act==='copy')copyText(f);
    else if(act==='duplicate'){
      var nm=prompt('Имя для дубликата:','copy-of-'+pv);
      if(!nm) return;
      vps('POST','/admin/keys/duplicate',{source_key:f,name:nm}).then(d2=>{
        if(d2 && d2.status==='created'){
          // Sync duplicate to MySQL as orphan
          fetch('api/admin.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'sync_kiro_key',key:d2.key,name:nm,tokens_limit:d2.tokens_limit||0}),credentials:'same-origin'}).catch(function(){});
          // Modal first (outside keysTable), then refresh table
          showNewKeyModal(d2.key, nm, d2.tokens_limit||0);
          loadKeys();
        } else alert((d2&&d2.detail)||'Ошибка');
      });
    }
    else if(act==='delete'){if(!confirm('Удалить ключ '+pv+'?'))return;vps('DELETE','/admin/keys/'+encodeURIComponent(f)).then(()=>{loadKeys();toast('Ключ удален');});}
  });
}

// ============ Bulk / Import / Export ============
var _selectedPreviews = {};
function onRowCheck(cb){
  var pv = cb.getAttribute('data-pv');
  if(cb.checked) _selectedPreviews[pv] = true;
  else delete _selectedPreviews[pv];
  updateSelCount();
}
function toggleAll(cb){
  document.querySelectorAll('.rowChk').forEach(function(c){
    c.checked = cb.checked;
    var pv = c.getAttribute('data-pv');
    if(cb.checked) _selectedPreviews[pv] = true;
    else delete _selectedPreviews[pv];
  });
  updateSelCount();
}
function clearSelection(){
  _selectedPreviews = {};
  document.querySelectorAll('.rowChk,#selAll').forEach(function(c){c.checked=false;});
  updateSelCount();
}
function updateSelCount(){
  var n = Object.keys(_selectedPreviews).length;
  document.getElementById('selCount').textContent = n;
  document.getElementById('bulkActionBar').style.display = n > 0 ? 'block' : 'none';
}
function getSelectedFullKeys(){
  // need to resolve previews → full keys via admin endpoint
  var pvs = Object.keys(_selectedPreviews);
  return Promise.all(pvs.map(function(pv){
    return vps('GET','/admin/key-resolve/'+encodeURIComponent(pv)).then(function(d){ return d&&d.full_key||null; });
  })).then(function(arr){ return arr.filter(Boolean); });
}
function bulkAction(action){
  var n = Object.keys(_selectedPreviews).length;
  if(!n){ alert('Ничего не выбрано'); return; }
  var msg = {enable:'Включить',disable:'Выключить',reset_used:'Сбросить расход',delete:'УДАЛИТЬ'}[action] || action;
  if(!confirm(msg+' '+n+' ключ(а/ей)?')) return;
  getSelectedFullKeys().then(function(keys){
    vps('POST','/admin/keys/bulk-action',{keys:keys,action:action}).then(function(d){
      if(d && d.status==='ok'){
        toast('Готово: '+d.affected+' ключ(а/ей)');
        clearSelection();
        loadKeys();
      } else alert((d&&d.detail)||'Ошибка');
    });
  });
}
function bulkSetCredits(){
  var v = prompt('Новый лимит токенов (0 = безлимит):','10000000');
  if(v===null) return;
  v = parseInt(v); if(isNaN(v)||v<0){ alert('Неверное число'); return; }
  getSelectedFullKeys().then(function(keys){
    vps('POST','/admin/keys/bulk-action',{keys:keys,action:'set_tokens',value:v}).then(function(d){
      if(d&&d.status==='ok'){ toast('Изменено: '+d.affected); clearSelection(); loadKeys(); }
      else alert((d&&d.detail)||'Ошибка');
    });
  });
}
function bulkSetRpm(){
  var v = prompt('Новый RPM:','45');
  if(v===null) return;
  v = parseInt(v); if(isNaN(v)||v<1){ alert('Неверное число'); return; }
  getSelectedFullKeys().then(function(keys){
    vps('POST','/admin/keys/bulk-action',{keys:keys,action:'set_rpm',value:v}).then(function(d){
      if(d&&d.status==='ok'){ toast('Изменено: '+d.affected); clearSelection(); loadKeys(); }
      else alert((d&&d.detail)||'Ошибка');
    });
  });
}
function bulkTopup(){
  var v = prompt('Сколько токенов добавить к каждому ключу?','1000000');
  if(v===null) return;
  v = parseInt(v); if(isNaN(v)||v<=0){ alert('Неверное число'); return; }
  getSelectedFullKeys().then(function(keys){
    Promise.all(keys.map(function(k){
      return vps('POST','/admin/keys/topup-tokens',{key:k,amount:v}).then(function(d){return d&&d.status==='ok';});
    })).then(function(arr){
      var ok = arr.filter(Boolean).length;
      toast('Топ-ап: '+ok+' / '+arr.length);
      clearSelection(); loadKeys();
    });
  });
}

// ============ Шаблоны ключей ============
var KEY_TEMPLATES = [
  {label:'Trial — 1M / 45rpm',     tokens:1000000,    rpm:45},
  {label:'10M — 10M / 45rpm',      tokens:10000000,   rpm:45},
  {label:'25M — 25M / 45rpm',      tokens:25000000,   rpm:45},
  {label:'50M — 50M / 45rpm',      tokens:50000000,   rpm:45},
  {label:'100M — 100M / 45rpm',    tokens:100000000,  rpm:45},
  {label:'250M — 250M / 45rpm',    tokens:250000000,  rpm:45},
  {label:'500M — 500M / 45rpm',    tokens:500000000,  rpm:45},
];
function initTemplates(){
  ['nTpl','bTpl'].forEach(function(id){
    var sel = document.getElementById(id);
    if(!sel) return;
    KEY_TEMPLATES.forEach(function(t,i){
      var o = document.createElement('option');
      o.value = i; o.textContent = t.label;
      sel.appendChild(o);
    });
  });
}
function applyTemplate(prefix){
  var sel = document.getElementById(prefix+'Tpl');
  var idx = sel.value;
  if(idx===''||idx===null) return;
  var t = KEY_TEMPLATES[parseInt(idx)];
  if(!t) return;
  if(prefix==='n'){
    document.getElementById('nr').value = t.rpm;
    document.getElementById('nc').value = t.tokens;
  } else if(prefix==='b'){
    document.getElementById('bRpm').value = t.rpm;
    document.getElementById('bCred').value = t.tokens;
  }
  sel.value = '';
}

function bulkPanel(){
  var el = document.getElementById('bulkRow');
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
function bulkCreate(){
  var n = parseInt(document.getElementById('bCount').value)||0;
  var pref = document.getElementById('bPrefix').value.trim() || 'key-';
  var rpm = parseInt(document.getElementById('bRpm').value)||45;
  var cred = parseInt(document.getElementById('bCred').value)||0;
  if(n<1||n>200){ alert('Кол-во: 1..200'); return; }
  var items = [];
  for(var i=1;i<=n;i++) items.push({name: pref+i, rpm: rpm, tokens_limit: cred});
  vps('POST','/admin/keys/bulk',{items:items}).then(function(d){
    if(!d || d.status!=='ok'){ alert((d&&d.detail)||'Ошибка'); return; }
    toast('Создано: '+d.count);
    // Sync each created key to MySQL as orphan
    (d.created||[]).forEach(function(ck){
      fetch('api/admin.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'sync_kiro_key',key:ck.key||ck.api_key,name:ck.name||'key',tokens_limit:ck.tokens_limit||0}),credentials:'same-origin'}).catch(function(){});
    });
    // download JSON with full keys
    var blob = new Blob([JSON.stringify(d.created,null,2)],{type:'application/json'});
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url; a.download = 'keys-'+pref+Date.now()+'.json'; a.click();
    URL.revokeObjectURL(url);
    document.getElementById('bulkRow').style.display = 'none';
    loadKeys();
  });
}

function exportKeys(fmt){
  if(!_allKeys||!_allKeys.length){ alert('Нет ключей'); return; }
  var rows = _allKeys.map(function(k){
    return {
      key_preview: k.key_preview,
      name: k.name,
      rpm_limit: k.rpm_limit,
      tokens_limit: k.tokens_limit||0,
      tokens_used: k.tokens_used||0,
      credits_limit: k.credits_limit||0,
      credits_used: k.credits_used||0,
      enabled: k.enabled,
      requests: k.stats?k.stats.requests:0,
      tokens_prompt: k.stats?k.stats.tokens_prompt:0,
      tokens_completion: k.stats?k.stats.tokens_completion:0
    };
  });
  var content, mime, ext;
  if(fmt==='csv'){
    var headers = Object.keys(rows[0]);
    var esc2 = function(s){ s=String(s==null?'':s); return /[",\n]/.test(s)?'"'+s.replace(/"/g,'""')+'"':s; };
    var lines = [headers.join(',')];
    rows.forEach(function(r){ lines.push(headers.map(function(h){return esc2(r[h]);}).join(',')); });
    content = lines.join('\n'); mime='text/csv'; ext='csv';
  } else {
    content = JSON.stringify(rows,null,2); mime='application/json'; ext='json';
  }
  var blob = new Blob([content],{type:mime});
  var url = URL.createObjectURL(blob);
  var a = document.createElement('a');
  a.href = url; a.download = 'kiro-keys-'+Date.now()+'.'+ext; a.click();
  URL.revokeObjectURL(url);
}

function importCsvBtn(){ document.getElementById('csvFile').click(); }
function onCsvSelected(ev){
  var file = ev.target.files[0]; if(!file) return;
  var reader = new FileReader();
  reader.onload = function(e){
    var text = e.target.result;
    var lines = text.split(/\r?\n/).filter(function(l){return l.trim();});
    if(lines.length<2){ alert('Пустой CSV'); return; }
    var headers = lines[0].split(',').map(function(h){return h.trim().toLowerCase();});
    var nameIdx = headers.indexOf('name');
    var tokIdx  = headers.indexOf('tokens');
    var tokIdx2 = headers.indexOf('tokens_limit');
    var credIdx = headers.indexOf('credits');
    var credIdx2 = headers.indexOf('credits_limit');
    var rpmIdx = headers.indexOf('rpm');
    var keyIdx = headers.indexOf('key');
    if(nameIdx<0){ alert('CSV должен содержать колонку "name"'); return; }
    var items = [];
    for(var i=1;i<lines.length;i++){
      var cols = lines[i].split(',');
      var item = { name: (cols[nameIdx]||'').trim() };
      if(!item.name) continue;
      // Prefer explicit tokens columns; fall back to legacy credits → tokens_limit.
      if(tokIdx>=0)  item.tokens_limit = parseInt(cols[tokIdx])||0;
      else if(tokIdx2>=0) item.tokens_limit = parseInt(cols[tokIdx2])||0;
      else if(credIdx>=0)  item.tokens_limit = parseInt(cols[credIdx])||0;
      else if(credIdx2>=0) item.tokens_limit = parseInt(cols[credIdx2])||0;
      if(rpmIdx>=0) item.rpm = parseInt(cols[rpmIdx])||45;
      if(keyIdx>=0 && cols[keyIdx] && cols[keyIdx].trim()) item.key = cols[keyIdx].trim();
      items.push(item);
    }
    if(!items.length){ alert('Нет валидных строк'); return; }
    if(!confirm('Создать '+items.length+' ключ(а/ей)?')) { ev.target.value=''; return; }
    vps('POST','/admin/keys/bulk',{items:items}).then(function(d){
      if(!d||d.status!=='ok'){ alert((d&&d.detail)||'Ошибка'); return; }
      toast('Создано: '+d.count+(d.errors&&d.errors.length?' (ошибок: '+d.errors.length+')':''));
      var blob = new Blob([JSON.stringify(d.created,null,2)],{type:'application/json'});
      var url = URL.createObjectURL(blob);
      var a = document.createElement('a'); a.href=url; a.download='imported-keys-'+Date.now()+'.json'; a.click();
      URL.revokeObjectURL(url);
      loadKeys();
    });
    ev.target.value='';
  };
  reader.readAsText(file);
}

function showKey(pv){
  document.getElementById('keyModal').classList.add('open');
  document.getElementById('mTitle').textContent=pv;
  document.getElementById('mInfo').innerHTML='Загрузка...';
  document.getElementById('mLogs').innerHTML='Загрузка...';
  var ki=_allKeys.find(k=>k.key_preview===pv);
  if(ki){
    var modeStr = ki.hard_limit===false ? '<span style="color:var(--warn)">Soft (только учёт)</span>' : '<span style="color:var(--ok)">Hard (блокировка)</span>';
    var allowedStr = (ki.allowed_models && ki.allowed_models.length) ? ki.allowed_models.join(', ') : '<span style="color:var(--tm)">все</span>';
    var L=effLimit(ki), U=effUsed(ki);
    var limitMode = (ki.tokens_limit && ki.tokens_limit>0) ? 'tokens_limit'
                    : (ki.credits_limit && ki.credits_limit>0 ? 'credits_limit (legacy)' : '∞');
    var h='<div class="g">'+sc('Имя',esc(ki.name))+sc('RPM',ki.rpm_limit)+sc('Лимит',L>0?fmtT(L):'∞')+
      sc('Использовано',fmtT(U))+sc('Осталось',L>0?fmtT(L-U):'∞')+
      sc('Тип лимита',limitMode)+
      sc('Запросов',fmt(ki.stats?ki.stats.requests:0))+sc('Входящих токенов',fmtT(ki.stats?ki.stats.tokens_prompt:0))+sc('Исходящих токенов',fmtT(ki.stats?ki.stats.tokens_completion:0))+'</div>';
    h+='<div class="sc" style="margin-top:12px"><div class="sl">Лимит-режим</div><div class="sv">'+modeStr+'</div></div>';
    h+='<div class="sc" style="margin-top:8px"><div class="sl">Разрешённые модели</div><div class="sv" style="font-size:.85rem">'+allowedStr+'</div></div>';
    h+='<div id="mAnalytics" style="margin-top:12px"></div>';
    h+='<div class="row" style="margin-top:12px;gap:8px;flex-wrap:wrap">';
    h+='<button onclick="kAct(\''+esc(pv)+'\',\'copy\')">Копировать ключ</button>';
    h+='<button onclick="showCurl(\''+esc(pv)+'\')">Показать curl</button>';
    h+='<button onclick="topupKey(\''+esc(pv)+'\')" style="background:rgba(115,240,188,.1);color:var(--ok);border-color:rgba(115,240,188,.2)">+Топ-ап</button>';
    h+='<button onclick="toggleHardLimit(\''+esc(pv)+'\','+(!ki.hard_limit?'true':'false')+')">Режим: '+(ki.hard_limit===false?'Hard':'Soft')+'</button>';
    h+='<button onclick="editAllowedModels(\''+esc(pv)+'\')">Whitelist моделей</button>';
    h+='<button onclick="kAct(\''+esc(pv)+'\',\'delete\');document.getElementById(\'keyModal\').classList.remove(\'open\')" style="color:var(--err)">Удалить ключ</button>';
    h+='</div>';
    document.getElementById('mInfo').innerHTML=h;
    loadKeyAnalytics(pv, ki);
  }
  vps('GET','/admin/logs/key/'+encodeURIComponent(pv)+'?limit=50').then(d=>{
    if(!d||!d.logs||!d.logs.length){document.getElementById('mLogs').innerHTML='<span style="color:var(--tm)">Запросов пока нет</span>';return;}
    var h='<table><tr><th>Время</th><th>Модель</th><th>Промпт</th><th>Вх</th><th>Исх</th><th>Всего</th><th>мс</th><th>Аккаунт</th><th>Статус</th></tr>';
    d.logs.forEach(l=>{
      var prompt = l.prompt_preview || '';
      var promptShort = prompt.length > 60 ? prompt.substring(0, 60) + '…' : prompt;
      h+='<tr><td style="color:var(--tm)">'+(l.ts?l.ts.substring(11,19):'')+'</td>';
      h+='<td>'+esc(l.model)+'</td>';
      h+='<td class="pcell" title="'+esc(prompt)+'" style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+esc(promptShort)+'</td>';
      h+='<td>'+fmt(l.prompt_tokens)+'</td><td>'+fmt(l.completion_tokens)+'</td>';
      h+='<td style="color:#fff;font-weight:700">'+fmt(l.total_tokens||0)+'</td>';
      h+='<td>'+(l.duration_ms||0)+'</td>';
      h+='<td style="color:var(--tm)">'+esc(l.account||'')+'</td>';
      h+='<td class="'+(l.status==='ok'?'':'')+'"><span class="badge '+(l.status==='ok'?'ok':'off')+'">'+esc(l.status||'')+'</span></td></tr>';
    });
    h+='</table><div style="color:var(--tm);font-size:.75rem;margin-top:8px">'+d.logs.length+' из '+d.total+' запросов</div>';
    document.getElementById('mLogs').innerHTML=h;
  });
}

function loadKeyAnalytics(pv, ki){
  vps('GET','/admin/keys/'+encodeURIComponent(pv)+'/stats?hours=168').then(function(d){
    if(!d) return;
    var box = document.getElementById('mAnalytics');
    if(!box) return;
    var html = '';

    // by-model breakdown
    if(d.by_model && d.by_model.length){
      var total = d.by_model.reduce(function(s,m){return s+m.total_tokens;}, 0) || 1;
      html += '<div class="sc"><div class="sl">Расход по моделям (за неделю)</div>';
      d.by_model.forEach(function(m){
        var pct = Math.round(m.total_tokens/total*100);
        html += '<div style="display:flex;align-items:center;gap:8px;margin-top:6px">';
        html += '<div style="min-width:180px;font-family:JetBrains Mono;font-size:.78rem">'+esc(m.model)+'</div>';
        html += '<div class="bar" style="flex:1"><div class="f" style="width:'+pct+'%;background:#9ab8ff"></div></div>';
        html += '<div style="min-width:90px;font-size:.78rem;color:var(--tm);text-align:right">'+fmtT(m.total_tokens)+' • '+pct+'%</div>';
        html += '</div>';
      });
      html += '</div>';
    }

    // sparkline 24h
    if(d.sparkline && d.sparkline.length){
      html += '<div class="sc" style="margin-top:8px"><div class="sl">Расход почасово (24ч)</div>';
      var max = Math.max.apply(null, d.sparkline) || 1;
      var w = 480, h = 60;
      var step = w / (d.sparkline.length - 1 || 1);
      var pts = d.sparkline.map(function(v,i){
        var y = h - (v/max)*(h-4) - 2;
        return (i*step).toFixed(1) + ',' + y.toFixed(1);
      }).join(' ');
      html += '<svg viewBox="0 0 '+w+' '+h+'" style="width:100%;height:60px;margin-top:4px"><polyline fill="none" stroke="#9ab8ff" stroke-width="2" points="'+pts+'"/></svg>';
      html += '<div style="color:var(--tm);font-size:.75rem">Пик: '+fmtT(max)+' токенов/час</div>';
      html += '</div>';
    }

    // forecast (depletion) — prefer tokens_limit, fall back to legacy credits_limit
    var _fcLim = effLimit(ki);
    if(ki && _fcLim > 0 && ki.hard_limit !== false){
      var remaining = _fcLim - effUsed(ki);
      // compute avg per hour from sparkline
      var nonZero = (d.sparkline || []).filter(function(v){return v>0;});
      if(nonZero.length >= 3 && remaining > 0){
        var avgPerHour = nonZero.reduce(function(s,v){return s+v;}, 0) / nonZero.length;
        var hoursLeft = remaining / avgPerHour;
        var when;
        if(hoursLeft < 1) when = Math.round(hoursLeft*60) + ' мин';
        else if(hoursLeft < 24) when = Math.round(hoursLeft) + ' ч';
        else when = Math.round(hoursLeft/24) + ' дн';
        var color = hoursLeft < 24 ? 'var(--err)' : hoursLeft < 168 ? 'var(--warn)' : 'var(--ok)';
        html += '<div class="sc" style="margin-top:8px;border-color:'+color+'"><div class="sl">Прогноз исчерпания</div>';
        html += '<div class="sv">При текущем темпе (~'+fmtT(Math.round(avgPerHour))+' токенов/час) ключ закончится через <b style="color:'+color+'">'+when+'</b></div>';
        html += '</div>';
      }
    }

    box.innerHTML = html;
  });
}

function showCurl(pv){
  vps('GET','/admin/key-resolve/'+encodeURIComponent(pv)).then(function(r){
    if(!r || !r.full_key){ alert('Ключ не найден'); return; }
    var k = r.full_key;
    var examples = {
      'cURL': 'curl https://api.scarlex.ru/v1/chat/completions \\\n  -H "Authorization: Bearer '+k+'" \\\n  -H "Content-Type: application/json" \\\n  -d \'{"model":"claude-sonnet-4","messages":[{"role":"user","content":"Hello!"}]}\'',
      'Python': 'from openai import OpenAI\n\nclient = OpenAI(\n    base_url="https://api.scarlex.ru/v1",\n    api_key="'+k+'"\n)\nresp = client.chat.completions.create(\n    model="claude-sonnet-4",\n    messages=[{"role":"user","content":"Hello!"}]\n)\nprint(resp.choices[0].message.content)',
      'JavaScript': "import OpenAI from 'openai';\n\nconst client = new OpenAI({\n  baseURL: 'https://api.scarlex.ru/v1',\n  apiKey: '"+k+"',\n});\nconst resp = await client.chat.completions.create({\n  model: 'claude-sonnet-4',\n  messages: [{ role: 'user', content: 'Hello!' }],\n});\nconsole.log(resp.choices[0].message.content);",
    };
    var html = '<h3 style="color:#fff;margin:0 0 12px">Примеры для ключа '+esc(pv)+'</h3>';
    Object.keys(examples).forEach(function(name){
      var id = 'ex-'+name.replace(/[^a-z]/gi,'');
      html += '<div style="margin-bottom:12px"><div class="row" style="margin-bottom:4px"><b style="color:#fff">'+name+'</b><div style="flex:1"></div><button onclick="copyText(document.getElementById(\''+id+'\').textContent)" style="font-size:.75rem">Копировать</button></div>';
      html += '<pre id="'+id+'" style="white-space:pre-wrap;word-break:break-all;max-height:200px;overflow:auto">'+esc(examples[name])+'</pre></div>';
    });
    var box = document.getElementById('mInfo');
    box.insertAdjacentHTML('afterbegin', '<div id="curlBox" class="sc" style="margin-bottom:12px;border-color:var(--ok)">'+html+'<button onclick="document.getElementById(\'curlBox\').remove()" style="font-size:.75rem">Закрыть</button></div>');
  });
}

function topupKey(pv){
  var v = prompt('Сколько токенов добавить?','1000000');
  if(v===null) return;
  v = parseInt(v); if(isNaN(v)||v<=0){ alert('Неверное число'); return; }
  vps('GET','/admin/key-resolve/'+encodeURIComponent(pv)).then(function(r){
    if(!r||!r.full_key){ alert('Ключ не найден'); return; }
    vps('POST','/admin/keys/topup',{key:r.full_key,amount:v}).then(function(d){
      if(d&&d.status==='ok'){
        toast('Топ-ап выполнен');
        loadKeys();
        // refresh modal
        setTimeout(function(){ showKey(pv); }, 300);
      } else alert((d&&d.detail)||'Ошибка');
    });
  });
}
function toggleHardLimit(pv, newVal){
  vps('GET','/admin/key-resolve/'+encodeURIComponent(pv)).then(function(r){
    if(!r||!r.full_key){ alert('Ключ не найден'); return; }
    vps('PUT','/admin/keys',{key:r.full_key,hard_limit:newVal}).then(function(d){
      if(d&&d.status==='updated'){
        toast('Режим: '+(newVal?'Hard (блокировка)':'Soft (только учёт)'));
        loadKeys();
        setTimeout(function(){ showKey(pv); }, 300);
      } else alert((d&&d.detail)||'Ошибка');
    });
  });
}
var AVAILABLE_MODELS = [
  'claude-sonnet-4',
  'claude-sonnet-4-thinking',
  'claude-opus-4.6',
  'claude-opus-4.7',
  'claude-haiku-3.5',
];
var _wlContext = { pv: null, fullKey: null };

function editAllowedModels(pv){
  var ki = _allKeys.find(function(k){return k.key_preview===pv;});
  var current = (ki && ki.allowed_models) ? ki.allowed_models.slice() : [];
  vps('GET','/admin/key-resolve/'+encodeURIComponent(pv)).then(function(r){
    if(!r||!r.full_key){ alert('Ключ не найден'); return; }
    _wlContext.pv = pv;
    _wlContext.fullKey = r.full_key;
    // Render checkbox list
    var html = '';
    AVAILABLE_MODELS.forEach(function(m){
      var checked = current.indexOf(m) >= 0 ? 'checked' : '';
      html += '<label style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:8px;margin-bottom:6px;cursor:pointer;transition:background .15s" onmouseover="this.style.background=\'rgba(255,255,255,.06)\'" onmouseout="this.style.background=\'rgba(255,255,255,.03)\'">';
      html += '<input type="checkbox" class="wlChk" value="'+esc(m)+'" '+checked+' style="margin:0;width:16px;height:16px">';
      html += '<span style="color:#fff;font-family:JetBrains Mono;font-size:.85rem">'+esc(m)+'</span>';
      html += '</label>';
    });
    document.getElementById('wlList').innerHTML = html;
    document.getElementById('wlModal').classList.add('open');
  });
}

function wlSelectAll(state){
  document.querySelectorAll('#wlList .wlChk').forEach(function(c){ c.checked = state; });
}

function wlSave(){
  var list = [];
  document.querySelectorAll('#wlList .wlChk:checked').forEach(function(c){ list.push(c.value); });
  vps('PUT','/admin/keys',{key:_wlContext.fullKey, allowed_models:list}).then(function(d){
    if(d && d.status==='updated'){
      toast(list.length ? 'Whitelist: '+list.length+' модел(ей)' : 'Whitelist снят (все разрешены)');
      document.getElementById('wlModal').classList.remove('open');
      var pv = _wlContext.pv;
      loadKeys();
      setTimeout(function(){ if(document.getElementById('keyModal').classList.contains('open')) showKey(pv); }, 300);
    } else alert((d && d.detail) || 'Ошибка');
  });
}

function loadAccounts(){
  vps('GET','/admin/accounts').then(d=>{
    if(!d||!d.accounts)return;
    var alive  = d.accounts.filter(a=>!a.is_dead && !a.frozen).length;
    var frozen = d.accounts.filter(a=>!!a.frozen).length;
    document.getElementById('accSum').textContent =
      alive + '/' + d.accounts.length + ' активных' + (frozen ? ' · ' + frozen + ' заморожен' : '');
    var h='<table><tr><th>#</th><th>ID</th><th>Email</th><th>Статус</th><th>Кредиты</th><th>Запросов</th><th>Токенов</th><th>Прокси</th><th></th></tr>';
    d.accounts.forEach((a,i)=>{
      var pill;
      if (a.is_dead)     pill = '<span class="badge off">Мёртв</span>';
      else if (a.frozen) pill = '<span class="badge" style="background:#3a2a1a;color:#ffce5a;border-color:#5a3a1a">Заморожен</span>';
      else               pill = '<span class="badge ok">Жив</span>';
      var btn;
      if (a.frozen) {
        btn = '<button onclick="freezeAccount(\''+esc(a.label)+'\',false)" '
            + 'style="background:#1a2a1a;border:1px solid #2a5a3a;color:#7cffb6;padding:3px 9px;border-radius:6px;font-size:11px;cursor:pointer">Разморозить</button>';
      } else {
        btn = '<button onclick="freezeAccount(\''+esc(a.label)+'\',true)" '
            + 'style="background:#2a1a1a;border:1px solid #5a2a2a;color:#ffce5a;padding:3px 9px;border-radius:6px;font-size:11px;cursor:pointer"'
            + (a.is_dead?' disabled':'')+'>Заморозить</button>';
      }
      h+='<tr'+(a.frozen?' style="opacity:.65"':'')+'><td style="color:var(--tm)">'+(i+1)+'</td>';
      h+='<td style="font-family:JetBrains Mono;font-size:.75rem">'+esc(a.label)+'</td>';
      h+='<td>'+esc(a.email)+'</td>';
      h+='<td>'+pill+'</td>';
      h+='<td>'+a.credits_used+'/'+a.credits_limit+'</td>';
      h+='<td>'+a.requests_total+'</td>';
      h+='<td>'+fmtT((a.tokens_prompt||0)+(a.tokens_completion||0))+'</td>';
      h+='<td style="color:var(--tm)">'+(a.proxy||'прямое')+'</td>';
      h+='<td>'+btn+'</td></tr>';
    });
    h+='</table>';document.getElementById('accTable').innerHTML=h;
  });
}

function freezeAccount(accountId, freeze){
  if (freeze && !confirm('Заморозить аккаунт ' + accountId + '?\nЗапросы перестанут идти к нему до разморозки.')) return;
  var path = '/admin/accounts/' + encodeURIComponent(accountId) + (freeze ? '/freeze' : '/unfreeze');
  vps('POST', path, {}).then(function(d){
    if (d && (d.status === 'frozen' || d.status === 'unfrozen')) {
      loadAccounts();
    } else {
      alert('Ошибка: ' + ((d && d.detail) || 'unknown'));
    }
  });
}

var _logsTimer = null;
function logFilterDebounce(){
  if(_logsTimer) clearTimeout(_logsTimer);
  _logsTimer = setTimeout(loadLogs, 350);
}

function buildLogQuery(){
  var p = ['limit='+(document.getElementById('logLimit').value||100)];
  var m = document.getElementById('logModel').value;
  var s = document.getElementById('logStatus').value;
  var k = document.getElementById('logKey').value;
  var since = document.getElementById('logSince').value;
  var q = (document.getElementById('logQ').value||'').trim();
  if(m) p.push('model='+encodeURIComponent(m));
  if(s) p.push('status='+encodeURIComponent(s));
  if(k) p.push('key='+encodeURIComponent(k));
  if(since) p.push('since='+(Date.now()/1000 - parseInt(since)*3600));
  if(q) p.push('q='+encodeURIComponent(q));
  return '?' + p.join('&');
}

function fillLogKeyOptions(){
  var sel = document.getElementById('logKey');
  if(!sel) return;
  var prev = sel.value;
  // Keep first "Все ключи" option
  while(sel.options.length > 1) sel.remove(1);
  (_allKeys||[]).forEach(function(k){
    var o = document.createElement('option');
    o.value = k.key_preview; o.textContent = k.name + ' (' + k.key_preview + ')';
    sel.appendChild(o);
  });
  sel.value = prev;
}

function loadLogs(){
  fillLogKeyOptions();
  var qs = buildLogQuery();
  vps('GET','/admin/logs'+qs).then(d=>{
    if(!d||!d.logs){ document.getElementById('logBox').innerHTML='<span style="color:var(--err)">Ошибка загрузки</span>'; return; }
    document.getElementById('logCnt').textContent=d.logs.length+' из '+d.total+' записей';
    if(!d.logs.length){ document.getElementById('logBox').innerHTML='<span style="color:var(--tm)">Нет записей по фильтрам</span>'; return; }
    var h='<table><tr><th>Время</th><th>Модель</th><th>Ключ</th><th>Аккаунт</th><th>Промпт</th><th>Вх</th><th>Исх</th><th>Всего</th><th>мс</th><th>Статус</th></tr>';
    d.logs.forEach(l=>{
      var prompt = l.prompt_preview || '';
      var promptShort = prompt.length > 80 ? prompt.substring(0, 80) + '…' : prompt;
      var ts = l.ts ? new Date(l.ts) : null;
      var tsStr = ts ? (ts.getMonth()+1)+'-'+String(ts.getDate()).padStart(2,'0')+' '+String(ts.getHours()).padStart(2,'0')+':'+String(ts.getMinutes()).padStart(2,'0')+':'+String(ts.getSeconds()).padStart(2,'0') : '';
      h+='<tr><td style="color:var(--tm);font-size:.75rem;white-space:nowrap">'+tsStr+'</td>';
      h+='<td style="font-size:.78rem">'+esc(l.model||'')+'</td>';
      h+='<td><a href="#" onclick="showKey(\''+esc(l.key||'')+'\');return false" style="color:var(--ts);font-weight:600">'+esc(l.key_name||'')+'</a></td>';
      h+='<td style="color:var(--tm);font-size:.75rem">'+esc(l.account||'')+'</td>';
      h+='<td title="'+esc(prompt)+'" style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.78rem;cursor:help">'+esc(promptShort)+'</td>';
      h+='<td>'+fmt(l.prompt_tokens)+'</td><td>'+fmt(l.completion_tokens)+'</td>';
      h+='<td style="color:#fff;font-weight:600">'+fmt(l.total_tokens||0)+'</td>';
      h+='<td>'+(l.duration_ms||0)+'</td>';
      h+='<td><span class="badge '+(l.status==='ok'?'ok':'off')+'">'+esc(l.status||'')+'</span></td></tr>';
    });
    h+='</table>';document.getElementById('logBox').innerHTML=h;
  });
}

function genKey(){document.getElementById('newKeyRow').style.display='flex';var h='';for(var i=0;i<32;i++)h+='0123456789abcdef'[Math.random()*16|0];document.getElementById('nk').value='sk-scarlex-'+h;document.getElementById('nn').focus();}
function addKey(){document.getElementById('newKeyRow').style.display='flex';document.getElementById('nn').focus();}
function fmtTokensCompact(n){n=+n||0;if(n>=1e9)return (n/1e9).toFixed(1)+'B';if(n>=1e6)return (n/1e6).toFixed(0)+'M';if(n>=1e3)return (n/1e3).toFixed(0)+'K';return n||'∞';}
function showNewKeyModal(fullKey, name, tokensLimit){
  document.getElementById('nkmKey').value = fullKey || '';
  document.getElementById('nkmName').textContent = name || '—';
  document.getElementById('nkmTokens').textContent = tokensLimit ? fmtTokensCompact(tokensLimit) : '∞ (безлимит)';
  document.getElementById('newKeyModal').classList.add('open');
  setTimeout(function(){
    var inp = document.getElementById('nkmKey');
    inp.focus(); inp.select();
  }, 80);
}
function nkmCopy(){
  var inp = document.getElementById('nkmKey');
  inp.select();
  navigator.clipboard.writeText(inp.value).then(function(){toast('✅ Скопировано');}).catch(function(){
    try{document.execCommand('copy');toast('✅ Скопировано');}catch(e){toast('❌ Не удалось');}
  });
}

function submitKey(){
  var k=document.getElementById('nk').value.trim(),n=document.getElementById('nn').value.trim(),r=parseInt(document.getElementById('nr').value)||45,c=parseInt(document.getElementById('nc').value)||0;
  if(!k||!n){alert('Заполните ключ и имя');return;}
  vps('POST','/admin/keys',{key:k,name:n,rpm:r,tokens_limit:c,credits_limit:0,hard_limit:true}).then(d=>{
    if(d&&d.status==='created'){
      // Sync to MySQL as orphan (user_id=NULL) so users can claim via dashboard
      fetch('api/admin.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'sync_kiro_key',key:k,name:n,tokens_limit:c}),credentials:'same-origin'}).catch(()=>{});
      document.getElementById('newKeyRow').style.display='none';
      document.getElementById('nk').value='';document.getElementById('nn').value='';
      // Show modal FIRST — it's not in keysTable so loadKeys() won't destroy it.
      showNewKeyModal(k, n, c);
      loadKeys();
    } else alert((d&&d.detail)||'Ошибка');
  });
}

function loadGuide(){
  document.getElementById('s-guide').innerHTML=`
<div class="sc">
<h2 style="color:#fff;font-weight:800;margin-bottom:16px">Гайд по интеграции</h2>
<h3 style="color:#fff;margin:16px 0 8px;font-size:.95rem">OpenCode</h3>
<p style="margin-bottom:8px;font-size:.85rem">Создай <code>.opencode/config.json</code>:</p>
<pre>{
  "$schema": "https://opencode.ai/config.json",
  "provider": {
    "Scarlex": {
      "npm": "@ai-sdk/openai-compatible",
      "name": "Scarlex",
      "options": {
        "baseURL": "https://api.scarlex.ru/v1",
        "apiKey": "YOUR_API_KEY"
      },
      "models": {
        "claude-sonnet-4": {
          "name": "Claude Sonnet 4",
          "limit": { "context": 200000, "output": 64000 }
        },
        "claude-opus-4.7": {
          "name": "Claude Opus 4.7",
          "limit": { "context": 200000, "output": 64000 }
        }
      }
    }
  },
  "model": "claude-sonnet-4"
}</pre>

<h3 style="color:#fff;margin:20px 0 8px;font-size:.95rem">cURL</h3>
<pre>curl https://api.scarlex.ru/v1/chat/completions \\
  -H "Authorization: Bearer sk-scarlex-YOUR_KEY" \\
  -H "Content-Type: application/json" \\
  -d '{"model":"claude-sonnet-4","messages":[{"role":"user","content":"Hello!"}]}'</pre>

<h3 style="color:#fff;margin:20px 0 8px;font-size:.95rem">Python</h3>
<pre>from openai import OpenAI

client = OpenAI(
    base_url="https://api.scarlex.ru/v1",
    api_key="sk-scarlex-YOUR_KEY"
)
r = client.chat.completions.create(
    model="claude-sonnet-4",
    messages=[{"role":"user","content":"Hello!"}]
)
print(r.choices[0].message.content)</pre>

<h3 style="color:#fff;margin:20px 0 8px;font-size:.95rem">Модели</h3>
<table><tr><th>Модель</th><th>Тип</th><th>Контекст</th></tr>
<tr><td><code>claude-sonnet-4</code></td><td>Быстрая и умная (по умолчанию)</td><td>200K</td></tr>
<tr><td><code>claude-opus-4.7</code></td><td>Самая мощная</td><td>200K</td></tr>
<tr><td><code>claude-sonnet-4-thinking</code></td><td>С расширенным мышлением</td><td>200K</td></tr></table>
<p style="color:var(--tm);font-size:.78rem;margin-top:8px">Алиасы: gpt-4, gpt-4o, gpt-3.5-turbo → claude-sonnet-4</p>

<h3 style="color:#fff;margin:20px 0 8px;font-size:.95rem">Типы токенов</h3>
<table><tr><th>Токен</th><th>Что это</th></tr>
<tr><td><b>prompt_tokens</b> (Вх)</td><td>Сколько токенов отправил пользователь</td></tr>
<tr><td><b>completion_tokens</b> (Исх)</td><td>Сколько токенов ответил AI</td></tr>
<tr><td><b>total_tokens</b></td><td>Вх + Исх = сколько кредитов сожрал запрос</td></tr></table>

<h2 style="color:#fff;font-weight:800;margin:32px 0 16px;border-top:1px solid rgba(255,255,255,.08);padding-top:24px">Админ API</h2>
<p style="color:var(--tm);font-size:.85rem;margin-bottom:12px">Все эндпоинты требуют заголовок <code>Authorization: Bearer &lt;ADMIN_KEY&gt;</code>. Базовый URL — <code>https://api.scarlex.ru</code>.</p>

<h3 style="color:#fff;margin:20px 0 8px;font-size:.95rem">Публичные / OpenAI-совместимые</h3>
<table><tr><th>Метод</th><th>Путь</th><th>Описание</th></tr>
<tr><td>GET</td><td><code>/health</code></td><td>Состояние пула (без авторизации)</td></tr>
<tr><td>GET</td><td><code>/status</code></td><td>Подробный статус: пул, статистика, аптайм</td></tr>
<tr><td>GET</td><td><code>/v1/models</code></td><td>Список моделей (OpenAI-формат)</td></tr>
<tr><td>POST</td><td><code>/v1/chat/completions</code></td><td>Запрос к Claude (OpenAI-формат, поддержка stream)</td></tr></table>

<h3 style="color:#fff;margin:20px 0 8px;font-size:.95rem">Управление API ключами</h3>
<table><tr><th>Метод</th><th>Путь</th><th>Описание</th></tr>
<tr><td>GET</td><td><code>/admin/keys</code></td><td>Список всех ключей с метаданными и статистикой</td></tr>
<tr><td>POST</td><td><code>/admin/keys</code></td><td>Создать ключ. Body: <code>{key, name, rpm, tokens_limit, hard_limit?}</code> (0=∞; <code>credits_limit</code> — legacy)</td></tr>
<tr><td>PUT</td><td><code>/admin/keys</code></td><td>Обновить ключ. Body: <code>{key, name?, rpm?, enabled?, tokens_limit?, tokens_used?, credits_limit?, credits_used?, hard_limit?}</code></td></tr>
<tr><td>DELETE</td><td><code>/admin/keys/{key}</code></td><td>Удалить ключ</td></tr>
<tr><td>POST</td><td><code>/admin/keys/bulk</code></td><td>Массовое создание (до 200). Body: <code>{items:[{key?, name, rpm, tokens_limit}]}</code>. <code>key</code> опционален — автогенерация</td></tr>
<tr><td>POST</td><td><code>/admin/keys/bulk-action</code></td><td>Массовое действие. Body: <code>{keys:[full_key,...], action, value?}</code>. Действия: <code>delete</code>, <code>enable</code>, <code>disable</code>, <code>set_tokens</code>, <code>set_credits</code>, <code>set_rpm</code>, <code>reset_used</code></td></tr>
<tr><td>POST</td><td><code>/admin/keys/topup-tokens</code></td><td>Топ-ап токенов. Body: <code>{key, amount}</code>. amount&gt;0 — добавляется к <code>tokens_limit</code></td></tr>
<tr><td>POST</td><td><code>/admin/keys/duplicate</code></td><td>Создать копию с теми же лимитами. Body: <code>{source_key, name?, new_key?}</code>. <code>source_key</code> может быть полным ключом или preview</td></tr>
<tr><td>GET</td><td><code>/admin/key-resolve/{preview}</code></td><td>Получить полный ключ по preview (вида <code>sk-xxxx...yyyy</code>)</td></tr>
<tr><td>GET</td><td><code>/admin/logs/key/{preview}</code></td><td>Логи запросов конкретного ключа</td></tr></table>

<h3 style="color:#fff;margin:20px 0 8px;font-size:.95rem">Множители стоимости</h3>
<table><tr><th>Метод</th><th>Путь</th><th>Описание</th></tr>
<tr><td>GET</td><td><code>/admin/multipliers</code></td><td>Текущие множители стоимости по моделям</td></tr>
<tr><td>POST</td><td><code>/admin/multipliers</code></td><td>Заменить набор. Body: <code>{multipliers: {"model-name": float, ...}}</code>. Сохраняется в <code>multipliers.json</code></td></tr></table>

<h3 style="color:#fff;margin:20px 0 8px;font-size:.95rem">Аккаунты Kiro и логи</h3>
<table><tr><th>Метод</th><th>Путь</th><th>Описание</th></tr>
<tr><td>GET</td><td><code>/admin/accounts</code></td><td>Список аккаунтов: статус, кредиты, статистика</td></tr>
<tr><td>POST</td><td><code>/admin/accounts</code></td><td>Добавить аккаунт. Body: <code>{label, email, refresh_token, proxy?}</code></td></tr>
<tr><td>DELETE</td><td><code>/admin/accounts/{label}</code></td><td>Удалить аккаунт</td></tr>
<tr><td>POST</td><td><code>/admin/accounts/{label}/freeze</code></td><td>Заморозить — acquire() будет пропускать. Состояние в <code>frozen_accounts.json</code></td></tr>
<tr><td>POST</td><td><code>/admin/accounts/{label}/unfreeze</code></td><td>Разморозить аккаунт</td></tr>
<tr><td>GET</td><td><code>/admin/logs?limit=N</code></td><td>Глобальный журнал запросов (последние N, по умолчанию 100)</td></tr>
<tr><td>GET</td><td><code>/admin/metrics?hours=N</code></td><td>Временной ряд метрик (для графиков мониторинга)</td></tr></table>

<h3 style="color:#fff;margin:20px 0 8px;font-size:.95rem">Примеры</h3>
<pre># Создать 10 trial-ключей по 100k кредитов
curl -X POST https://api.scarlex.ru/admin/keys/bulk \\
  -H "Authorization: Bearer ADMIN_KEY" \\
  -H "Content-Type: application/json" \\
  -d '{"items":[
    {"name":"trial-1","rpm":45,"tokens_limit":100000},
    {"name":"trial-2","rpm":45,"tokens_limit":100000}
  ]}'

# Выключить пачку ключей
curl -X POST https://api.scarlex.ru/admin/keys/bulk-action \\
  -H "Authorization: Bearer ADMIN_KEY" \\
  -H "Content-Type: application/json" \\
  -d '{"keys":["sk-scarlex-aaa","sk-scarlex-bbb"],"action":"disable"}'

# Изменить множитель opus
curl -X POST https://api.scarlex.ru/admin/multipliers \\
  -H "Authorization: Bearer ADMIN_KEY" \\
  -H "Content-Type: application/json" \\
  -d '{"multipliers":{"claude-opus-4.7":7.0,"claude-sonnet-4":2.0}}'</pre>
</div>`;
}

function fmt(n){return Number(n||0).toLocaleString();}
function fmtT(n){n=n||0;if(n>=1e9)return(n/1e9).toFixed(1)+'B';if(n>=1e6)return(n/1e6).toFixed(1)+'M';if(n>=1e3)return(n/1e3).toFixed(1)+'K';return String(n);}
function esc(s){if(!s)return'';return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}

// ── Upstream Monitor ────────────────────────────────────────
var _upstreamLastUpdate = null;

function loadUpstream(){
  var t0 = Date.now();
  var statsEl = document.getElementById('upstreamStats');
  var accEl = document.getElementById('upstreamAccounts');
  var logsEl = document.getElementById('upstreamLogs');

  // 1. Status + latency
  vps('GET','/status').then(function(d){
    var latency = Date.now() - t0;
    _upstreamLastUpdate = new Date();
    if(!d||d.detail){
      statsEl.innerHTML=sc('Статус','ОФФЛАЙН','err')+sc('Задержка','—')+sc('Аккаунты','—')+sc('Свободно','—')+sc('Запросов','—')+sc('Токенов','—');
      return;
    }
    var s = d;
    var aliveColor = s.pool.alive === s.pool.total ? 'ok' : s.pool.alive > 0 ? 'warn' : 'err';
    var latColor = latency < 300 ? 'ok' : latency < 1000 ? 'warn' : 'err';
    statsEl.innerHTML =
      sc('Статус','● Онлайн','ok') +
      sc('Задержка', latency + 'мс', latColor) +
      sc('Аккаунты', s.pool.alive + '/' + s.pool.total, aliveColor) +
      sc('Свободно', s.pool.available) +
      sc('Запросов', fmt(s.stats.total_requests)) +
      sc('Токенов', fmtT(s.stats.total_tokens)) +
      sc('Аптайм', s.uptime) +
      sc('RPM (всего)', fmt(s.stats.rpm || 0));
  }).catch(function(){
    statsEl.innerHTML=sc('Статус','ОФФЛАЙН','err')+sc('Задержка','Таймаут','err');
  });

  // 1b. System load (CPU / RAM / disk / network)
  loadSysMetrics();

  // 2. Accounts with health details
  vps('GET','/admin/accounts').then(function(d){
    if(!d||!d.accounts){accEl.innerHTML='<span style="color:var(--tm)">Не удалось загрузить</span>';return;}
    var accs = d.accounts;
    var alive = accs.filter(function(a){return !a.is_dead;}).length;
    var dead = accs.length - alive;
    document.getElementById('upAccSummary').textContent = alive + ' живых, ' + dead + ' мёртвых — всего ' + accs.length;

    var h = '<table><tr><th>#</th><th>ID</th><th>Email</th><th>Состояние</th><th>Кредиты</th><th>Загрузка %</th><th>Запросов</th><th>Токенов</th><th>Прокси</th></tr>';
    accs.forEach(function(a, i){
      var pct = a.credits_limit > 0 ? Math.round(a.credits_used / a.credits_limit * 100) : 0;
      var pctColor = a.is_dead ? 'var(--err)' : pct > 90 ? 'var(--err)' : pct > 70 ? 'var(--warn)' : 'var(--ok)';
      var statusBadge = a.is_dead ? '<span class="badge off">Мёртв</span>' : '<span class="badge ok">Жив</span>';
      var totalTok = (a.tokens_prompt||0) + (a.tokens_completion||0);

      h += '<tr>';
      h += '<td style="color:var(--tm)">' + (i+1) + '</td>';
      h += '<td style="font-family:JetBrains Mono;font-size:.75rem">' + esc(a.label) + '</td>';
      h += '<td>' + esc(a.email) + '</td>';
      h += '<td>' + statusBadge + '</td>';
      h += '<td>' + a.credits_used + '/' + a.credits_limit + '</td>';
      h += '<td><div class="bar" style="width:80px"><div class="f" style="width:' + Math.min(100,pct) + '%;background:' + pctColor + '"></div></div> <span style="color:' + pctColor + '">' + pct + '%</span></td>';
      h += '<td>' + fmt(a.requests_total) + '</td>';
      h += '<td>' + fmtT(totalTok) + '</td>';
      h += '<td style="color:var(--tm);font-size:.75rem">' + esc(a.proxy || 'прямое') + '</td>';
      h += '</tr>';
    });
    h += '</table>';
    accEl.innerHTML = h;
  });

  // 3. Recent logs
  vps('GET','/admin/logs?limit=20').then(function(d){
    if(!d||!d.logs||!d.logs.length){logsEl.innerHTML='<span style="color:var(--tm)">Нет недавних запросов</span>';return;}
    document.getElementById('upLogSummary').textContent = 'Последние ' + d.logs.length + ' из ' + d.total;
    var h = '<table><tr><th>Время</th><th>Ключ</th><th>Модель</th><th>Промпт</th><th>Вх</th><th>Исх</th><th>мс</th><th>Аккаунт</th><th>Статус</th></tr>';
    d.logs.forEach(function(l){
      var timeStr = l.ts ? l.ts.substring(11,19) : '';
      var statusColor = l.status === 'ok' ? 'ok' : 'off';
      h += '<tr>';
      h += '<td style="color:var(--tm);font-family:JetBrains Mono;font-size:.72rem">' + timeStr + '</td>';
      h += '<td style="font-size:.78rem">' + esc(l.key_name || '') + '</td>';
      h += '<td style="font-size:.78rem">' + esc(l.model || '') + '</td>';
      h += '<td class="pcell" title="' + esc(l.prompt_preview||'') + '">' + esc((l.prompt_preview||'').substring(0,40)) + '</td>';
      h += '<td>' + fmt(l.prompt_tokens) + '</td>';
      h += '<td>' + fmt(l.completion_tokens) + '</td>';
      h += '<td>' + (l.duration_ms||0) + '</td>';
      h += '<td style="color:var(--tm);font-size:.75rem">' + esc(l.account||'') + '</td>';
      h += '<td><span class="badge ' + statusColor + '">' + l.status + '</span></td>';
      h += '</tr>';
    });
    h += '</table>';
    logsEl.innerHTML = h;
  });
}

// ── Auto-refresh ────────────────────────────────────────
var _upstreamCountdown = 30;
setInterval(function(){
  // Upstream tab auto-refresh
  if(document.getElementById('s-upstream').classList.contains('on')){
    _upstreamCountdown--;
    if(_upstreamCountdown <= 0){
      loadUpstream();
      loadMetricsCharts();
      _upstreamCountdown = 30;
    }
    var el = document.getElementById('upstreamTimer');
    if(el) el.textContent = 'След. обновление: ' + _upstreamCountdown + 'с' + (_upstreamLastUpdate ? ' • Последнее: ' + _upstreamLastUpdate.toLocaleTimeString() : '');
  } else {
    _upstreamCountdown = 30;
  }
  // Dashboard auto-refresh (existing)
  if(document.getElementById('s-dash').classList.contains('on')){loadStatus();loadKeys();}
}, 1000);

// Replace old 30s interval
// (removed setInterval at bottom — replaced with unified interval above)

// ── VPS system load (CPU/RAM/disk/network) ──────────────
function fmtBytes(n) {
  n = +n || 0;
  if (n >= 1024*1024*1024) return (n/1024/1024/1024).toFixed(1) + ' GB';
  if (n >= 1024*1024)      return (n/1024/1024).toFixed(1) + ' MB';
  if (n >= 1024)           return (n/1024).toFixed(0) + ' KB';
  return n + ' B';
}
function fmtBps(n) { return fmtBytes(n) + '/s'; }
function pctColor(p) {
  if (p === null || p === undefined) return '#fff';
  if (p >= 90) return 'var(--err)';
  if (p >= 70) return 'var(--warn)';
  return 'var(--ok)';
}
function loadSysMetrics() {
  vps('GET', '/admin/system/metrics').then(function(d) {
    if (!d || d.detail) return;
    // CPU
    var cpu = d.cpu || {};
    if (cpu.percent !== null && cpu.percent !== undefined) {
      var el = document.getElementById('sysCpu');
      el.textContent = cpu.percent + '%';
      el.style.color = pctColor(cpu.percent);
      document.getElementById('sysCpuSub').textContent = (cpu.count || '?') + ' ядер';
    }
    // RAM
    var mem = d.memory || {};
    if (mem.percent !== null && mem.percent !== undefined) {
      var el2 = document.getElementById('sysMem');
      el2.textContent = mem.percent + '%';
      el2.style.color = pctColor(mem.percent);
      document.getElementById('sysMemSub').textContent =
        fmtBytes(mem.used) + ' / ' + fmtBytes(mem.total);
    }
    // Disk
    var disk = d.disk || {};
    if (disk.percent !== null && disk.percent !== undefined) {
      var el3 = document.getElementById('sysDisk');
      el3.textContent = disk.percent + '%';
      el3.style.color = pctColor(disk.percent);
      document.getElementById('sysDiskSub').textContent =
        fmtBytes(disk.used) + ' / ' + fmtBytes(disk.total);
    }
    // Network
    var net = d.network || {};
    document.getElementById('sysNetRx').textContent = fmtBps(net.rx_bytes_per_sec || 0);
    document.getElementById('sysNetRxSub').textContent = fmtBytes(net.rx_bytes || 0) + ' всего';
    document.getElementById('sysNetTx').textContent = fmtBps(net.tx_bytes_per_sec || 0);
    document.getElementById('sysNetTxSub').textContent = fmtBytes(net.tx_bytes || 0) + ' всего';
    // Load avg
    var la = d.load_avg || {};
    if (la['1m'] !== undefined) {
      var loadEl = document.getElementById('sysLoad');
      var nc = (cpu.count || 1);
      var ratio = la['1m'] / nc;
      loadEl.textContent = la['1m'].toFixed(2);
      loadEl.style.color = ratio >= 1.0 ? 'var(--err)' : ratio >= 0.7 ? 'var(--warn)' : 'var(--ok)';
      document.getElementById('sysLoadSub').textContent =
        '5м: ' + (la['5m'] || 0).toFixed(2) + '  15м: ' + (la['15m'] || 0).toFixed(2);
    }
    // Uptime + process info as a footer line
    var ts = document.getElementById('sysLoadTs');
    var ut = '';
    if (d.uptime_seconds) {
      var s = d.uptime_seconds;
      var days = Math.floor(s/86400); s -= days*86400;
      var hours = Math.floor(s/3600); s -= hours*3600;
      var mins = Math.floor(s/60);
      ut = 'аптайм ' + (days ? days+'д ' : '') + hours+'ч '+mins+'м';
    }
    var procRss = (d.process && d.process.rss_bytes) ? ('  · proxy RSS ' + fmtBytes(d.process.rss_bytes)) : '';
    var iface = (net.interface) ? ('  · ' + net.interface) : '';
    ts.textContent = ut + procRss + iface;
  }).catch(function() {
    document.getElementById('sysLoadTs').textContent = 'ошибка загрузки';
  });
}

// ── Chart drawing ────────────────────────────────────────

function drawChart(canvasId, points, getValue, opts) {
  var canvas = document.getElementById(canvasId);
  if (!canvas || !points.length) return;
  var ctx = canvas.getContext('2d');
  var W = canvas.width = canvas.parentElement.clientWidth - 24;
  var H = canvas.height = 120;
  ctx.clearRect(0, 0, W, H);

  // Store data on canvas for tooltip
  canvas._chartData = {points: points, getValue: getValue, opts: opts, W: W, H: H};
  if (!canvas._hasTooltip) {
    canvas._hasTooltip = true;
    var tip = document.createElement('div');
    tip.style.cssText = 'position:fixed;background:#1a1a2e;color:#fff;padding:6px 12px;border-radius:8px;font-size:.78rem;font-family:JetBrains Mono,monospace;pointer-events:none;z-index:300;border:1px solid rgba(255,255,255,.12);display:none;white-space:nowrap;box-shadow:0 4px 12px rgba(0,0,0,.4)';
    document.body.appendChild(tip);
    canvas.addEventListener('mousemove', function(e) {
      var d = canvas._chartData; if (!d) return;
      var rect = canvas.getBoundingClientRect();
      var mx = e.clientX - rect.left;
      var idx = Math.round((mx / d.W) * (d.points.length - 1));
      idx = Math.max(0, Math.min(d.points.length - 1, idx));
      var p = d.points[idx];
      var v = d.getValue(p);
      var ts = (p.ts || '').substring(11, 19);
      var label = d.opts.fmtY ? d.opts.fmtY(v) : String(Math.round(v));
      var extra = '';
      if (d.opts.getValue2) { var v2 = d.opts.getValue2(p); extra = ' / ' + (d.opts.fmtY ? d.opts.fmtY(v2) : String(Math.round(v2))); }
      tip.innerHTML = '<span style="color:var(--ts)">' + ts + '</span>  <b>' + label + extra + '</b>';
      tip.style.display = 'block';
      tip.style.left = (e.clientX + 12) + 'px';
      tip.style.top = (e.clientY - 30) + 'px';
    });
    canvas.addEventListener('mouseleave', function() { tip.style.display = 'none'; });
  }

  var values = points.map(getValue);
  var maxV = opts.maxV || Math.max.apply(null, values) || 1;
  if (opts.minV !== undefined) var minV = opts.minV; else var minV = 0;
  var range = maxV - minV || 1;

  var color = opts.color || 'rgba(115,240,188,0.8)';
  var fillColor = opts.fill || 'rgba(115,240,188,0.08)';

  // Grid lines
  ctx.strokeStyle = 'rgba(255,255,255,0.04)';
  ctx.lineWidth = 1;
  for (var g = 0; g < 4; g++) {
    var gy = H - (H * (g / 3));
    ctx.beginPath(); ctx.moveTo(0, gy); ctx.lineTo(W, gy); ctx.stroke();
  }

  // Time labels
  ctx.fillStyle = 'rgba(255,255,255,0.25)';
  ctx.font = '9px Inter, sans-serif';
  var step = Math.max(1, Math.floor(points.length / 6));
  for (var i = 0; i < points.length; i += step) {
    var ts = points[i].ts || '';
    var timeStr = ts.substring(11, 16);
    var x = (i / (points.length - 1)) * W;
    ctx.fillText(timeStr, x, H - 2);
  }

  // Y labels
  ctx.textAlign = 'right';
  ctx.fillText(opts.fmtY ? opts.fmtY(maxV) : String(Math.round(maxV)), W - 2, 10);
  ctx.fillText(opts.fmtY ? opts.fmtY(minV) : String(Math.round(minV)), W - 2, H - 14);
  ctx.textAlign = 'left';

  var chartH = H - 20;
  var chartY = 4;

  // Uptime mode: colored bars
  if (opts.bars) {
    var barW = Math.max(1, W / points.length);
    for (var i = 0; i < points.length; i++) {
      var v = values[i];
      var x = (i / points.length) * W;
      ctx.fillStyle = v ? 'rgba(115,240,188,0.5)' : 'rgba(255,127,159,0.5)';
      ctx.fillRect(x, chartY, barW + 1, chartH);
    }
    return;
  }

  // Line + fill
  ctx.beginPath();
  ctx.moveTo(0, chartY + chartH);
  for (var i = 0; i < values.length; i++) {
    var x = (i / (values.length - 1)) * W;
    var y = chartY + chartH - ((values[i] - minV) / range) * chartH;
    ctx.lineTo(x, y);
  }
  ctx.lineTo(W, chartY + chartH);
  ctx.closePath();
  ctx.fillStyle = fillColor;
  ctx.fill();

  // Line
  ctx.beginPath();
  for (var i = 0; i < values.length; i++) {
    var x = (i / (values.length - 1)) * W;
    var y = chartY + chartH - ((values[i] - minV) / range) * chartH;
    if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
  }
  ctx.strokeStyle = color;
  ctx.lineWidth = 1.5;
  ctx.stroke();

  // Second line (if opts.getValue2)
  if (opts.getValue2) {
    var vals2 = points.map(opts.getValue2);
    ctx.beginPath();
    for (var i = 0; i < vals2.length; i++) {
      var x = (i / (vals2.length - 1)) * W;
      var y = chartY + chartH - ((vals2[i] - minV) / range) * chartH;
      if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
    }
    ctx.strokeStyle = opts.color2 || 'rgba(255,207,96,0.7)';
    ctx.lineWidth = 1.5;
    ctx.stroke();
  }
}

function loadMetricsCharts() {
  var hours = parseFloat(document.getElementById('upstreamRange').value) || 6;
  vps('GET', '/admin/metrics/history?hours=' + hours).then(function(d) {
    if (!d || !d.points || !d.points.length) return;
    var pts = d.points;

    // 1. Uptime (bar chart: green=online, red=offline)
    drawChart('chartUptime', pts, function(p){ return p.online ? 1 : 0; }, {bars: true});

    // 2. Accounts alive + cooldown
    var maxAcc = pts[0].total || 24;
    drawChart('chartAccounts', pts, function(p){ return p.alive || 0; }, {
      color: 'rgba(115,240,188,0.8)', fill: 'rgba(115,240,188,0.08)',
      maxV: maxAcc,
      getValue2: function(p){ return p.cooldown || 0; },
      color2: 'rgba(255,207,96,0.7)',
      fmtY: function(v){ return String(Math.round(v)); }
    });

    // 3. RPM
    drawChart('chartRpm', pts, function(p){ return p.rpm || 0; }, {
      color: 'rgba(100,180,255,0.8)', fill: 'rgba(100,180,255,0.08)',
      fmtY: function(v){ return String(Math.round(v)); }
    });

    // 4. Errors
    drawChart('chartErrors', pts, function(p){ return p.errors_60s || 0; }, {
      color: 'rgba(255,127,159,0.8)', fill: 'rgba(255,127,159,0.1)',
      fmtY: function(v){ return String(Math.round(v)); }
    });

    // 5. Tokens delta
    drawChart('chartTokens', pts, function(p){ return p.delta_tokens || 0; }, {
      color: 'rgba(190,140,255,0.8)', fill: 'rgba(190,140,255,0.08)',
      fmtY: function(v){ return fmtT(v); }
    });

    // 6. Credits remaining
    drawChart('chartCredits', pts, function(p){ return p.credits_remaining || 0; }, {
      color: 'rgba(255,207,96,0.8)', fill: 'rgba(255,207,96,0.08)',
      fmtY: function(v){ return fmtT(v); }
    });
  });
}

// ============ Множители ============
var _multCache = {};
function loadMult(){
  vps('GET','/admin/multipliers').then(function(d){
    if(!d || !d.multipliers){ document.getElementById('multTable').innerHTML='<span style="color:var(--err)">Ошибка загрузки</span>'; return; }
    _multCache = Object.assign({}, d.multipliers);
    renderMult();
  });
}
function renderMult(){
  var keys = Object.keys(_multCache).sort();
  var h = '<table style="width:100%"><tr><th style="width:60%">Модель</th><th>Множитель</th><th style="width:60px"></th></tr>';
  keys.forEach(function(k){
    var safe = esc(k);
    h += '<tr>';
    h += '<td><input data-mult-key="'+safe+'" value="'+safe+'" style="width:100%;font-family:JetBrains Mono;font-size:.85rem"></td>';
    h += '<td><input type="number" step="0.1" min="0" data-mult-val="'+safe+'" value="'+_multCache[k]+'" style="width:120px"></td>';
    h += '<td><button onclick="delMultRow(\''+safe.replace(/'/g,"\\'")+'\')" style="background:rgba(255,107,107,.1);color:var(--err);border-color:rgba(255,107,107,.2);padding:4px 10px">×</button></td>';
    h += '</tr>';
  });
  h += '</table>';
  document.getElementById('multTable').innerHTML = h;
}
function addMultRow(){
  var name = prompt('Название модели (например claude-haiku-3.5):');
  if(!name) return;
  name = name.trim();
  if(!name) return;
  if(_multCache[name] !== undefined){ alert('Уже есть'); return; }
  _multCache[name] = 1.0;
  renderMult();
}
function delMultRow(k){
  if(!confirm('Удалить '+k+'?')) return;
  delete _multCache[k];
  renderMult();
}
function saveMult(){
  // Read current state from inputs (in case user edited names too)
  var rows = document.querySelectorAll('#multTable tr[class],#multTable tr');
  var out = {};
  document.querySelectorAll('#multTable input[data-mult-key]').forEach(function(inp){
    var origKey = inp.getAttribute('data-mult-key');
    var newKey = inp.value.trim();
    if(!newKey) return;
    var valInp = document.querySelector('#multTable input[data-mult-val="'+origKey+'"]');
    var v = parseFloat(valInp.value);
    if(isNaN(v) || v < 0){ alert('Неверное значение для '+newKey); throw new Error('bad'); }
    out[newKey] = v;
  });
  if(!Object.keys(out).length){ alert('Список пуст'); return; }
  vps('POST','/admin/multipliers',{multipliers: out}).then(function(d){
    if(d && d.status === 'ok'){
      _multCache = Object.assign({}, d.multipliers);
      renderMult();
      toast('Множители сохранены');
    } else {
      alert((d && d.detail) || 'Ошибка');
    }
  });
}
</script>
</body></html>
