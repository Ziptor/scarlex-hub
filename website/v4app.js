// Preloader
window.addEventListener('load',function(){setTimeout(function(){document.getElementById('preloader').classList.add('done')},500)});

// Session check
(function(){fetch('api/check_session.php',{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){if(d&&d.ok&&d.user){var b=document.getElementById('loginNavBtn');if(b){b.textContent='👤 '+d.user.username;b.href='dashboard.html';b.classList.remove('nav-cta');b.style.cssText='display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:10px;color:var(--white);font-size:13px;font-weight:600;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.14);transition:background .2s,border-color .2s';b.onmouseenter=function(){b.style.background='rgba(255,255,255,.08)';b.style.borderColor='rgba(255,255,255,.25)'};b.onmouseleave=function(){b.style.background='rgba(255,255,255,.04)';b.style.borderColor='rgba(255,255,255,.14)'}}}}).catch(function(){})})();

// Toast
function toast(m){var t=document.getElementById('toast');t.textContent=m;t.classList.add('show');clearTimeout(t._t);t._t=setTimeout(function(){t.classList.remove('show')},1800)}
function copyCode(which){var el=document.getElementById(which==='after'?'codeAfter':'codeBefore');if(el)navigator.clipboard.writeText(el.innerText).then(function(){toast(curLang==='en'?'Copied!':'Скопировано!')})}
function copyWellflowCurl(){var el=document.getElementById('ctaCurlText');if(el)navigator.clipboard.writeText(el.textContent).then(function(){toast(curLang==='en'?'Copied!':'Скопировано!')})}

// Code tabs
var curCodeTab='python';
function updateCodeTabIndicator(){
  var nav=document.querySelector('.code-tabs-nav');
  var act=nav&&nav.querySelector('.code-tab.act');
  var ind=document.getElementById('codeTabIndicator');
  if(!nav||!act||!ind) return;
  ind.style.width=act.offsetWidth+'px';
  ind.style.left=act.offsetLeft+'px';
  ind.style.opacity='1';
}
function renderCode(){
  var d=CODE[curCodeTab];
  document.getElementById('codeBefore').innerHTML=d.before;
  document.getElementById('codeAfter').innerHTML=d.after;
}
document.querySelectorAll('.code-tab').forEach(function(b){
  b.addEventListener('click',function(){
    curCodeTab=b.getAttribute('data-ctab');
    document.querySelectorAll('.code-tab').forEach(function(x){x.classList.toggle('act',x===b)});
    renderCode();
    updateCodeTabIndicator();
  });
});
renderCode();
updateCodeTabIndicator();
window.addEventListener('resize',updateCodeTabIndicator);

// Pricing
function renderPr(arr,id){
  var chk='✓';var el=document.getElementById(id);el.innerHTML='';
  arr.forEach(function(p){
    var badge=p.badge?'<div class="pr-badge">'+p.badge+'</div>':'';
    var feats='';p.feats.forEach(function(f){feats+='<li>'+f+'</li>'});
    var sav=p.sav?'<div class="pr-save">'+p.sav+'</div>':'';
    var priceHtml=p.price==='0'?'<span class="free">Free</span>':p.price==='Custom'?'Custom':p.price+'<span class="cur">'+p.cur+'</span>';
    el.innerHTML+='<div class="surface pr-card '+p.cls+' fi">'+badge+'<div class="pr-name">'+p.nm+'</div><div class="pr-price">'+priceHtml+'</div><div class="pr-tokens">'+p.tokens+'</div><div class="pr-per">'+p.per+'</div>'+sav+'<ul class="pr-feats">'+feats+'</ul><a class="'+p.btnCls+'" href="authorization.html?plan='+p.plan+'">'+p.btnText+'</a></div>';
  });
}
renderPr(PRICES_MAIN,'prMain');
renderPr(PRICES_MORE,'prMore');

