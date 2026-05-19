/* Scarlex Dashboard core — общий JS для всех страниц.
   Подгружает sidebar, профиль, балансовый виджет; роутер активной вкладки. */

(function(){
  var SCX = window.SCX = window.SCX || {};
  SCX.api = function(url, body, opts){
    opts = opts || {};
    var init = { credentials:'same-origin', headers:{'Content-Type':'application/json'} };
    if (body) { init.method = opts.method || 'POST'; init.body = JSON.stringify(body); }
    else      { init.method = opts.method || 'GET'; }
    return fetch(url, init).then(function(r){ return r.json(); });
  };
  SCX.toast = function(msg){
    var t = document.getElementById('dToast');
    if (!t) { t = document.createElement('div'); t.id='dToast'; t.className='d-toast'; document.body.appendChild(t); }
    t.textContent = msg; t.classList.add('show');
    clearTimeout(t._t); t._t = setTimeout(function(){ t.classList.remove('show'); }, 2000);
  };
  SCX.escapeHtml = function(s){ if(s==null) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); };
  SCX.fmtRub = function(n){ return Number(n||0).toLocaleString('ru-RU',{minimumFractionDigits:2,maximumFractionDigits:2}); };
  SCX.fmtTokens = function(n){
    n = Number(n||0);
    if (n>=1e9) return (n/1e9).toFixed(2).replace(/\.?0+$/,'')+'B';
    if (n>=1e6) return (n/1e6).toFixed(2).replace(/\.?0+$/,'')+'M';
    if (n>=1e3) return (n/1e3).toFixed(1).replace(/\.?0+$/,'')+'K';
    return String(n);
  };
  SCX.fmtDate = function(s){ if(!s) return '—'; var d=new Date(s); return d.toLocaleDateString('ru-RU')+' '+d.toLocaleTimeString('ru-RU',{hour:'2-digit',minute:'2-digit'}); };

  // ── Sidebar template + active-link router ──
  var SIDEBAR_HTML = ''
    + '<aside class="d-sidebar" id="dSidebar">'
    + '  <div class="d-side-head">'
    + '    <a class="d-side-logo" href="/dashboard/home.html" title="Scarlex"><img src="/logo_nofon.png" alt="Scarlex"></a>'
    + '    <button class="d-side-toggle" id="dSideToggle" title="Свернуть"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>'
    + '  </div>'
    + '  <nav class="d-side-nav">'
    + '    <div class="d-side-group">'
    + '      <div class="d-side-label">Аккаунт</div>'
    + '      <a class="d-nav-item" data-page="home"         href="/dashboard/home.html"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 12l9-8 9 8M5 10v10h14V10"/></svg><span>Главная</span></a>'
    + '      <a class="d-nav-item" data-page="profile"      href="/dashboard/profile.html"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-7 8-7s8 3 8 7"/></svg><span>Профиль</span></a>'
    + '      <a class="d-nav-item" data-page="settings"     href="/dashboard/settings.html"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.2-1.5l2.1-1.6-2-3.4-2.4 1a7 7 0 0 0-2.6-1.5L13.5 2h-3l-.4 2.5a7 7 0 0 0-2.6 1.5l-2.4-1-2 3.4 2.1 1.6A7 7 0 0 0 5 12c0 .5.1 1 .2 1.5L3.1 15l2 3.5 2.4-1c.8.7 1.7 1.2 2.6 1.5L10.5 22h3l.4-2.5c.9-.3 1.8-.8 2.6-1.5l2.4 1 2-3.5-2.1-1.5c.1-.5.2-1 .2-1.5z"/></svg><span>Настройки</span></a>'
    + '      <a class="d-nav-item" data-page="achievements" href="/dashboard/achievements.html"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4zM5 4v3a3 3 0 0 0 3 3M19 4v3a3 3 0 0 1-3 3"/></svg><span>Достижения</span></a>'
    + '    </div>'
    + '    <div class="d-side-group">'
    + '      <div class="d-side-label">API</div>'
    + '      <a class="d-nav-item" data-page="keys"      href="/dashboard/keys.html"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="7" cy="15" r="4"/><path d="M11 13l9-9-2-2-2 2M16 6l2 2"/></svg><span>Ключи</span></a>'
    + '      <a class="d-nav-item" data-page="analytics" href="/dashboard/analytics.html"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 19V5M4 19h16M8 15v-4M12 15V7M16 15v-6"/></svg><span>Аналитика</span></a>'
    + '      <a class="d-nav-item" data-page="models"    href="/dashboard/models.html"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M5 19l2-2M17 7l2-2"/></svg><span>Модели</span></a>'
    + '      <a class="d-nav-item" data-page="docs"      href="/dashboard/docs.html"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 4h12a3 3 0 0 1 3 3v13H7a3 3 0 0 1-3-3V4zM4 17a3 3 0 0 1 3-3h12"/></svg><span>Документация</span></a>'
    + '    </div>'
    + '    <div class="d-side-group">'
    + '      <div class="d-side-label">Баланс и тарифы</div>'
    + '      <a class="d-nav-item" data-page="market"       href="/dashboard/market.html"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 6h15l-2 9H8L6 4H3M9 21a1 1 0 100-2 1 1 0 000 2zm9 0a1 1 0 100-2 1 1 0 000 2z"/></svg><span>Маркет</span></a>'
    + '      <a class="d-nav-item" data-page="transactions" href="/dashboard/transactions.html"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 12h18M3 6h18M3 18h18"/></svg><span>Транзакции</span></a>'
    + '      <a class="d-nav-item" data-page="referrals"    href="/dashboard/referrals.html"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0114 0M17 11a3 3 0 100-6 3 3 0 000 6zM22 21a5 5 0 00-8-4"/></svg><span>Рефералы</span></a>'
    + '      <a class="d-nav-item" data-page="promocodes"   href="/dashboard/promocodes.html"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M20 12V7H4v5M2 12h20v8H2zM9 16v2M15 16v2"/></svg><span>Промокоды</span></a>'
    + '    </div>'
    + '    <div class="d-side-group d-side-admin" id="dSideAdmin" style="display:none">'
    + '      <div class="d-side-label" style="color:#ff9aae">Администрация</div>'
    + '      <a class="d-nav-item" data-page="admin" href="/admin.html"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 2l9 4v6c0 5-3.5 9-9 10-5.5-1-9-5-9-10V6l9-4z"/><path d="M9 12l2 2 4-4"/></svg><span>Админ-панель</span></a>'
    + '    </div>'
    + '  </nav>'
    + '  <div class="d-side-foot">'
    + '    <div class="d-side-balance">'
    + '      <div class="lbl">Баланс</div>'
    + '      <div class="val" id="dBalance">— ₽</div>'
    + '      <a class="topup" href="/dashboard/transactions.html?action=topup">Пополнить</a>'
    + '    </div>'
    + '    <a class="d-nav-item" data-page="chat" href="/dashboard/chat.html"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M21 11a8 8 0 01-15 4l-3 1 1-3a8 8 0 1117-2z"/></svg><span>Чат-поддержка</span></a>'
    + '    <a class="d-nav-item" href="#" id="dLogout"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg><span>Выйти</span></a>'
    + '  </div>'
    + '</aside>';

  SCX.mountSidebar = function(page){
    var holder = document.getElementById('dSidebarHolder');
    if (!holder) {
      holder = document.createElement('div');
      holder.id = 'dSidebarHolder';
      document.body.insertBefore(holder, document.body.firstChild);
    }
    holder.innerHTML = SIDEBAR_HTML;
    // Active link
    var active = page || (location.pathname.split('/').pop() || 'home.html').replace('.html','');
    document.querySelectorAll('.d-nav-item[data-page]').forEach(function(a){
      a.classList.toggle('is-active', a.getAttribute('data-page') === active);
    });
    // Collapse persistence
    var sb = document.getElementById('dSidebar');
    if (localStorage.getItem('scx_side') === 'collapsed') sb.classList.add('collapsed');
    document.getElementById('dSideToggle').addEventListener('click', function(){
      sb.classList.toggle('collapsed');
      localStorage.setItem('scx_side', sb.classList.contains('collapsed') ? 'collapsed' : 'open');
    });
    document.getElementById('dLogout').addEventListener('click', function(e){
      e.preventDefault();
      fetch('/api/auth.php', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'logout'}) })
        .then(function(){ location.href = '/authorization.html'; });
    });

    // ── Mobile: burger + backdrop ──
    if (!document.getElementById('dBackdrop')) {
      var bd = document.createElement('div');
      bd.id = 'dBackdrop';
      bd.className = 'd-backdrop';
      document.body.appendChild(bd);
      bd.addEventListener('click', function(){ sb.classList.remove('is-open'); bd.classList.remove('show'); });
    }
    // Close sidebar on any nav-link tap inside it (mobile UX)
    sb.querySelectorAll('.d-nav-item').forEach(function(a){
      a.addEventListener('click', function(){
        if (window.matchMedia('(max-width:920px)').matches) {
          sb.classList.remove('is-open');
          var bd2 = document.getElementById('dBackdrop'); if (bd2) bd2.classList.remove('show');
        }
      });
    });
    // Burger lives inside the topbar of each page — wire any present
    document.querySelectorAll('.d-mobile-burger').forEach(function(b){
      if (b.dataset._wired) return; b.dataset._wired = '1';
      b.addEventListener('click', function(){
        sb.classList.toggle('is-open');
        var bd3 = document.getElementById('dBackdrop'); if (bd3) bd3.classList.toggle('show', sb.classList.contains('is-open'));
      });
    });
    // If a page didn't include its own burger, inject one into the first .d-topbar.
    var firstBar = document.querySelector('.d-topbar');
    if (firstBar && !firstBar.querySelector('.d-mobile-burger')) {
      var bt = document.createElement('button');
      bt.className = 'd-mobile-burger';
      bt.setAttribute('aria-label', 'Меню');
      bt.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>';
      bt.addEventListener('click', function(){
        sb.classList.toggle('is-open');
        var bd4 = document.getElementById('dBackdrop'); if (bd4) bd4.classList.toggle('show', sb.classList.contains('is-open'));
      });
      firstBar.insertBefore(bt, firstBar.firstChild);
    }
  };

  // ── Preview mode: ?preview=1 (или localStorage scx_preview) — мокаем API без логина ──
  (function setupPreview(){
    try {
      var qs = new URLSearchParams(location.search);
      if (qs.get('preview') === '1') localStorage.setItem('scx_preview', '1');
      if (qs.get('preview') === '0') localStorage.removeItem('scx_preview');
      SCX.preview = localStorage.getItem('scx_preview') === '1';
    } catch(e){ SCX.preview = false; }
    if (!SCX.preview) return;

    var demoUser = { id:1, public_id:'demo-42', email:'demo@scarlex.ru', username:'demo_user', balance:1234.56, telegram_id:null, telegram_username:null, created_at:'2025-09-12 14:30:00' };
    var demoKeys = [
      { id:1, key_value:'sk-scarlex-demo000000000000000000000000000001', masked:'sk-scarlex...0001', label:'Production', tokens_limit:50000000, used_tokens:18432110, remaining:31567890, usage_percent:36.9, is_active:true, is_frozen:false, is_test:false, source:'scarlex_proxy', created_at:'2025-10-22 12:00:00' },
      { id:2, key_value:'sk-scarlex-demo000000000000000000000000000002', masked:'sk-scarlex...0002', label:'Trial', tokens_limit:1000000, used_tokens:780000, remaining:220000, usage_percent:78, is_active:true, is_frozen:false, is_test:true, source:'scarlex_proxy', created_at:'2025-11-01 18:22:00' },
      { id:3, key_value:'sk-scarlex-demo000000000000000000000000000003', masked:'sk-scarlex...0003', label:'Side project', tokens_limit:25000000, used_tokens:1250000, remaining:23750000, usage_percent:5, is_active:true, is_frozen:true, is_test:false, source:'scarlex_proxy', created_at:'2025-11-05 09:10:00' }
    ];
    var demoTx = [
      { id:101, tx_type:'financial', type:'purchase', amount:-540, description:'Покупка 50M токенов', created_at:'2025-11-08 10:12:00', key_label:'Production', key_masked:'sk-scarlex...0001' },
      { id:102, tx_type:'financial', type:'topup', amount:+1500, description:'Пополнение YooMoney', created_at:'2025-11-07 18:30:00', key_label:null, key_masked:null },
      { id:103, tx_type:'chat', model:'claude-sonnet-4.6', total_tokens:42100, status:'success', created_at:'2025-11-08 11:01:00', key_label:'Production', key_masked:'sk-scarlex...0001' },
      { id:104, tx_type:'financial', type:'topup_key', amount:-190, description:'Пополнение ключа: 10M', created_at:'2025-11-06 14:05:00', key_label:'Side project', key_masked:'sk-scarlex...0003' },
      { id:105, tx_type:'chat', model:'claude-haiku-4.5', total_tokens:1820, status:'success', created_at:'2025-11-08 11:02:00', key_label:'Trial', key_masked:'sk-scarlex...0002' },
      { id:106, tx_type:'financial', type:'refund', amount:+120, description:'Возврат за неиспользованный ключ', created_at:'2025-11-04 09:00:00', key_label:'Old key', key_masked:'sk-scarlex...9999' }
    ];
    var demoDaily = []; for (var i=29;i>=0;i--){ var d=new Date(); d.setDate(d.getDate()-i); demoDaily.push({date:d.toISOString().slice(0,10), cnt: Math.max(0, Math.round(40 + Math.sin(i/3)*25 + Math.random()*30))}); }
    var demoModels = [
      {model:'claude-sonnet-4.6', cnt:420},
      {model:'claude-haiku-4.5',  cnt:180},
      {model:'claude-opus-4.7',   cnt:62},
      {model:'claude-sonnet-4.5', cnt:38}
    ];
    var demoAch = [
      {id:'first_key',name:'Первый ключ',description:'Получи свой первый API-ключ',icon:'🔑',tier:'bronze',category:'onboarding',unlocked:true,progress:1,threshold:1,percent:100},
      {id:'first_request',name:'Первый запрос',icon:'⚡',description:'Сделай первый запрос',tier:'bronze',category:'onboarding',unlocked:true,progress:1,threshold:1,percent:100},
      {id:'req_100',name:'Стартер',icon:'💯',description:'100 запросов',tier:'bronze',category:'requests',unlocked:true,progress:700,threshold:100,percent:100},
      {id:'req_1k',name:'Прокачка',icon:'🚀',description:'1 000 запросов',tier:'silver',category:'requests',unlocked:false,progress:700,threshold:1000,percent:70},
      {id:'tok_1m',name:'Миллион',icon:'⭐',description:'Сжечь 1M',tier:'silver',category:'tokens',unlocked:true,progress:1000000,threshold:1000000,percent:100},
      {id:'tok_10m',name:'Десять миллионов',icon:'🔥',description:'Сжечь 10M',tier:'gold',category:'tokens',unlocked:true,progress:19000000,threshold:10000000,percent:100},
      {id:'tok_100m',name:'Жадный',icon:'💎',description:'Сжечь 100M',tier:'platinum',category:'tokens',unlocked:false,progress:19000000,threshold:100000000,percent:19},
      {id:'streak_3',name:'Три дня подряд',icon:'📅',description:'3 дня активности',tier:'bronze',category:'streak',unlocked:true,progress:3,threshold:3,percent:100},
      {id:'streak_7',name:'Неделя',icon:'📆',description:'7 дней подряд',tier:'silver',category:'streak',unlocked:false,progress:4,threshold:7,percent:57}
    ];
    var demoTickets = [
      { id:1, subject:'Не списываются токены', status:'open', created_at:'2025-11-07 12:00:00', updated_at:'2025-11-08 09:00:00', msg_count:3 },
      { id:2, subject:'Как подключить SDK Anthropic?', status:'closed', created_at:'2025-10-28 10:00:00', updated_at:'2025-10-28 14:00:00', msg_count:5 }
    ];
    var demoPrefs = { notify_login:1, notify_purchase:1, notify_low_balance:1, notify_key_exhausted:1, notify_referral:0, notify_achievement:1, notify_daily_report:0, low_balance_threshold:10 };
    var demoSess = [{ id:'cur', is_current:true, browser:'Chrome', os:'Windows', ip:'185.227.0.1', last_seen:'2025-11-08 11:30:00' }, { id:'old', is_current:false, browser:'Safari', os:'iOS', ip:'95.142.10.55', last_seen:'2025-11-06 22:14:00' }];

    function ok(o){ return new Promise(function(r){ setTimeout(function(){ r(Object.assign({ok:true}, o)); }, 100); }); }

    SCX.api = function(url){
      url = String(url);
      if (url.indexOf('check_session') >= 0)  return ok({user:demoUser});
      if (url.indexOf('keys.php') >= 0)       return ok({keys:demoKeys, base_url:'https://api.scarlex.ru'});
      if (url.indexOf('transactions.php') >= 0) return ok({transactions:demoTx});
      if (url.indexOf('analytics.php?action=overview') >= 0) return ok({total_requests:1850, requests_30d:700, tokens_30d:19500000, spent_30d:3450, by_model:demoModels, daily:demoDaily});
      if (url.indexOf('analytics.php?action=burn_rate') >= 0) return ok({daily_avg:124.5, days_left:9, balance:demoUser.balance, total_spent:1743});
      if (url.indexOf('achievements.php') >= 0) return ok({achievements:demoAch, total:demoAch.length, unlocked:demoAch.filter(function(a){return a.unlocked;}).length});
      if (url.indexOf('models.php') >= 0) return ok({models:[
        {family:'Anthropic Claude', logo:'claude.png', items:[
          {id:'claude-opus-4.7', name:'Claude Opus 4.7', context:500000, tag:'flagship'},
          {id:'claude-opus-4.6', name:'Claude Opus 4.6', context:200000, tag:'reasoning'},
          {id:'claude-sonnet-4.6', name:'Claude Sonnet 4.6', context:200000, tag:'balanced'},
          {id:'claude-sonnet-4.5', name:'Claude Sonnet 4.5', context:200000, tag:'balanced'},
          {id:'claude-haiku-4.5', name:'Claude Haiku 4.5', context:200000, tag:'fast'}
        ]}
      ]});
      if (url.indexOf('referrals.php') >= 0) return ok({code:'DEMO42', link:'https://scarlex.ru/authorization.html?ref=DEMO42', total_referrals:7, active_referrals:3, total_earned:425.5, bonuses:[
        {id:1, bonus_type:'signup', amount:50, description:'Регистрация друга', created_at:'2025-11-01 12:00:00', referred_username:'friend_alex'},
        {id:2, bonus_type:'topup_percent', amount:75.5, description:'5% от пополнения', created_at:'2025-11-03 16:30:00', referred_username:'friend_alex'},
        {id:3, bonus_type:'topup_percent', amount:300, description:'5% от пополнения', created_at:'2025-11-06 09:15:00', referred_username:'demo_friend'}
      ]});
      if (url.indexOf('support.php?action=get') >= 0) return ok({ticket:{id:1,subject:'Не списываются токены',status:'open'}, messages:[
        {sender:'user', body:'Сделал 10 запросов через ключ, но used_tokens не растёт. Что не так?', created_at:'2025-11-07 12:00:00'},
        {sender:'admin', body:'Здравствуйте! Проверили — у вашего ключа всё корректно списывается. Возможно, кешировался ответ на стороне SDK. Попробуйте обновить страницу.', created_at:'2025-11-07 14:20:00'},
        {sender:'user', body:'Спасибо, помогло!', created_at:'2025-11-08 09:00:00'}
      ]});
      if (url.indexOf('support.php') >= 0) return ok({tickets:demoTickets});
      if (url.indexOf('settings.php') >= 0) return ok({user:demoUser, prefs:demoPrefs, sessions:demoSess});
      if (url.indexOf('buy_key.php?action=products') >= 0) {
        return fetch('/api/products_public.php').then(function(r){return r.json();}).then(function(d){ return Object.assign({ok:true}, d); });
      }
      if (url.indexOf('buy_key.php?action=quote_custom') >= 0) {
        var t = +(url.match(/tokens=(\d+)/)||[])[1] || 1000000;
        if (t > 500000000) return ok({tokens:t, contact:true, contact_url:'https://t.me/scarlex_support', message:'Объём свыше 500M токенов — цена по договорённости.'});
        var anchors = [{tokens:10000000,per:19},{tokens:25000000,per:11.6},{tokens:50000000,per:10.8},{tokens:250000000,per:10.36},{tokens:500000000,per:9.8}];
        var tier = anchors[0]; for (var i=0;i<anchors.length;i++) if (t >= anchors[i].tokens) tier = anchors[i];
        var per = tier.per;
        return ok({tokens:t, price_rub:Math.round(per*(t/1000000)*100)/100, per_1m:per, tier:{tokens:tier.tokens, per_1m:per}});
      }
      if (url.indexOf('news.php?action=list') >= 0 || url.indexOf('news.php') >= 0 && url.indexOf('action=') < 0) {
        return ok({posts:[
          {id:3, title:'⚡ Новая модель Claude Opus 4.7', body:'Добавили <b>Claude Opus 4.7</b> — флагман для сложного reasoning. Множитель ×5.', kind:'update', is_pinned:1, created_at:'2025-11-08 10:00:00', is_read:false},
          {id:2, title:'🛠 Тех.работы 09.11 03:00 МСК', body:'Плановое обновление прокси. Ожидаемый даунтайм: 5 минут.', kind:'maintenance', is_pinned:0, created_at:'2025-11-07 18:00:00', is_read:false},
          {id:1, title:'🎉 Запустили dashboard v4', body:'Полностью переработали личный кабинет: новое меню, ступенчатые тарифы, лента новостей.', kind:'news', is_pinned:0, created_at:'2025-11-05 14:00:00', is_read:true}
        ]});
      }
      if (url.indexOf('news.php?action=unread_count') >= 0) return ok({count:2});
      if (url.indexOf('notifications.php') >= 0) {
        var demoNotifs = [
          {id:5, title:'Ключ пополнен', body:'К ключу Production добавлено 50 000 000 токенов (50M, −540.00 ₽).', type:'success', is_read:0, created_at:'2025-11-08 11:00:00'},
          {id:4, title:'Новый реферал', body:'Друг zerg_alex зарегистрировался по твоей ссылке. Бонус: +50 ₽.', type:'info', is_read:0, created_at:'2025-11-07 18:32:00'},
          {id:3, title:'Достижение', body:'Получено достижение «Десять миллионов» — сжёг 10M токенов!', type:'success', is_read:1, created_at:'2025-11-06 12:15:00'},
          {id:2, title:null, body:'Возврат за ключ Old key: +120.00 ₽ зачислено на баланс.', type:'success', is_read:1, created_at:'2025-11-04 09:00:00'},
          {id:1, title:'Тестовый ключ активирован', body:'Получен бесплатный тестовый ключ на 1 000 000 токенов.', type:'info', is_read:1, created_at:'2025-11-01 14:00:00'}
        ];
        return ok({items: demoNotifs, unread: 2});
      }
      // POST actions: return ok+toast-like
      return ok({});
    };
  })();

  // ── Profile + balance loader ──
  SCX.loadProfile = function(){
    return SCX.api('/api/check_session.php').then(function(d){
      if (!d || !d.ok || !d.user) {
        if (SCX.preview) return null;
        location.href = '/authorization.html?next=' + encodeURIComponent(location.pathname); return null;
      }
      SCX.user = d.user;
      var chip = document.getElementById('dUserChip');
      if (chip) { chip.textContent = '👤 ' + d.user.username; chip.href = '/dashboard/profile.html'; }
      var bal = document.getElementById('dBalance');
      if (bal) bal.textContent = SCX.fmtRub(d.user.balance) + ' ₽';
      // Admin link (по email или public_id)
      var _isAdmin = ((d.user.email||'').toLowerCase() === 'luckyanencko.andrej2017@yandex.ru') || (String(d.user.public_id||'') === '67888');
      if (_isAdmin) {
        var adm = document.getElementById('dSideAdmin');
        if (adm) adm.style.display = '';
      }
      return d.user;
    });
  };

  // ── Preview-mode banner ──
  function injectPreviewBanner(){
    if (!SCX.preview) return;
    var b = document.createElement('div');
    b.style.cssText = 'position:fixed;bottom:14px;right:14px;z-index:400;background:#1c1c1f;border:1px solid rgba(255,255,255,.2);border-radius:10px;padding:10px 14px;font:600 12px/1.4 Inter,system-ui;color:#fff;display:flex;gap:10px;align-items:center';
    b.innerHTML = '<span style="color:#ffce5a">●</span> Preview-режим (демо-данные) <a href="?preview=0" style="color:#7cd0ff;text-decoration:underline">Выкл.</a>';
    document.body.appendChild(b);
  }

  // ── Page bootstrap helper ──
  SCX.boot = function(page, ready){
    document.addEventListener('DOMContentLoaded', function(){
      SCX.mountSidebar(page);
      injectPreviewBanner();
      SCX.loadProfile().then(function(u){
        if (u) SCX.mountNotifBell();
        if (u && typeof ready === 'function') ready(u);
      });
    });
  };

  // ── In-site notifications bell ────────────────────────────────────
  // Injects a 🔔 button into .d-topbar before #dUserChip, polls
  // /api/notifications.php every 30s, shows unread badge and a popover.
  var _notifPollTimer = null;
  var _notifItems = [];
  var _notifUnread = 0;

  function notifIconHtml(){
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="20" height="20">'
         + '<path d="M18 16v-5a6 6 0 10-12 0v5l-2 2h16l-2-2z"/>'
         + '<path d="M10 21a2 2 0 004 0"/></svg>';
  }

  SCX.mountNotifBell = function(){
    var bar = document.querySelector('.d-topbar');
    if (!bar) return;
    if (document.getElementById('dNotifWrap')) return; // already mounted

    var wrap = document.createElement('div');
    wrap.id = 'dNotifWrap';
    wrap.className = 'd-notif-wrap';
    wrap.innerHTML =
      '<button class="d-notif-bell" id="dNotifBtn" title="Уведомления" aria-label="Уведомления">'
        + notifIconHtml()
        + '<span class="d-notif-badge" id="dNotifBadge" hidden>0</span>'
      + '</button>'
      + '<div class="d-notif-popover" id="dNotifPop" hidden>'
        + '<div class="d-notif-head">'
          + '<b>Уведомления</b>'
          + '<button class="d-notif-readall" id="dNotifReadAll" type="button">Прочитать всё</button>'
        + '</div>'
        + '<div class="d-notif-list" id="dNotifList"><div class="d-empty">Загрузка…</div></div>'
      + '</div>';

    // Insert before #dUserChip if present, else at the end of topbar
    var chip = document.getElementById('dUserChip');
    if (chip && chip.parentNode === bar) bar.insertBefore(wrap, chip);
    else bar.appendChild(wrap);

    var btn = document.getElementById('dNotifBtn');
    var pop = document.getElementById('dNotifPop');

    btn.addEventListener('click', function(e){
      e.stopPropagation();
      var open = !pop.hasAttribute('hidden');
      if (open) { pop.setAttribute('hidden',''); return; }
      pop.removeAttribute('hidden');
      renderNotifList();
      // Mark all as read on open (only if there are unread)
      if (_notifUnread > 0) {
        SCX.api('/api/notifications.php', {action:'mark_all_read'}).then(function(){
          _notifUnread = 0;
          updateNotifBadge();
          _notifItems = _notifItems.map(function(n){ n.is_read = 1; return n; });
          renderNotifList();
        });
      }
    });
    // Close on outside click / Esc
    document.addEventListener('click', function(e){
      if (!pop.hasAttribute('hidden') && !wrap.contains(e.target)) pop.setAttribute('hidden','');
    });
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && !pop.hasAttribute('hidden')) pop.setAttribute('hidden','');
    });
    document.getElementById('dNotifReadAll').addEventListener('click', function(e){
      e.stopPropagation();
      SCX.api('/api/notifications.php', {action:'mark_all_read'}).then(function(){
        _notifUnread = 0;
        _notifItems = _notifItems.map(function(n){ n.is_read = 1; return n; });
        updateNotifBadge();
        renderNotifList();
      });
    });

    pollNotif();
    if (_notifPollTimer) clearInterval(_notifPollTimer);
    _notifPollTimer = setInterval(pollNotif, 30000);
  };

  function updateNotifBadge(){
    var b = document.getElementById('dNotifBadge');
    if (!b) return;
    if (_notifUnread > 0) {
      b.removeAttribute('hidden');
      b.textContent = _notifUnread > 99 ? '99+' : String(_notifUnread);
    } else {
      b.setAttribute('hidden','');
    }
  }

  function renderNotifList(){
    var list = document.getElementById('dNotifList');
    if (!list) return;
    if (!_notifItems.length) {
      list.innerHTML = '<div class="d-empty">Пока ничего нет.</div>';
      return;
    }
    list.innerHTML = _notifItems.map(function(n){
      var type = (n.type || 'info');
      var unread = +n.is_read === 0;
      var title = n.title ? '<div class="d-notif-title">' + SCX.escapeHtml(n.title) + '</div>' : '';
      return '<div class="d-notif-item type-' + SCX.escapeHtml(type) + (unread ? ' is-unread' : '') + '">'
        + (unread ? '<span class="d-notif-dot" aria-hidden="true"></span>' : '')
        + title
        + '<div class="d-notif-body">' + SCX.escapeHtml(n.body || '') + '</div>'
        + '<div class="d-notif-date">' + SCX.fmtDate(n.created_at) + '</div>'
      + '</div>';
    }).join('');
  }

  function pollNotif(){
    SCX.api('/api/notifications.php?action=list').then(function(d){
      if (!d || !d.ok) return;
      _notifItems = d.items || [];
      _notifUnread = +(d.unread || 0);
      updateNotifBadge();
      // re-render only if popover is open
      var pop = document.getElementById('dNotifPop');
      if (pop && !pop.hasAttribute('hidden')) renderNotifList();
    }).catch(function(){ /* network blip — silent */ });
  }

  // ── News badge on Home nav-item (disabled by request) ──
  SCX.loadNewsBadge = function(){
    var link = document.querySelector('.d-nav-item[data-page="home"]');
    if (!link) return Promise.resolve();
    var old = link.querySelector('.d-nav-badge'); if (old) old.remove();
    return Promise.resolve();
  };

  // ── News modal: вызывается с home.html ──
  SCX.openNewsModal = function(){
    var bg = document.getElementById('mNews');
    if (!bg) {
      bg = document.createElement('div');
      bg.id = 'mNews'; bg.className = 'd-modal-bg';
      bg.innerHTML = '<div class="d-modal" style="max-width:680px">'
        + '<button class="d-modal-close" data-close>✕</button>'
        + '<h3>Все новости</h3>'
        + '<div id="mNewsList" style="margin-top:14px;max-height:60vh;overflow-y:auto;display:flex;flex-direction:column;gap:10px"></div>'
        + '<button class="d-btn" id="mNewsMarkAll" style="margin-top:14px">Прочитать всё</button>'
        + '</div>';
      document.body.appendChild(bg);
      bg.addEventListener('click', function(e){ if (e.target===bg || e.target.dataset.close!==undefined) bg.classList.remove('show'); });
      bg.querySelector('[data-close]').onclick = function(){ bg.classList.remove('show'); };
      document.getElementById('mNewsMarkAll').onclick = function(){
        SCX.api('/api/news.php', {action:'mark_all_read'}).then(function(){
          SCX.openNewsModal(); SCX.loadNewsBadge();
        });
      };
    }
    bg.classList.add('show');
    var list = document.getElementById('mNewsList');
    list.innerHTML = '<div class="d-empty">Загрузка…</div>';
    SCX.api('/api/news.php?action=list&limit=50').then(function(d){
      if (!d || !d.ok) { list.innerHTML = '<div class="d-empty">Ошибка</div>'; return; }
      var posts = d.posts || [];
      if (!posts.length) { list.innerHTML = '<div class="d-empty">Новостей пока нет.</div>'; return; }
      list.innerHTML = posts.map(function(p){ return SCX.renderNewsItem(p, true); }).join('');
      // отмечаем прочтение при показе модалки
      posts.forEach(function(p){ if (!p.is_read) SCX.api('/api/news.php', {action:'mark_read', post_id:p.id}); });
      setTimeout(SCX.loadNewsBadge, 400);
    });
  };

  SCX.renderNewsItem = function(p, full){
    var kindCls = 'kind-' + (p.kind || 'news');
    var pinned = +p.is_pinned ? '<span class="d-pill ok" style="margin-left:6px">📌</span>' : '';
    var unread = !p.is_read ? '<span class="d-news-dot" title="Новое"></span>' : '';
    return '<article class="d-news-item ' + kindCls + (p.is_read ? '' : ' is-unread') + '">'
      + unread
      + '<div class="d-news-h"><b>' + SCX.escapeHtml(p.title) + '</b>' + pinned + '<span class="d-news-date">' + SCX.fmtDate(p.created_at) + '</span></div>'
      + '<div class="d-news-body">' + (full ? p.body : (p.body || '').replace(/<[^>]+>/g,'').slice(0, 180) + '…') + '</div>'
      + '</article>';
  };
})();
