/* === Pricing (real, per AGENTS.md) === */
var PRICES_MAIN=[
{nm:'Trial',price:'0',cur:'',tokens:'1M токенов',per:'1 раз на аккаунт',feats:['Все модели Claude','Тест API','Без оплаты'],cls:'',btnCls:'btn-g',btnText:'Получить ключ',plan:'free'},
{nm:'25M',price:'290',cur:'₽',tokens:'25M токенов',per:'≈ 11,6 ₽ / 1M',sav:'Старт для рабочего проекта',feats:['Все модели Claude','OpenAI-совместимый','SBP · карта · ЮMoney · Crypto'],cls:'pop',badge:'Популярный',btnCls:'btn-p',btnText:'Купить',plan:'plan-25m'},
{nm:'50M',price:'540',cur:'₽',tokens:'50M токенов',per:'≈ 10,8 ₽ / 1M',sav:'Лучший баланс цена / объём',feats:['Все модели Claude','OpenAI-совместимый','Приоритет в очереди'],cls:'',btnCls:'btn-g',btnText:'Купить',plan:'plan-50m'}
];
var PRICES_MORE=[
{nm:'10M',price:'190',cur:'₽',tokens:'10M токенов',per:'≈ 19 ₽ / 1M',feats:['Все модели Claude','OpenAI-совместимый','Подходит для небольших задач'],cls:'',btnCls:'btn-g',btnText:'Купить',plan:'plan-10m'},
{nm:'250M',price:'2 590',cur:'₽',tokens:'250M токенов',per:'≈ 10,4 ₽ / 1M',sav:'−45% к Trial-аналогу',feats:['Все модели Claude','Приоритет','Sticky-сессии 20 мин'],cls:'',btnCls:'btn-g',btnText:'Купить',plan:'plan-250m'},
{nm:'500M',price:'4 900',cur:'₽',tokens:'500M токенов',per:'≈ 9,8 ₽ / 1M',sav:'Минимальная цена за 1M',feats:['Все модели Claude','Высший приоритет','Менеджер в Telegram'],cls:'',btnCls:'btn-g',btnText:'Купить',plan:'plan-500m'}
];

/* === FAQ === */
var FAQS=[
['Это точно те же модели Claude?','Да. Запросы идут к официальным моделям Anthropic. Никаких локальных моделей или подделок — Claude Opus 4.7, Sonnet 4.6, Sonnet 4, Haiku 3.5 — те же самые.'],
['Как оплатить из России?','СБП, карта российского банка, ЮMoney, CryptoBot (USDT, BTC, TON). Без VPN и иностранных карт.'],
['Работает с моей IDE?','Да. Эндпоинт OpenAI-совместимый. Cursor, Cline, Aider, Claude Code, Continue, любой OpenAI SDK — указываете <code>base_url</code> и API-ключ.'],
['Как быстро активируется ключ?','Обычно сразу после покупки и активации. Процесс рассчитан на быстрый старт без сложной ручной настройки.'],
['Поддерживается streaming и tool calling?','Да. Streaming, tool calling, system prompts работают. Sticky-сессии 20 минут — один и тот же бот держит контекст между запросами с одного ключа.']
];

/* === Code examples === */
var CODE={
python:{
before:'<span class="kw">from</span> openai <span class="kw">import</span> OpenAI\n\nclient = OpenAI(\n    api_key=os.environ[<span class="st">"OPENAI_API_KEY"</span>],\n)',
after:'<span class="kw">from</span> openai <span class="kw">import</span> OpenAI\n\nclient = OpenAI(\n    api_key=os.environ[<span class="st">"SCARLEX_API_KEY"</span>],\n    base_url=<span class="st">"https://api.scarlex.ru/v1"</span>,\n)'
},
node:{
before:'<span class="kw">import</span> OpenAI <span class="kw">from</span> <span class="st">"openai"</span>;\n\n<span class="kw">const</span> client = <span class="kw">new</span> OpenAI({\n  apiKey: process.env.<span class="fn">OPENAI_API_KEY</span>,\n});',
after:'<span class="kw">import</span> OpenAI <span class="kw">from</span> <span class="st">"openai"</span>;\n\n<span class="kw">const</span> client = <span class="kw">new</span> OpenAI({\n  apiKey: process.env.<span class="fn">SCARLEX_API_KEY</span>,\n  baseURL: <span class="st">"https://api.scarlex.ru/v1"</span>,\n});'
},
curl:{
before:'curl https://api.openai.com/v1/chat/completions \\\n  -H <span class="st">"Authorization: Bearer $OPENAI_API_KEY"</span> \\\n  -H <span class="st">"Content-Type: application/json"</span> \\\n  -d <span class="st">\'{"model":"gpt-4","messages":[...]}\'</span>',
after:'curl https://api.scarlex.ru/v1/chat/completions \\\n  -H <span class="st">"Authorization: Bearer $SCARLEX_KEY"</span> \\\n  -H <span class="st">"Content-Type: application/json"</span> \\\n  -d <span class="st">\'{"model":"claude-opus-4.7","messages":[...]}\'</span>'
}
};

