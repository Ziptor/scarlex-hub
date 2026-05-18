# Indexv3 React SPA Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a new React + Vite product-style `indexv3` SPA that is visually much closer to `wellflow.dev` while preserving Scarlex business facts, auth flow, RU/EN support, and developer-focused interactions.

**Architecture:** This implementation creates a fresh front-end app instead of extending the existing static HTML drafts. Business content moves into structured data modules, the page is composed from React screen/components, and only useful behaviors from the old landing are ported into a calmer, app-like SPA shell.

**Tech Stack:** React, Vite, vanilla CSS, browser Fetch API, localStorage, Clipboard API

---

## File Map

- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\package.json`
  - Vite app manifest and scripts.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\vite.config.js`
  - Vite config for local dev/build.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\index.html`
  - Vite entry HTML.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\main.jsx`
  - React mount entry.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\App.jsx`
  - Root app composition.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\lib\content.js`
  - Structured content for models, plans, FAQ, code snippets, trust facts, CTA links.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\lib\i18n.js`
  - RU/EN dictionaries and translation helpers.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\lib\session.js`
  - Session fetch helper for `api/check_session.php`.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\app.css`
  - Reset, variables, foundation.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\layout.css`
  - Shell, nav, section and container layout.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\components.css`
  - Shared panel/card/button/component styles.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\screens.css`
  - Per-screen styling.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\layout\TopNav.jsx`
  - Sticky product header with auth and language state.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\layout\Footer.jsx`
  - Minimal footer.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\hero\HeroProduct.jsx`
  - Left-side product messaging and CTA area.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\hero\HeroDemoPanel.jsx`
  - Right-side product panel with typewriter/demo UI.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\models\ModelExperience.jsx`
  - Interactive capability screen.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\models\ModelCard.jsx`
  - Model selector card.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\dev\DeveloperFlow.jsx`
  - Developer integration screen.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\dev\CodeTabs.jsx`
  - Tabbed code viewer with copy support.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\pricing\PricingSurface.jsx`
  - Pricing screen.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\pricing\PlanCard.jsx`
  - Plan card component.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\trust\TrustAndFAQ.jsx`
  - Compact trust and FAQ screen.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\trust\FAQItem.jsx`
  - Accordion item.
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\cta\FinalAction.jsx`
  - Closing CTA screen.

## Constraints

- Build a fresh app in a new directory rather than mutating `indexv2.html` or the current `indexv3.html`.
- Preserve business facts and working user flows from the old landing.
- Do not use a heavy UI component framework.
- Keep the app single-route unless implementation reveals a real need for client routing.
- Keep interactions stable and minimal; no `mix-blend-mode`, no spectacle animations, no Three.js.

### Task 1: Scaffold the React + Vite app shell

**Files:**
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\package.json`
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\vite.config.js`
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\index.html`
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\main.jsx`
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\App.jsx`

- [ ] **Step 1: Verify the target parent directory exists**

Run:

```powershell
Test-Path -LiteralPath "C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02"
```

Expected: `True`

- [ ] **Step 2: Create the SPA root directory**

Run:

```powershell
New-Item -ItemType Directory -Path "C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa" -Force
```

Expected: directory created or already exists.

- [ ] **Step 3: Create `package.json`**

Create `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\package.json` with:

```json
{
  "name": "scarlex-indexv3-spa",
  "private": true,
  "version": "0.0.1",
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "preview": "vite preview"
  },
  "dependencies": {
    "react": "^18.3.1",
    "react-dom": "^18.3.1"
  },
  "devDependencies": {
    "vite": "^5.4.10"
  }
}
```

- [ ] **Step 4: Create `vite.config.js`**

Create `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\vite.config.js` with:

```js
import { defineConfig } from 'vite'

export default defineConfig({
  server: {
    host: '127.0.0.1',
    port: 4173,
  },
  preview: {
    host: '127.0.0.1',
    port: 4173,
  },
})
```

- [ ] **Step 5: Create `index.html`**

Create `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\index.html` with:

```html
<!doctype html>
<html lang="ru">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Scarlex V3</title>
    <meta
      name="description"
      content="Scarlex V3 — продуктовый Claude API SPA без VPN, с оплатой картой, СБП и CryptoBot."
    />
    <link rel="icon" type="image/png" href="../claude.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap"
      rel="stylesheet"
    />
  </head>
  <body>
    <div id="root"></div>
    <script type="module" src="/src/main.jsx"></script>
  </body>
</html>
```

- [ ] **Step 6: Create the initial React entry files**

Create `src/main.jsx` with:

```jsx
import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App'
import './styles/app.css'
import './styles/layout.css'
import './styles/components.css'
import './styles/screens.css'

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
)
```

Create `src/App.jsx` with:

```jsx
export default function App() {
  return <div>Scarlex V3 SPA bootstrapped.</div>
}
```

- [ ] **Step 7: Install dependencies**

Run:

```powershell
npm install
```

Workdir: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa`

Expected: install completes successfully and `node_modules` + lockfile are created.

- [ ] **Step 8: Run an initial production build**

Run:

```powershell
npm run build
```

Workdir: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa`

Expected: Vite build succeeds and creates `dist/`.

- [ ] **Step 9: Commit**

```bash
git add package.json package-lock.json vite.config.js index.html src/main.jsx src/App.jsx
git commit -m "feat: scaffold indexv3 react spa"
```

### Task 2: Create content, i18n, and session data layers

**Files:**
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\lib\content.js`
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\lib\i18n.js`
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\lib\session.js`

- [ ] **Step 1: Create `content.js` with structured business data**

Create `src/lib/content.js` with:

```js
export const models = [
  {
    id: 'claude-opus-4.7',
    name: 'Opus 4.7',
    tag: 'NEW',
    blurb: {
      ru: 'Самая мощная Claude-модель для сложного кода, архитектуры и reasoning.',
      en: 'The strongest Claude model for complex coding, architecture, and reasoning.',
    },
    meta: '200K context · top tier',
  },
  {
    id: 'claude-opus-4.6',
    name: 'Opus 4.6',
    tag: 'STABLE',
    blurb: {
      ru: 'Прошлое поколение топовой модели — стабильная и проверенная в проде.',
      en: 'Previous generation flagship — stable and proven in production.',
    },
    meta: '200K context · stable flagship',
  },
  {
    id: 'claude-sonnet-4-thinking',
    name: 'Sonnet 4 Thinking',
    tag: 'REASONING',
    blurb: {
      ru: 'Sonnet с расширенным мышлением для задач, где важна точность.',
      en: 'Extended-reasoning Sonnet for tasks where precision matters.',
    },
    meta: '200K context · deeper reasoning',
  },
  {
    id: 'claude-sonnet-4',
    name: 'Sonnet 4',
    tag: 'DEFAULT',
    blurb: {
      ru: 'Быстрый баланс цены, качества и скорости для повседневной работы.',
      en: 'Fast balance of quality, speed, and price for daily work.',
    },
    meta: '200K context · default pick',
  },
  {
    id: 'claude-haiku-3.5',
    name: 'Haiku 3.5',
    tag: 'FAST',
    blurb: {
      ru: 'Самая быстрая и дешёвая модель для коротких ответов и high-volume задач.',
      en: 'Fastest and cheapest model for short answers and high-volume tasks.',
    },
    meta: '200K context · fastest',
  },
]