// Динамическая подгрузка актуальных цен из БД (admin → products).
// Если запрос успешен — перерисовываем карточки, сохраняя визуальную структуру.
(function syncPricesFromBackend(){
  fetch('api/products_public.php',{credentials:'same-origin'})
    .then(function(r){return r.json()})
    .then(function(d){
      if(!d||!d.ok||!Array.isArray(d.products)) return;
      // Маппинг slug → fallback-карточка из v4data, чтобы сохранить feats/cls/btn.
      var fallback={};
      [PRICES_MAIN,PRICES_MORE].forEach(function(arr){arr.forEach(function(p){if(p.plan){fallback[p.plan]=p}})});
      var main=[],more=[];
      d.products.forEach(function(p){
        var slug='plan-'+(p.slug||'').replace(/^plan-/,'');
        if(p.is_trial) slug='free';
        var fb=fallback[p.slug==='trial'?'free':p.slug]||fallback['plan-25m']||{};
        var card={
          nm: p.is_trial?'Trial':(p.name||p.slug),
          price: p.is_trial?'0':String(p.price_rub).replace(/\.00$/,'').replace(/(\d)(?=(\d{3})+(?!\d))/g,'$1 '),
          cur: p.is_trial?'':'₽',
          tokens: p.is_trial?'1M токенов':(p.tokens/1000000).toFixed(0).replace(/(\d)(?=(\d{3})+(?!\d))/g,'$1 ')+'M токенов',
          per: p.is_trial?'1 раз на аккаунт':('≈ '+p.price_per_1m.toFixed(2).replace('.',',').replace(/,00$/,'')+' ₽ / 1M'),
          sav: fb.sav||'',
          feats: fb.feats||['Все модели Claude','OpenAI-совместимо','SBP · Карта · ЮMoney · Crypto'],
          cls: fb.cls||'',
          badge: p.badge||fb.badge||'',
          btnCls: fb.btnCls||'btn-g',
          btnText: fb.btnText||(p.is_trial?'Активировать триал':'Купить'),
          plan: p.slug
        };
        // Распределяем как в исходных PRICES_MAIN: trial, 25m, 50m → main; остальные → more.
        if(p.slug==='trial'||p.slug==='plan-25m'||p.slug==='plan-50m') main.push(card);
        else more.push(card);
      });
      if(main.length) renderPr(main,'prMain');
      if(more.length) renderPr(more,'prMore');
      initObs();
    })
    .catch(function(){});
})();

// Show more pricing
document.getElementById('showMoreBtn').addEventListener('click',function(){
  var m=document.getElementById('prMore');var b=this;
  if(m.classList.contains('show')){m.classList.remove('show');b.textContent=I18N[curLang]['pr.showAll']||'Все тарифы ↓'}
  else{m.classList.add('show');b.textContent=I18N[curLang]['pr.hideAll']||'Скрыть ↑';initObs()}
});

// FAQ
function renderFaq(){
  var el=document.getElementById('faqList');el.innerHTML='';
  FAQS.forEach(function(f,i){
    el.innerHTML+='<div class="faq-item" id="fq'+i+'"><button class="faq-q" onclick="toggleFaq('+i+')"><span>'+f[0]+'</span><span class="plus">+</span></button><div class="faq-a"><div class="faq-a-inner">'+f[1]+'</div></div></div>';
  });
}
function toggleFaq(i){
  var el=document.getElementById('fq'+i);
  el.classList.toggle('open');
}
renderFaq();

// Fade-in observer
function initObs(){
  if(!('IntersectionObserver' in window)){document.querySelectorAll('.fi').forEach(function(el){el.classList.add('v')});return}
  var io=new IntersectionObserver(function(e){e.forEach(function(x){if(x.isIntersecting){x.target.classList.add('v');io.unobserve(x.target)}})},{rootMargin:'0px 0px -6% 0px',threshold:0.05});
  document.querySelectorAll('.fi:not(.v)').forEach(function(el){io.observe(el)});
}
initObs();

// i18n
var curLang='ru';
function applyLang(lang){
  curLang=lang;
  var d=I18N[lang]||I18N.ru;
  document.documentElement.lang=lang;
  document.querySelectorAll('[data-i18n]').forEach(function(el){var k=el.getAttribute('data-i18n');if(d[k]!=null)el.textContent=d[k]});
  document.querySelectorAll('[data-i18n-html]').forEach(function(el){var k=el.getAttribute('data-i18n-html');if(d[k]!=null)el.innerHTML=d[k]});
  document.querySelectorAll('.nav-lang').forEach(function(b){b.classList.toggle('act',b.getAttribute('data-lang')===lang)});
  try{localStorage.setItem('scarlex_lang',lang)}catch(e){}
}
document.querySelectorAll('.nav-lang').forEach(function(b){
  b.addEventListener('click',function(){applyLang(b.getAttribute('data-lang'))});
});
var saved='ru';try{saved=localStorage.getItem('scarlex_lang')||'ru'}catch(e){}
applyLang(saved);

// Responsive numbers grid
var numHero=document.querySelector('.num-hero');
if(numHero&&window.innerWidth<768)numHero.style.gridTemplateColumns='1fr';