/* === i18n === */
var I18N={
ru:{
'brand':'Scarlex V2','nav.features':'Возможности','nav.numbers':'Цифры','nav.integration':'Интеграция','nav.pricing':'Цены','nav.faq':'FAQ','nav.login':'Войти',
'trust.eyebrow':'ИНТЕГРИРУЕТСЯ С',
'hero.title':'Платите <span class="t-muted">в 30×</span> меньше за Claude&nbsp;Code','hero.sub':'Те же модели <b>Claude Opus 4.7, Sonnet 4.6, Sonnet 4, Haiku 3.5</b> от Anthropic. OpenAI-совместимый эндпоинт, оплата в рублях.','hero.cta':'Протестировать →',
'hero.card.label':'Расход за сессию · Claude Opus 4.7','hero.card.official':'Через Anthropic','hero.card.scarlex':'Через Scarlex V2',
'feat.eyebrow':'ВОЗМОЖНОСТИ','feat.title':'Один ключ. <span class="t-muted">Все модели Claude, все способы оплаты.</span>',
'feat.1.title':'Прозрачный биллинг','feat.1.desc':'Списания по реальным токенам с множителем модели. История запросов и расход — в личном кабинете.',
'feat.2.title':'OpenAI и Anthropic API','feat.2.desc':'Два совместимых эндпоинта: <b>/v1/chat/completions</b> и <b>/v1/messages</b>. Работает с OpenAI SDK, Anthropic SDK, Claude Code, Cursor, Cline, Aider, OpenCode.',
'feat.3.title':'Все модели Claude','feat.3.desc':'Opus 4.7, Opus 4.6, Sonnet 4.6, Sonnet 4, Sonnet Thinking, Haiku 3.5. Переключение через параметр model.',
'feat.4.title':'Оплата из России','feat.4.desc':'СБП, карта российского банка, ЮMoney, CryptoBot (USDT, BTC, TON). Без валютных конвертаций и VPN.',
'num.eyebrow':'ЦИФРЫ','num.subtitle':'ДЕШЕВЛЕ ОФИЦИАЛЬНОГО ANTHROPIC API','num.desc':'Anthropic берёт ~300 ₽ за миллион токенов Opus. Через Scarlex V2 — от 9,8 ₽. Разница до 30×.',
'num.s1.label':'AVG LATENCY','num.s1.val':'до 150×','num.s1.sub':'','num.s1.metric':'2432 мс','num.s1.metricSub':'среднее время ответа',
'num.s2.label':'ЗАПРОСОВ ЗА 30 ДНЕЙ','num.s2.val':'5','num.s2.sub':'Opus, Sonnet, Haiku','num.s2.metric':'400тыс','num.s2.metricSub':'через Scarlex',
'num.s3.label':'ДОСТУПНО МОДЕЛЕЙ','num.s3.val':'OpenAI SDK','num.s3.sub':'drop-in замена эндпоинта','num.s3.metric':'5+','num.s3.metricSub':'Claude Opus, Sonnet, Haiku',
'num.cmp.head':'Claude Opus 4.7 / 1M токенов','num.cmp.other':'ANTHROPIC DIRECT','num.cmp.us':'SCARLEX V2',
'int.eyebrow':'ИНТЕГРАЦИЯ','int.title':'Одна строка. <span class="t-muted">Без миграции.</span>','int.sub':'Scarlex V2 совместим с OpenAI SDK. Меняете <b>base_url</b> и ключ. Остальные 99% кода работают как раньше.','int.before':'ДО','int.after':'ПОСЛЕ','int.copy':'⎘ Copy',
'pr.eyebrow':'ЦЕНЫ','pr.title':'Платите за токены, <span class="t-muted">а не за подписку.</span>','pr.showAll':'Все тарифы ↓','pr.hideAll':'Скрыть ↑',
'faq.eyebrow':'ОТВЕТЫ','faq.title':'Частые<br>вопросы.','faq.sub':'Не нашли ответ? Напишите в <a href="https://t.me/scarlex_support">Telegram</a>',
'cta.title':'Начни <span class="t-muted">экономить</span><br>сейчас.','cta.sub':'Регистрация за 30 секунд. Работает с любой IDE и SDK.','cta.btn':'Получить API ключ →','cta.copy':'Copy','cta.docs':'или прочитайте документацию',
'footer.brand':'Scarlex','footer.tagline':'AI API Provider','footer.product':'Продукт','footer.pricing':'Цены','footer.models':'Доступные модели','footer.docs':'Документация','footer.resources':'Ресурсы','footer.faq':'Частые вопросы','footer.apiGuide':'API Guide','footer.community':'Сообщество','footer.supportTitle':'Поддержка','footer.support':'@scarlex_support','footer.copy':'© 2026 Scarlex. Все права защищены.','footer.terms':'Публичная оферта','copied':'Скопировано!'
},
en:{
'brand':'Scarlex V2','nav.features':'Features','nav.numbers':'Numbers','nav.integration':'Integration','nav.pricing':'Pricing','nav.faq':'FAQ','nav.login':'Sign In',
'trust.eyebrow':'INTEGRATES WITH',
'hero.title':'Pay <span class="t-muted">30×</span> less for Claude&nbsp;Code','hero.sub':'Same <b>Claude Opus 4.7, Sonnet 4.6, Sonnet 4, Haiku 3.5</b> models from Anthropic. OpenAI-compatible endpoint, RUB billing.','hero.cta':'Try free →',
'hero.card.label':'Session spend · Claude Opus 4.7','hero.card.official':'Via Anthropic','hero.card.scarlex':'Via Scarlex V2',
'feat.eyebrow':'FEATURES','feat.title':'One key. <span class="t-muted">All Claude models, all payment methods.</span>',
'feat.1.title':'Transparent Billing','feat.1.desc':'Per-token billing with per-model multiplier. Full request history and spend in dashboard.',
'feat.2.title':'OpenAI & Anthropic API','feat.2.desc':'Two compatible endpoints: <b>/v1/chat/completions</b> and <b>/v1/messages</b>. Works with OpenAI SDK, Anthropic SDK, Claude Code, Cursor, Cline, Aider, OpenCode.',
'feat.3.title':'All Claude Models','feat.3.desc':'Opus 4.7, Opus 4.6, Sonnet 4.6, Sonnet 4, Sonnet Thinking, Haiku 3.5. Switch via the model parameter.',
'feat.4.title':'Russian Payments','feat.4.desc':'SBP, Russian cards, YooMoney, CryptoBot (USDT, BTC, TON). No FX conversions, no VPN.',
'num.eyebrow':'NUMBERS','num.subtitle':'CHEAPER THAN OFFICIAL ANTHROPIC API','num.desc':'Anthropic charges about ₽300 per million Opus tokens. Scarlex V2 — from ₽9.8. Up to 30× difference.',
'num.s1.label':'AVG LATENCY','num.s1.val':'up to 150×','num.s1.sub':'','num.s1.metric':'2432 ms','num.s1.metricSub':'average response time',
'num.s2.label':'REQUESTS IN 30 DAYS','num.s2.val':'5','num.s2.sub':'Opus, Sonnet, Haiku','num.s2.metric':'400k','num.s2.metricSub':'via Scarlex',
'num.s3.label':'MODELS AVAILABLE','num.s3.val':'OpenAI SDK','num.s3.sub':'drop-in endpoint','num.s3.metric':'5+','num.s3.metricSub':'Claude Opus, Sonnet, Haiku',
'num.cmp.head':'Claude Opus 4.7 / 1M tokens','num.cmp.other':'ANTHROPIC DIRECT','num.cmp.us':'SCARLEX V2',
'int.eyebrow':'INTEGRATION','int.title':'One line. <span class="t-muted">No migration.</span>','int.sub':'Scarlex V2 is OpenAI SDK compatible. Change <b>base_url</b> and key. 99% of your code stays the same.','int.before':'BEFORE','int.after':'AFTER','int.copy':'⎘ Copy',
'pr.eyebrow':'PRICING','pr.title':'Pay for tokens, <span class="t-muted">not for a subscription.</span>','pr.showAll':'All plans ↓','pr.hideAll':'Hide ↑',
'faq.eyebrow':'ANSWERS','faq.title':'Frequently<br>asked.','faq.sub':'Can\'t find an answer? Write us on <a href="https://t.me/scarlex_support">Telegram</a>',
'cta.title':'Start <span class="t-muted">saving</span><br>now.','cta.sub':'Registration takes 30 seconds. Works with any IDE and SDK.','cta.btn':'Get API Key →','cta.copy':'Copy','cta.docs':'or read the documentation',
'footer.brand':'Scarlex','footer.tagline':'AI API Provider','footer.product':'Product','footer.pricing':'Pricing','footer.models':'Available models','footer.docs':'Documentation','footer.resources':'Resources','footer.faq':'FAQ','footer.apiGuide':'API Guide','footer.community':'Community','footer.supportTitle':'Support','footer.support':'@scarlex_support','footer.copy':'© 2026 Scarlex. All rights reserved.','footer.terms':'Public Offer','copied':'Copied!'
}
};