export const pricingPlans = [
  { slug: 'trial', name: 'Trial', tokens: '1M', price: 'Бесплатно', perMillion: 'бесплатно', compare: '', badge: '1 на аккаунт', href: 'authorization.html?plan=trial' },
  { slug: 'starter-10m', name: 'Starter', tokens: '10M', price: '190 ₽', perMillion: '19 ₽/M', compare: '×79 дешевле Anthropic', badge: '', href: 'authorization.html?plan=starter_10m' },
  { slug: 'basic-25m', name: 'Basic', tokens: '25M', price: '290 ₽', perMillion: '11.6 ₽/M', compare: '×129 дешевле Anthropic', badge: 'Популярный', href: 'authorization.html?plan=basic_25m' },
  { slug: 'standard-50m', name: 'Standard', tokens: '50M', price: '540 ₽', perMillion: '10.8 ₽/M', compare: '×139 дешевле Anthropic', badge: '', href: 'authorization.html?plan=standard_50m' },
  { slug: 'pro-250m', name: 'Pro', tokens: '250M', price: '2 590 ₽', perMillion: '10.36 ₽/M', compare: '×144 дешевле Anthropic', badge: 'Лучший value', href: 'authorization.html?plan=pro_250m' },
  { slug: 'business-500m', name: 'Business', tokens: '500M', price: '4 900 ₽', perMillion: '9.8 ₽/M', compare: '×153 дешевле Anthropic', badge: 'Самый дешёвый /M', href: 'authorization.html?plan=business_500m' },
]

export const codeSnippets = {
  curl: `curl https://api.scarlex.ru/v1/chat/completions \\
  -H "Authorization: Bearer sk-scarlex-YOUR_KEY" \\
  -H "Content-Type: application/json" \\
  -d '{
    "model": "claude-sonnet-4",
    "messages": [{"role": "user", "content": "Привет!"}]
  }'`,
  python: `from openai import OpenAI

client = OpenAI(
    base_url="https://api.scarlex.ru/v1",
    api_key="sk-scarlex-YOUR_KEY",
)

resp = client.chat.completions.create(
    model="claude-sonnet-4",
    messages=[{"role": "user", "content": "Привет!"}],
)

print(resp.choices[0].message.content)`,
  javascript: `import OpenAI from 'openai'

const client = new OpenAI({
  baseURL: 'https://api.scarlex.ru/v1',
  apiKey: 'sk-scarlex-YOUR_KEY',
})

const resp = await client.chat.completions.create({
  model: 'claude-sonnet-4',
  messages: [{ role: 'user', content: 'Привет!' }],
})

console.log(resp.choices[0].message.content)`,
  opencode: `{
  "models": {
    "scarlex-sonnet": {
      "provider": "openai-compatible",
      "base_url": "https://api.scarlex.ru/v1",
      "api_key": "sk-scarlex-YOUR_KEY",
      "model": "claude-sonnet-4"
    }
  }
}`,
}

export const faqItems = [
  {
    q: { ru: 'Нужен ли VPN?', en: 'Do I need a VPN?' },
    a: { ru: 'Нет. Эндпоинт доступен напрямую из РФ.', en: 'No. The endpoint is directly accessible from Russia.' },
  },
  {
    q: { ru: 'Как оплатить?', en: 'How do I pay?' },
    a: { ru: 'СБП, карта РФ и CryptoBot.', en: 'SBP, Russian bank card, and CryptoBot.' },
  },
  {
    q: { ru: 'Какие модели есть?', en: 'Which models are available?' },
    a: { ru: '5 моделей Claude: Opus 4.7/4.6, Sonnet 4/Thinking, Haiku 3.5.', en: '5 Claude models: Opus 4.7/4.6, Sonnet 4/Thinking, Haiku 3.5.' },
  },
  {
    q: { ru: 'Какой rate limit?', en: 'What is the rate limit?' },
    a: { ru: 'По умолчанию 45 RPM на ключ.', en: 'Default is 45 RPM per key.' },
  },
]

export const trustFacts = [
  '99.9% uptime',
  'OpenAI-compatible',
  'Без VPN',
  'СБП / карта / CryptoBot',
  'RU support',
]

export const anthropicComparison = {
  ru: 'Anthropic Claude Opus 4.7: $5 input + $25 output за 1M токенов. Scarlex: от 9.8 ₽ за 1M.',
  en: 'Anthropic Claude Opus 4.7: $5 input + $25 output per 1M tokens. Scarlex: from 9.8 ₽ per 1M.',
}
```

- [ ] **Step 2: Create `i18n.js`**

Create `src/lib/i18n.js` with:

```js
export const dictionaries = {
  ru: {
    navModels: 'Модели',
    navFlow: 'Интеграция',
    navPricing: 'Цены',
    navFaq: 'FAQ',
    navLogin: 'Войти',
    navDashboard: 'Кабинет',
    heroEyebrow: 'SCARLEX V3 · PRODUCT SPA',
    heroTitle: 'Claude API для тех, кто хочет продукт, а не костыль.',
    heroBody: 'OpenAI-compatible доступ к Claude-моделям без VPN. Карта, СБП и CryptoBot. Быстрое подключение и русский onboarding.',
    heroPrimary: 'Получить ключ',
    heroSecondary: 'Смотреть интеграцию',
    heroTrust: ['Без VPN', 'OpenAI-compatible', 'RU-first', 'Мгновенная активация'],
    modelsTitle: 'Одна точка входа ко всем Claude-моделям.',
    modelsBody: 'Выбирай модель как capability layer внутри продукта, а не как строку в прайсе.',
    devTitle: 'Подключение за минуты, не за вечер.',
    devBody: 'Тот же привычный OpenAI-стиль интеграции. Меняешь endpoint и ключ — и работаешь.',
    pricingTitle: 'Прозрачная покупка токенов без подписочного мусора.',
    pricingBody: 'Чем больше пакет, тем ниже цена за 1M токенов.',
    faqTitle: 'Оплата, доступ, поддержка и ограничения — без сюрпризов.',
    finalTitle: 'Готов перейти на Claude API без VPN?',
    finalBody: 'Зайди в кабинет, выбери пакет и начни работать без лишней инфраструктурной боли.',
    finalPrimary: 'Открыть кабинет',
    finalSecondary: 'Посмотреть цены',
    copied: 'Скопировано!',
  },
  en: {
    navModels: 'Models',
    navFlow: 'Integration',
    navPricing: 'Pricing',
    navFaq: 'FAQ',
    navLogin: 'Sign in',
    navDashboard: 'Dashboard',
    heroEyebrow: 'SCARLEX V3 · PRODUCT SPA',
    heroTitle: 'Claude API for people who want a product, not a workaround.',
    heroBody: 'OpenAI-compatible access to Claude models without VPN. Card, SBP, and CryptoBot. Fast setup and RU-first onboarding.',
    heroPrimary: 'Get a key',
    heroSecondary: 'View integration',
    heroTrust: ['No VPN', 'OpenAI-compatible', 'RU-first', 'Instant activation'],
    modelsTitle: 'One entry point for every Claude model.',
    modelsBody: 'Choose a model like a capability layer inside a product, not a row in pricing.',
    devTitle: 'Integration in minutes, not an evening.',
    devBody: 'The same familiar OpenAI-style integration. Change the endpoint and key — ship.',
    pricingTitle: 'Transparent token pricing without subscription junk.',
    pricingBody: 'Bigger packs mean lower price per 1M tokens.',
    faqTitle: 'Payments, access, support, and limits — no surprises.',
    finalTitle: 'Ready to switch to Claude API without VPN?',
    finalBody: 'Open the dashboard, choose a pack, and start working without infrastructure pain.',
    finalPrimary: 'Open dashboard',
    finalSecondary: 'View pricing',
    copied: 'Copied!',
  },
}