// Animated background blobs (dimmed)
(function(){
  var canvas=document.getElementById('particleCanvas');
  if(!canvas) return;
  var ctx=canvas.getContext('2d'); if(!ctx) return;
  if(window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var width=window.innerWidth, height=window.innerHeight;
  var blobs=[];
  var palette=[
    {r:255,g:255,b:255},
    {r:180,g:200,b:255},
    {r:200,g:180,b:255},
    {r:255,g:220,b:200},
    {r:160,g:220,b:240}
  ];
  function mk(i){
    var c=palette[i%palette.length];
    return{
      x:Math.random()*width,y:Math.random()*height,
      baseRadius:240+Math.random()*220,
      vx:(Math.random()-0.5)*0.16,vy:(Math.random()-0.5)*0.16,
      color:c, alpha:0.10+Math.random()*0.08,
      phase:Math.random()*Math.PI*2, phaseSpeed:0.0025+Math.random()*0.004,
      driftAmp:60+Math.random()*80
    };
  }
  function resize(){
    var dpr=Math.min(window.devicePixelRatio||1,2);
    width=window.innerWidth; height=window.innerHeight;
    canvas.width=Math.floor(width*dpr); canvas.height=Math.floor(height*dpr);
    canvas.style.width=width+'px'; canvas.style.height=height+'px';
    ctx.setTransform(dpr,0,0,dpr,0,0);
    var area=width*height;
    var count=Math.max(4,Math.min(7,Math.floor(area/320000)));
    blobs=[]; for(var i=0;i<count;i++) blobs.push(mk(i));
  }
  function step(){
    ctx.globalCompositeOperation='source-over';
    ctx.fillStyle='rgba(10,10,11,0.22)';
    ctx.fillRect(0,0,width,height);
    ctx.globalCompositeOperation='lighter';
    for(var i=0;i<blobs.length;i++){
      var b=blobs[i];
      b.phase+=b.phaseSpeed;
      var dxd=Math.sin(b.phase)*b.driftAmp;
      var dyd=Math.cos(b.phase*0.85)*b.driftAmp*0.7;
      b.x+=b.vx; b.y+=b.vy;
      var m=b.baseRadius+100;
      if(b.x<-m)b.x=width+m; if(b.x>width+m)b.x=-m;
      if(b.y<-m)b.y=height+m; if(b.y>height+m)b.y=-m;
      var pulse=1+Math.sin(b.phase*1.4)*0.15;
      var r=b.baseRadius*pulse, px=b.x+dxd, py=b.y+dyd;
      var grad=ctx.createRadialGradient(px,py,0,px,py,r);
      var c=b.color, a=b.alpha;
      grad.addColorStop(0,'rgba('+c.r+','+c.g+','+c.b+','+a+')');
      grad.addColorStop(0.4,'rgba('+c.r+','+c.g+','+c.b+','+(a*0.35)+')');
      grad.addColorStop(1,'rgba('+c.r+','+c.g+','+c.b+',0)');
      ctx.fillStyle=grad;
      ctx.beginPath(); ctx.arc(px,py,r,0,Math.PI*2); ctx.fill();
    }
    ctx.globalCompositeOperation='source-over';
    requestAnimationFrame(step);
  }
  resize(); step();
  window.addEventListener('resize', resize);
})();

// Live hero panel: both counters tick by +1, smoothly. Burst, then pause.
(function(){
  var anthEl=document.getElementById('liveAnth'),scxEl=document.getElementById('liveScx');
  if(!anthEl||!scxEl) return;
  var fmt=function(n){return Math.round(n).toLocaleString('ru-RU').replace(/,/g,' ')};

  var curAnth=1500, curScx=50;
  var tgtAnth=curAnth, tgtScx=curScx;
  var ratio=30;

  function render(){
    anthEl.textContent=fmt(curAnth);
    scxEl.textContent=fmt(curScx);
  }

  function nextBurst(){
    ratio+=(Math.random()-0.5)*0.25;
    if(ratio<28) ratio=28; if(ratio>32) ratio=32;
    var burst=4+Math.floor(Math.random()*4);     // 4..7 единиц scarlex
    tgtScx=curScx+burst;
    tgtAnth=curAnth+Math.round(burst*ratio);
    if(tgtAnth>4800){curAnth=1500; curScx=50; tgtAnth=curAnth; tgtScx=curScx; render(); setTimeout(nextBurst,800); return;}
    burstStep();
  }

  function burstStep(){
    if(curAnth>=tgtAnth && curScx>=tgtScx){
      setTimeout(nextBurst, 1400+Math.random()*900);
      return;
    }
    var anthLeft=tgtAnth-curAnth, scxLeft=tgtScx-curScx;
    if(curAnth<tgtAnth) curAnth+=1;
    var anthLevel=tgtAnth-Math.round(scxLeft*ratio);
    if(curScx<tgtScx && curAnth>=anthLevel) curScx+=1;
    render();
    setTimeout(burstStep, 10+Math.random()*8);
  }

  render();
  setTimeout(nextBurst, 600);
})();