export function getInitialLanguage() {
  try {
    return localStorage.getItem('scarlex_lang') || 'ru'
  } catch {
    return 'ru'
  }
}

export function persistLanguage(lang) {
  try {
    localStorage.setItem('scarlex_lang', lang)
  } catch {
    // ignore storage issues
  }
}
```

- [ ] **Step 3: Create `session.js`**

Create `src/lib/session.js` with:

```js
export async function fetchSession() {
  try {
    const response = await fetch('api/check_session.php', {
      credentials: 'same-origin',
    })

    if (!response.ok) {
      return { ok: false, user: null }
    }

    const payload = await response.json()

    if (payload && payload.ok && payload.user) {
      return { ok: true, user: payload.user }
    }

    return { ok: false, user: null }
  } catch {
    return { ok: false, user: null }
  }
}
```

- [ ] **Step 4: Wire content/i18n imports into `App.jsx` as a failing-but-valid placeholder**

Replace `src/App.jsx` with:

```jsx
import { useEffect, useMemo, useState } from 'react'
import { dictionaries, getInitialLanguage, persistLanguage } from './lib/i18n'
import { fetchSession } from './lib/session'
import { models, pricingPlans, faqItems } from './lib/content'

export default function App() {
  const [lang, setLang] = useState(getInitialLanguage)
  const [session, setSession] = useState({ ok: false, user: null })

  useEffect(() => {
    fetchSession().then(setSession)
  }, [])

  const dict = useMemo(() => dictionaries[lang] || dictionaries.ru, [lang])

  function handleLanguage(nextLang) {
    setLang(nextLang)
    persistLanguage(nextLang)
  }

  return (
    <div>
      <h1>{dict.heroTitle}</h1>
      <p>{dict.heroBody}</p>
      <p>{session.ok ? dict.navDashboard : dict.navLogin}</p>
      <p>Models: {models.length}</p>
      <p>Plans: {pricingPlans.length}</p>
      <p>FAQ: {faqItems.length}</p>
      <button onClick={() => handleLanguage(lang === 'ru' ? 'en' : 'ru')}>toggle lang</button>
    </div>
  )
}
```

- [ ] **Step 5: Run build to validate the data layer**

Run:

```powershell
npm run build
```

Workdir: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa`

Expected: build passes.

- [ ] **Step 6: Commit**

```bash
git add src/lib/content.js src/lib/i18n.js src/lib/session.js src/App.jsx
git commit -m "feat: add indexv3 content and app state"
```

### Task 3: Build the app shell, nav, and footer

**Files:**
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\layout\TopNav.jsx`
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\layout\Footer.jsx`
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\app.css`
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\layout.css`
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\App.jsx`

- [ ] **Step 1: Create `TopNav.jsx`**

Create `src/components/layout/TopNav.jsx` with:

```jsx
export default function TopNav({ dict, lang, onLanguageChange, session }) {
  const isAuthed = Boolean(session?.ok)

  return (
    <header className="top-nav">
      <div className="shell top-nav__inner">
        <a className="brand" href="#hero">
          <span className="brand__mark">S</span>
          <span className="brand__text">Scarlex</span>
        </a>

        <nav className="top-nav__links" aria-label="Primary">
          <a href="#models">{dict.navModels}</a>
          <a href="#developer-flow">{dict.navFlow}</a>
          <a href="#pricing">{dict.navPricing}</a>
          <a href="#trust-faq">{dict.navFaq}</a>
        </nav>

        <div className="top-nav__actions">
          <div className="lang-switch" role="group" aria-label="Language switch">
            <button className={lang === 'ru' ? 'is-active' : ''} onClick={() => onLanguageChange('ru')}>
              RU
            </button>
            <button className={lang === 'en' ? 'is-active' : ''} onClick={() => onLanguageChange('en')}>
              EN
            </button>
          </div>

          <a className="nav-cta" href={isAuthed ? 'dashboard.html' : 'authorization.html'}>
            {isAuthed ? dict.navDashboard : dict.navLogin}
          </a>
        </div>
      </div>
    </header>
  )
}
```

- [ ] **Step 2: Create `Footer.jsx`**

Create `src/components/layout/Footer.jsx` with:

```jsx
export default function Footer() {
  return (
    <footer className="site-footer">
      <div className="shell site-footer__inner">
        <span>© 2026 Scarlex</span>
        <div className="site-footer__links">
          <a href="authorization.html">Auth</a>
          <a href="#pricing">Pricing</a>
          <a href="#developer-flow">Docs</a>
        </div>
      </div>
    </footer>
  )
}
```

- [ ] **Step 3: Create `app.css` foundation styles**

Create `src/styles/app.css` with:

```css
:root {
  --bg: #06070b;
  --bg-soft: #0b0d14;
  --surface: rgba(14, 18, 28, 0.74);
  --surface-strong: rgba(15, 20, 31, 0.92);
  --border: rgba(255, 255, 255, 0.08);
  --border-strong: rgba(145, 163, 255, 0.18);
  --text: #f6f8fb;
  --text-2: #a4aec4;
  --text-3: rgba(230, 235, 244, 0.52);
  --brand: #ff7d3d;
  --violet: #8b5cf6;
  --blue: #88a7ff;
  --ok: #73f0bc;
  --radius-xl: 32px;
  --radius-lg: 24px;
  --radius-md: 18px;
  --radius-sm: 12px;
  --shadow-lg: 0 28px 90px rgba(0, 0, 0, 0.42);
  --shadow-md: 0 18px 42px rgba(0, 0, 0, 0.28);
  --container: min(1280px, calc(100% - 40px));
}

* { box-sizing: border-box; }

html {
  scroll-behavior: smooth;
  color-scheme: dark;
}

body {
  margin: 0;
  min-height: 100vh;
  font-family: 'Inter', system-ui, sans-serif;
  background:
    radial-gradient(circle at 10% 0%, rgba(123, 92, 246, 0.13), transparent 30%),
    radial-gradient(circle at 100% 20%, rgba(136, 167, 255, 0.10), transparent 34%),
    linear-gradient(180deg, #07080c 0%, #090b12 100%);
  color: var(--text);
  overflow-x: hidden;
}

a {
  color: inherit;
  text-decoration: none;
}

button,
input,
textarea,
select {
  font: inherit;
}

code,
pre {
  font-family: 'JetBrains Mono', ui-monospace, monospace;
}
```

- [ ] **Step 4: Create `layout.css`**

Create `src/styles/layout.css` with:

```css
.shell {
  width: var(--container);
  margin: 0 auto;
}

.app-shell {
  position: relative;
}

.screen {
  position: relative;
  padding: 48px 0 88px;
}

.top-nav {
  position: sticky;
  top: 0;
  z-index: 40;
  backdrop-filter: blur(18px) saturate(140%);
  -webkit-backdrop-filter: blur(18px) saturate(140%);
  background: rgba(6, 8, 12, 0.72);
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.top-nav__inner {
  min-height: 72px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
}

.top-nav__links,
.top-nav__actions,
.site-footer__links {
  display: flex;
  align-items: center;
  gap: 16px;
}

.site-footer {
  padding: 28px 0 36px;
  border-top: 1px solid rgba(255,255,255,0.06);
}

.site-footer__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  color: var(--text-3);
  font-size: 13px;
}

@media (max-width: 900px) {
  .top-nav__links {
    display: none;
  }

  .site-footer__inner {
    flex-direction: column;
    align-items: flex-start;
  }
}
```

- [ ] **Step 5: Update `App.jsx` to render the shell and layout components**

Replace `src/App.jsx` with:

```jsx
import { useEffect, useMemo, useState } from 'react'
import { dictionaries, getInitialLanguage, persistLanguage } from './lib/i18n'
import { fetchSession } from './lib/session'
import TopNav from './components/layout/TopNav'
import Footer from './components/layout/Footer'

export default function App() {
  const [lang, setLang] = useState(getInitialLanguage)
  const [session, setSession] = useState({ ok: false, user: null })

  useEffect(() => {
    fetchSession().then(setSession)
  }, [])

  const dict = useMemo(() => dictionaries[lang] || dictionaries.ru, [lang])

  function handleLanguage(nextLang) {
    setLang(nextLang)
    persistLanguage(nextLang)
  }

  return (
    <div className="app-shell">
      <TopNav dict={dict} lang={lang} onLanguageChange={handleLanguage} session={session} />
      <main>
        <section className="screen shell" id="hero">
          <h1>{dict.heroTitle}</h1>
        </section>
      </main>
      <Footer />
    </div>
  )
}
```

- [ ] **Step 6: Run build to verify the shell compiles**

Run:

```powershell
npm run build
```

Expected: build passes.

- [ ] **Step 7: Commit**

```bash
git add src/components/layout/TopNav.jsx src/components/layout/Footer.jsx src/styles/app.css src/styles/layout.css src/App.jsx
git commit -m "feat: add indexv3 app shell and nav"
```

### Task 4: Build the hero product screen and demo panel

**Files:**
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\hero\HeroProduct.jsx`
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\hero\HeroDemoPanel.jsx`
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\components.css`
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\screens.css`
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\App.jsx`

- [ ] **Step 1: Create `HeroDemoPanel.jsx`**

Create `src/components/hero/HeroDemoPanel.jsx` with:

```jsx
import { useEffect, useMemo, useState } from 'react'

const scenes = [
  {
    label: 'Response',
    status: 'claude-opus-4.7 · 2.4s · 287 tokens',
    lines: [
      '$ curl https://api.scarlex.ru/v1/chat/completions',
      '-H "Authorization: Bearer sk-scarlex-***"',
      '-d {"model":"claude-opus-4.7","messages":[...]}',
      '',
      '{',
      '  "content": "Привет! Я помогу с вашим кодом.",',
      '  "usage": { "total_tokens": 287 }',
      '}',
    ],
  },
  {
    label: 'Python SDK',
    status: 'claude-sonnet-4 · 1.8s · OpenAI-compatible',
    lines: [
      'from openai import OpenAI',
      'client = OpenAI(base_url="https://api.scarlex.ru/v1", api_key="sk-...")',
      'resp = client.chat.completions.create(...)',
      'print(resp.choices[0].message.content)',
      '',
      'Hi! How can I help?',
    ],
  },
]

export default function HeroDemoPanel() {
  const [sceneIndex, setSceneIndex] = useState(0)
  const [lineIndex, setLineIndex] = useState(0)
  const [charIndex, setCharIndex] = useState(0)

  const scene = useMemo(() => scenes[sceneIndex], [sceneIndex])

  useEffect(() => {
    const currentLine = scene.lines[lineIndex] ?? ''

    if (lineIndex >= scene.lines.length) {
      const timer = window.setTimeout(() => {
        setSceneIndex((value) => (value + 1) % scenes.length)
        setLineIndex(0)
        setCharIndex(0)
      }, 1800)

      return () => window.clearTimeout(timer)
    }

    if (charIndex < currentLine.length) {
      const timer = window.setTimeout(() => {
        setCharIndex((value) => Math.min(value + 2, currentLine.length))
      }, 18)

      return () => window.clearTimeout(timer)
    }

    const timer = window.setTimeout(() => {
      setLineIndex((value) => value + 1)
      setCharIndex(0)
    }, currentLine === '' ? 90 : 130)

    return () => window.clearTimeout(timer)
  }, [scene, lineIndex, charIndex])

  const visibleLines = scene.lines.map((line, index) => {
    if (index < lineIndex) return line
    if (index === lineIndex) return line.slice(0, charIndex)
    return ''
  })

  return (
    <div className="hero-demo panel">
      <div className="hero-demo__top">
        <div>
          <div className="eyebrow-chip">{scene.label}</div>
          <div className="hero-demo__status">{scene.status}</div>
        </div>
        <div className="hero-demo__meta">OpenAI-compatible</div>
      </div>

      <div className="hero-demo__terminal" aria-live="polite">
        {visibleLines.map((line, index) => (
          <div key={index} className="hero-demo__line">
            {line}
            {index === lineIndex && <span className="hero-demo__cursor" />}
          </div>
        ))}
      </div>
    </div>
  )
}
```

- [ ] **Step 2: Create `HeroProduct.jsx`**

Create `src/components/hero/HeroProduct.jsx` with:

```jsx
import HeroDemoPanel from './HeroDemoPanel'

export default function HeroProduct({ dict }) {
  return (
    <section className="screen hero-screen" id="hero">
      <div className="shell hero-screen__grid">
        <div className="hero-copy">
          <div className="eyebrow-chip eyebrow-chip--soft">{dict.heroEyebrow}</div>
          <h1 className="hero-copy__title">{dict.heroTitle}</h1>
          <p className="hero-copy__body">{dict.heroBody}</p>

          <div className="hero-copy__actions">
            <a className="button button--primary" href="authorization.html">
              {dict.heroPrimary}
            </a>
            <a className="button button--ghost" href="#developer-flow">
              {dict.heroSecondary}
            </a>
          </div>

          <div className="hero-copy__trust">
            {dict.heroTrust.map((item) => (
              <span className="trust-pill" key={item}>
                {item}
              </span>
            ))}
          </div>
        </div>

        <HeroDemoPanel />
      </div>
    </section>
  )
}
```

- [ ] **Step 3: Create shared component styles in `components.css`**

Create `src/styles/components.css` with:

```css
.panel {
  background: linear-gradient(180deg, rgba(15, 19, 29, 0.94), rgba(11, 14, 21, 0.9));
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-md), inset 0 1px 0 rgba(255, 255, 255, 0.02);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
}

.button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 48px;
  padding: 0 18px;
  border-radius: 999px;
  border: 1px solid transparent;
  font-weight: 600;
  transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
}

.button:hover {
  transform: translateY(-1px);
}

.button--primary {
  background: linear-gradient(135deg, #8b5cf6 0%, #88a7ff 62%, #ff7d3d 100%);
  color: white;
  box-shadow: 0 12px 26px rgba(90, 104, 255, 0.28);
}

.button--ghost {
  border-color: rgba(255,255,255,0.1);
  background: rgba(255,255,255,0.03);
  color: var(--text);
}

.eyebrow-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 30px;
  padding: 0 12px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  border: 1px solid rgba(255,255,255,0.08);
  background: rgba(255,255,255,0.04);
  color: var(--text-2);
}

.eyebrow-chip--soft {
  background: rgba(139, 92, 246, 0.08);
  border-color: rgba(139, 92, 246, 0.18);
  color: #cfbfff;
}

.trust-pill {
  display: inline-flex;
  align-items: center;
  min-height: 34px;
  padding: 0 12px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,0.08);
  background: rgba(255,255,255,0.03);
  color: var(--text-2);
  font-size: 12px;
  font-weight: 500;
}

.lang-switch {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,0.08);
  background: rgba(255,255,255,0.03);
}

.lang-switch button {
  min-width: 44px;
  min-height: 32px;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--text-3);
  cursor: pointer;
}

.lang-switch .is-active {
  background: rgba(255,255,255,0.08);
  color: var(--text);
}

.nav-cta {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 40px;
  padding: 0 14px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,0.08);
  background: rgba(255,255,255,0.04);
}
```

- [ ] **Step 4: Create hero screen styles in `screens.css`**

Create `src/styles/screens.css` with:

```css
.hero-screen {
  padding-top: 56px;
}

.hero-screen__grid {
  display: grid;
  grid-template-columns: minmax(0, 1.04fr) minmax(420px, .96fr);
  gap: 34px;
  align-items: center;
}

.hero-copy__title {
  margin: 18px 0 0;
  font-size: clamp(3rem, 7vw, 6.1rem);
  line-height: 0.95;
  letter-spacing: -0.055em;
  max-width: 11ch;
}

.hero-copy__body {
  margin: 22px 0 0;
  max-width: 56ch;
  font-size: 18px;
  line-height: 1.75;
  color: var(--text-2);
}

.hero-copy__actions,
.hero-copy__trust {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.hero-copy__actions {
  margin-top: 28px;
}

.hero-copy__trust {
  margin-top: 18px;
}

.hero-demo {
  padding: 22px;
}

.hero-demo__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
  padding-bottom: 18px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
}

.hero-demo__status,
.hero-demo__meta {
  margin-top: 8px;
  color: var(--text-3);
  font-size: 12px;
  font-family: 'JetBrains Mono', monospace;
}

.hero-demo__terminal {
  padding-top: 18px;
  min-height: 320px;
  font-size: 13px;
  line-height: 1.7;
  color: #e9efff;
  font-family: 'JetBrains Mono', monospace;
}

.hero-demo__line {
  min-height: 22px;
  white-space: pre-wrap;
  word-break: break-word;
}

.hero-demo__cursor {
  display: inline-block;
  width: 8px;
  height: 18px;
  margin-left: 4px;
  background: #cdbfff;
  animation: blink 1s steps(1, end) infinite;
}

@keyframes blink {
  0%, 49% { opacity: 1; }
  50%, 100% { opacity: 0; }
}

@media (max-width: 980px) {
  .hero-screen__grid {
    grid-template-columns: 1fr;
  }
}
```

- [ ] **Step 5: Replace the hero placeholder in `App.jsx` with the real hero screen**

Update `src/App.jsx` to:

```jsx
import { useEffect, useMemo, useState } from 'react'
import { dictionaries, getInitialLanguage, persistLanguage } from './lib/i18n'
import { fetchSession } from './lib/session'
import TopNav from './components/layout/TopNav'
import Footer from './components/layout/Footer'
import HeroProduct from './components/hero/HeroProduct'

export default function App() {
  const [lang, setLang] = useState(getInitialLanguage)
  const [session, setSession] = useState({ ok: false, user: null })

  useEffect(() => {
    fetchSession().then(setSession)
  }, [])

  const dict = useMemo(() => dictionaries[lang] || dictionaries.ru, [lang])

  function handleLanguage(nextLang) {
    setLang(nextLang)
    persistLanguage(nextLang)
  }

  return (
    <div className="app-shell">
      <TopNav dict={dict} lang={lang} onLanguageChange={handleLanguage} session={session} />
      <main>
        <HeroProduct dict={dict} />
      </main>
      <Footer />
    </div>
  )
}
```

- [ ] **Step 6: Run build after the hero screen lands**

Run:

```powershell
npm run build
```

Expected: build passes.

- [ ] **Step 7: Commit**

```bash
git add src/components/hero/HeroProduct.jsx src/components/hero/HeroDemoPanel.jsx src/styles/components.css src/styles/screens.css src/App.jsx
git commit -m "feat: add indexv3 hero product screen"
```

### Task 5: Build the models and developer experience screens

**Files:**
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\models\ModelCard.jsx`
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\models\ModelExperience.jsx`
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\dev\CodeTabs.jsx`
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\dev\DeveloperFlow.jsx`
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\screens.css`
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\App.jsx`

- [ ] **Step 1: Create `ModelCard.jsx`**

Create `src/components/models/ModelCard.jsx` with:

```jsx
export default function ModelCard({ model, active, onSelect }) {
  return (
    <button
      type="button"
      className={`model-card panel ${active ? 'is-active' : ''}`}
      onClick={() => onSelect(model.id)}
    >
      <div className="model-card__top">
        <div>
          <div className="model-card__name">{model.name}</div>
          <div className="model-card__id">{model.id}</div>
        </div>
        <span className="model-card__tag">{model.tag}</span>
      </div>
      <p className="model-card__body">{model.blurb}</p>
      <div className="model-card__meta">{model.meta}</div>
    </button>
  )
}
```

- [ ] **Step 2: Create `ModelExperience.jsx`**

Create `src/components/models/ModelExperience.jsx` with:

```jsx
import { useMemo, useState } from 'react'
import { models } from '../../lib/content'
import ModelCard from './ModelCard'

export default function ModelExperience({ dict, lang }) {
  const [activeModelId, setActiveModelId] = useState(models[0].id)

  const activeModel = useMemo(
    () => models.find((item) => item.id === activeModelId) || models[0],
    [activeModelId],
  )

  return (
    <section className="screen" id="models">
      <div className="shell stack-screen">
        <div className="stack-screen__intro">
          <div className="eyebrow-chip">Claude Models</div>
          <h2 className="section-title">{dict.modelsTitle}</h2>
          <p className="section-body">{dict.modelsBody}</p>
        </div>

        <div className="model-experience__grid">
          <div className="model-experience__list">
            {models.map((model) => (
              <ModelCard
                key={model.id}
                model={{
                  ...model,
                  blurb: model.blurb[lang],
                }}
                active={model.id === activeModelId}
                onSelect={setActiveModelId}
              />
            ))}
          </div>

          <div className="model-preview panel">
            <div className="model-preview__eyebrow">Active capability</div>
            <h3>{activeModel.name}</h3>
            <p>{activeModel.blurb[lang]}</p>
            <dl className="model-preview__meta">
              <div>
                <dt>Model ID</dt>
                <dd>{activeModel.id}</dd>
              </div>
              <div>
                <dt>Meta</dt>
                <dd>{activeModel.meta}</dd>
              </div>
            </dl>
          </div>
        </div>
      </div>
    </section>
  )
}
```

- [ ] **Step 3: Create `CodeTabs.jsx`**

Create `src/components/dev/CodeTabs.jsx` with:

```jsx
import { useMemo, useState } from 'react'
import { codeSnippets } from '../../lib/content'

const tabOrder = [
  ['curl', 'cURL'],
  ['python', 'Python'],
  ['javascript', 'JavaScript'],
  ['opencode', 'OpenCode'],
]

export default function CodeTabs({ copiedLabel }) {
  const [active, setActive] = useState('curl')

  const snippet = useMemo(() => codeSnippets[active], [active])

  async function handleCopy() {
    try {
      await navigator.clipboard.writeText(snippet)
      window.alert(copiedLabel)
    } catch {
      const textarea = document.createElement('textarea')
      textarea.value = snippet
      document.body.appendChild(textarea)
      textarea.select()
      document.execCommand('copy')
      textarea.remove()
      window.alert(copiedLabel)
    }
  }

  return (
    <div className="code-tabs panel">
      <div className="code-tabs__head">
        <div className="code-tabs__nav">
          {tabOrder.map(([id, label]) => (
            <button
              key={id}
              className={active === id ? 'is-active' : ''}
              onClick={() => setActive(id)}
            >
              {label}
            </button>
          ))}
        </div>

        <button className="code-tabs__copy" onClick={handleCopy}>
          Copy
        </button>
      </div>

      <pre className="code-tabs__body">
        <code>{snippet}</code>
      </pre>
    </div>
  )
}
```

- [ ] **Step 4: Create `DeveloperFlow.jsx`**

Create `src/components/dev/DeveloperFlow.jsx` with:

```jsx
import CodeTabs from './CodeTabs'

export default function DeveloperFlow({ dict }) {
  return (
    <section className="screen" id="developer-flow">
      <div className="shell developer-flow">
        <div className="developer-flow__copy">
          <div className="eyebrow-chip">Developer flow</div>
          <h2 className="section-title">{dict.devTitle}</h2>
          <p className="section-body">{dict.devBody}</p>

          <div className="developer-flow__notes panel">
            <div>Endpoint: <code>https://api.scarlex.ru/v1</code></div>
            <div>Protocol: OpenAI-compatible</div>
            <div>Payments: СБП / карта / CryptoBot</div>
          </div>
        </div>

        <CodeTabs copiedLabel={dict.copied} />
      </div>
    </section>
  )
}
```

- [ ] **Step 5: Extend `screens.css` for models and developer flow**

Append to `src/styles/screens.css`:

```css
.stack-screen {
  display: grid;
  gap: 28px;
}

.stack-screen__intro {
  max-width: 760px;
}

.section-title {
  margin: 14px 0 0;
  font-size: clamp(2rem, 4vw, 3.4rem);
  line-height: 1.02;
  letter-spacing: -0.04em;
}

.section-body {
  margin: 14px 0 0;
  color: var(--text-2);
  line-height: 1.75;
  font-size: 17px;
}

.model-experience__grid,
.developer-flow {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(360px, 520px);
  gap: 24px;
  align-items: start;
}

.model-experience__list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.model-card {
  padding: 18px;
  text-align: left;
  cursor: pointer;
  transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
}

.model-card:hover,
.model-card.is-active {
  transform: translateY(-2px);
  border-color: var(--border-strong);
  box-shadow: 0 18px 40px rgba(0,0,0,0.28);
}

.model-card__top {
  display: flex;
  justify-content: space-between;
  gap: 16px;
}

.model-card__name {
  font-size: 18px;
  font-weight: 700;
}

.model-card__id,
.model-card__meta,
.model-preview__eyebrow,
.model-preview__meta dt {
  color: var(--text-3);
  font-size: 12px;
  font-family: 'JetBrains Mono', monospace;
}

.model-card__body,
.model-preview p {
  color: var(--text-2);
  line-height: 1.7;
}

.model-card__tag {
  align-self: flex-start;
  min-height: 26px;
  padding: 0 10px;
  border-radius: 999px;
  background: rgba(255,255,255,0.06);
  display: inline-flex;
  align-items: center;
  font-size: 11px;
  font-weight: 700;
}

.model-preview,
.developer-flow__notes {
  padding: 22px;
}

.model-preview__meta {
  display: grid;
  gap: 16px;
  margin-top: 18px;
}

.model-preview__meta dd {
  margin: 8px 0 0;
}

.code-tabs {
  overflow: hidden;
}

.code-tabs__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 16px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
}

.code-tabs__nav {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.code-tabs__nav button,
.code-tabs__copy {
  min-height: 36px;
  padding: 0 12px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,0.08);
  background: rgba(255,255,255,0.03);
  color: var(--text-2);
  cursor: pointer;
}

.code-tabs__nav .is-active {
  color: var(--text);
  border-color: var(--border-strong);
}

.code-tabs__body {
  margin: 0;
  padding: 20px;
  color: #ecf1ff;
  line-height: 1.7;
  overflow: auto;
}

@media (max-width: 980px) {
  .model-experience__grid,
  .developer-flow {
    grid-template-columns: 1fr;
  }

  .model-experience__list {
    grid-template-columns: 1fr;
  }
}
```

- [ ] **Step 6: Add the screens to `App.jsx`**

Update `src/App.jsx` to:

```jsx
import { useEffect, useMemo, useState } from 'react'
import { dictionaries, getInitialLanguage, persistLanguage } from './lib/i18n'
import { fetchSession } from './lib/session'
import TopNav from './components/layout/TopNav'
import Footer from './components/layout/Footer'
import HeroProduct from './components/hero/HeroProduct'
import ModelExperience from './components/models/ModelExperience'
import DeveloperFlow from './components/dev/DeveloperFlow'

export default function App() {
  const [lang, setLang] = useState(getInitialLanguage)
  const [session, setSession] = useState({ ok: false, user: null })

  useEffect(() => {
    fetchSession().then(setSession)
  }, [])

  const dict = useMemo(() => dictionaries[lang] || dictionaries.ru, [lang])

  function handleLanguage(nextLang) {
    setLang(nextLang)
    persistLanguage(nextLang)
  }

  return (
    <div className="app-shell">
      <TopNav dict={dict} lang={lang} onLanguageChange={handleLanguage} session={session} />
      <main>
        <HeroProduct dict={dict} />
        <ModelExperience dict={dict} lang={lang} />
        <DeveloperFlow dict={dict} />
      </main>
      <Footer />
    </div>
  )
}
```

- [ ] **Step 7: Run build after adding the middle screens**

Run:

```powershell
npm run build
```

Expected: build passes.

- [ ] **Step 8: Commit**

```bash
git add src/components/models/ModelCard.jsx src/components/models/ModelExperience.jsx src/components/dev/CodeTabs.jsx src/components/dev/DeveloperFlow.jsx src/styles/screens.css src/App.jsx
git commit -m "feat: add model and developer screens"
```

### Task 6: Build pricing, trust/FAQ, and final CTA screens

**Files:**
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\pricing\PlanCard.jsx`
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\pricing\PricingSurface.jsx`
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\trust\FAQItem.jsx`
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\trust\TrustAndFAQ.jsx`
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\cta\FinalAction.jsx`
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\screens.css`
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\App.jsx`

- [ ] **Step 1: Create `PlanCard.jsx`**

Create `src/components/pricing/PlanCard.jsx` with:

```jsx
export default function PlanCard({ plan }) {
  return (
    <a className="plan-card panel" href={plan.href}>
      <div className="plan-card__top">
        <div>
          <div className="plan-card__name">{plan.name}</div>
          <div className="plan-card__tokens">{plan.tokens}</div>
        </div>
        {plan.badge ? <span className="plan-card__badge">{plan.badge}</span> : null}
      </div>
      <div className="plan-card__price">{plan.price}</div>
      <div className="plan-card__per">{plan.perMillion}</div>
      {plan.compare ? <div className="plan-card__compare">{plan.compare}</div> : null}
    </a>
  )
}
```

- [ ] **Step 2: Create `PricingSurface.jsx`**

Create `src/components/pricing/PricingSurface.jsx` with:

```jsx
import { anthropicComparison, pricingPlans } from '../../lib/content'
import PlanCard from './PlanCard'

export default function PricingSurface({ dict, lang }) {
  return (
    <section className="screen" id="pricing">
      <div className="shell stack-screen">
        <div className="stack-screen__intro">
          <div className="eyebrow-chip">Pricing</div>
          <h2 className="section-title">{dict.pricingTitle}</h2>
          <p className="section-body">{dict.pricingBody}</p>
        </div>

        <div className="pricing-grid">
          {pricingPlans.map((plan) => (
            <PlanCard key={plan.slug} plan={plan} />
          ))}
        </div>

        <div className="pricing-note panel">{anthropicComparison[lang]}</div>
      </div>
    </section>
  )
}
```

- [ ] **Step 3: Create `FAQItem.jsx`**

Create `src/components/trust/FAQItem.jsx` with:

```jsx
export default function FAQItem({ item, lang }) {
  return (
    <details className="faq-item panel">
      <summary>{item.q[lang]}</summary>
      <div className="faq-item__body">{item.a[lang]}</div>
    </details>
  )
}
```

- [ ] **Step 4: Create `TrustAndFAQ.jsx`**

Create `src/components/trust/TrustAndFAQ.jsx` with:

```jsx
import { faqItems, trustFacts } from '../../lib/content'
import FAQItem from './FAQItem'

export default function TrustAndFAQ({ dict, lang }) {
  return (
    <section className="screen" id="trust-faq">
      <div className="shell trust-faq">
        <div className="trust-faq__intro">
          <div className="eyebrow-chip">Trust</div>
          <h2 className="section-title">{dict.faqTitle}</h2>
          <div className="trust-facts">
            {trustFacts.map((fact) => (
              <span className="trust-pill" key={fact}>{fact}</span>
            ))}
          </div>
        </div>

        <div className="trust-faq__list">
          {faqItems.map((item) => (
            <FAQItem key={item.q.ru} item={item} lang={lang} />
          ))}
        </div>
      </div>
    </section>
  )
}
```

- [ ] **Step 5: Create `FinalAction.jsx`**

Create `src/components/cta/FinalAction.jsx` with:

```jsx
export default function FinalAction({ dict }) {
  return (
    <section className="screen">
      <div className="shell">
        <div className="final-action panel">
          <div className="eyebrow-chip">Scarlex</div>
          <h2 className="section-title">{dict.finalTitle}</h2>
          <p className="section-body">{dict.finalBody}</p>

          <div className="hero-copy__actions final-action__actions">
            <a className="button button--primary" href="authorization.html">
              {dict.finalPrimary}
            </a>
            <a className="button button--ghost" href="#pricing">
              {dict.finalSecondary}
            </a>
          </div>
        </div>
      </div>
    </section>
  )
}
```

- [ ] **Step 6: Extend `screens.css` with pricing, FAQ, and final CTA styles**

Append to `src/styles/screens.css`:

```css
.pricing-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.plan-card {
  padding: 20px;
  transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
}

.plan-card:hover {
  transform: translateY(-2px);
  border-color: var(--border-strong);
  box-shadow: 0 18px 42px rgba(0,0,0,0.28);
}

.plan-card__top {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.plan-card__name {
  font-size: 18px;
  font-weight: 700;
}

.plan-card__tokens,
.plan-card__per,
.plan-card__compare,
.plan-card__badge,
.pricing-note,
.faq-item__body {
  color: var(--text-2);
}

.plan-card__badge {
  align-self: flex-start;
  padding: 6px 10px;
  border-radius: 999px;
  background: rgba(255,255,255,0.06);
  font-size: 11px;
}

.plan-card__price {
  margin-top: 18px;
  font-size: 34px;
  font-weight: 800;
  letter-spacing: -0.04em;
}

.plan-card__per,
.plan-card__compare {
  margin-top: 8px;
  line-height: 1.6;
}

.pricing-note,
.final-action {
  padding: 24px;
}

.trust-faq {
  display: grid;
  grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr);
  gap: 24px;
  align-items: start;
}

.trust-facts {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 18px;
}

.trust-faq__list {
  display: grid;
  gap: 12px;
}

.faq-item {
  padding: 0;
  overflow: hidden;
}

.faq-item summary {
  list-style: none;
  cursor: pointer;
  padding: 18px 20px;
  font-weight: 600;
}

.faq-item__body {
  padding: 0 20px 20px;
  line-height: 1.7;
}

.final-action {
  text-align: center;
}

.final-action__actions {
  justify-content: center;
}

@media (max-width: 1100px) {
  .pricing-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 980px) {
  .trust-faq,
  .pricing-grid {
    grid-template-columns: 1fr;
  }
}
```

- [ ] **Step 7: Add the final screens to `App.jsx`**

Update `src/App.jsx` to:

```jsx
import { useEffect, useMemo, useState } from 'react'
import { dictionaries, getInitialLanguage, persistLanguage } from './lib/i18n'
import { fetchSession } from './lib/session'
import TopNav from './components/layout/TopNav'
import Footer from './components/layout/Footer'
import HeroProduct from './components/hero/HeroProduct'
import ModelExperience from './components/models/ModelExperience'
import DeveloperFlow from './components/dev/DeveloperFlow'
import PricingSurface from './components/pricing/PricingSurface'
import TrustAndFAQ from './components/trust/TrustAndFAQ'
import FinalAction from './components/cta/FinalAction'

export default function App() {
  const [lang, setLang] = useState(getInitialLanguage)
  const [session, setSession] = useState({ ok: false, user: null })

  useEffect(() => {
    fetchSession().then(setSession)
  }, [])

  const dict = useMemo(() => dictionaries[lang] || dictionaries.ru, [lang])

  function handleLanguage(nextLang) {
    setLang(nextLang)
    persistLanguage(nextLang)
  }

  return (
    <div className="app-shell">
      <TopNav dict={dict} lang={lang} onLanguageChange={handleLanguage} session={session} />
      <main>
        <HeroProduct dict={dict} />
        <ModelExperience dict={dict} lang={lang} />
        <DeveloperFlow dict={dict} />
        <PricingSurface dict={dict} lang={lang} />
        <TrustAndFAQ dict={dict} lang={lang} />
        <FinalAction dict={dict} />
      </main>
      <Footer />
    </div>
  )
}
```

- [ ] **Step 8: Run build after all screens are added**

Run:

```powershell
npm run build
```

Expected: build passes.

- [ ] **Step 9: Commit**

```bash
git add src/components/pricing/PlanCard.jsx src/components/pricing/PricingSurface.jsx src/components/trust/FAQItem.jsx src/components/trust/TrustAndFAQ.jsx src/components/cta/FinalAction.jsx src/styles/screens.css src/App.jsx
git commit -m "feat: complete indexv3 spa screens"
```

### Task 7: Verify behavior and prepare for visual review

**Files:**
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\dev\CodeTabs.jsx` if copy fallback is broken
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\App.jsx` if session/lang issues are found
- Modify: any CSS file if build or runtime layout problems appear

- [ ] **Step 1: Run the final production build**

Run:

```powershell
npm run build
```

Expected: successful build with generated `dist/` assets.

- [ ] **Step 2: Start the local Vite preview server**

Run:

```powershell
npm run preview -- --host 127.0.0.1 --port 4173
```

Workdir: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa`

Expected: local preview server starts successfully.

- [ ] **Step 3: Manually review the SPA in browser**

Open:

```text
http://127.0.0.1:4173
```

Check:

```text
- Page feels product-like, not like the rejected landing draft
- Hero has strong wellflow-like product composition
- Models screen feels interactive and product-oriented
- Developer flow reads like docs/devtool UI
- Pricing is clear and links to authorization.html?plan=...
- FAQ and trust screen remain usable
- RU/EN switch works
- Session-aware nav degrades safely if session check fails
- Copy buttons work
- Mobile layout remains readable
```

- [ ] **Step 4: Apply final polish fixes if manual review reveals issues**

If fixes are needed, make the smallest relevant edits and rerun:

```powershell
npm run build
```

Expected: build still passes.

- [ ] **Step 5: Commit**

```bash
git add src
git commit -m "feat: polish indexv3 react spa"
```

## Self-Review

- Spec coverage checked: the plan covers fresh React + Vite scaffolding, app shell, hero, model experience, developer flow, pricing, trust/FAQ, final CTA, content extraction, lightweight i18n, session-aware nav, and purposeful preserved interactions.
- Placeholder scan checked: no `TODO`, `TBD`, or vague deferred steps remain.
- Type consistency checked: component names, file paths, IDs/anchors, and helper names are internally consistent across tasks.
