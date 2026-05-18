# Indexv3 React SPA Restart Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the current `indexv3-spa` front-end so it abandons the rejected SaaS/landing composition and becomes a much closer wellflow-class product front page while preserving Scarlex business facts and essential interactions.

**Architecture:** Keep the existing React + Vite project scaffold, but treat the current component/page structure as disposable. Rebuild the page from visual grammar first: a quiet header, a massive product hero canvas, a unified product-proof zone, an embedded pricing-decision zone, and a quieter lower utility zone; then remap Scarlex content into that new structure.

**Tech Stack:** React, Vite, vanilla CSS, browser Fetch API, localStorage, Clipboard API

---

## File Map

- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\App.jsx`
  - Replace the current screen stack with the new whole-page composition.
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\app.css`
  - Tighten the base atmosphere and remove leftover generic-SaaS foundation cues.
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\layout.css`
  - Rebuild global shell and header geometry.
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\components.css`
  - Replace pill-heavy buttons/chips/panels with stricter product surfaces.
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\screens.css`
  - Replace current screen styling with the new visual skeleton.
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\lib\content.js`
  - Keep business data, but reshape any content structures needed by the new composition.
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\lib\i18n.js`
  - Tighten language strings to fit the new product-style composition.
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\layout\TopNav.jsx`
  - Keep only if it supports the new thinner header.
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\hero\HeroProduct.jsx`
  - Rebuild as the new hero canvas.
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\hero\HeroDemoPanel.jsx`
  - Replace current terminal-card feel with a real product-window shell.
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\models\ModelExperience.jsx`
  - Fold into the new unified proof zone.
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\models\ModelCard.jsx`
  - Replace current card pattern with selector/capability treatment if still needed.
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\dev\DeveloperFlow.jsx`
  - Fold into the unified proof zone.
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\dev\CodeTabs.jsx`
  - Keep behavior, but restyle into a stricter product/dev surface.
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\pricing\PricingSurface.jsx`
  - Replace pricing-card layout with pricing decision zone.
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\pricing\PlanCard.jsx`
  - Replace or remove if card-based pricing conflicts with new design.
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\trust\TrustAndFAQ.jsx`
  - Compress into the quieter lower utility zone.
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\trust\FAQItem.jsx`
  - Keep if it fits the quieter help-center-like FAQ treatment.
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\cta\FinalAction.jsx`
  - Replace banner-like ending with a restrained final action zone.
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\layout\Footer.jsx`
  - Make footer quieter and secondary.

## Constraints

- Do not preserve the current visual/component structure for its own sake.
- Keep React + Vite setup intact; do not start a separate project.
- Keep Scarlex business facts and links intact.
- Avoid giant radii, pill-heavy controls, generic pricing cards, loud gradient blobs, and repeated section-card patterns.
- Use the existing app as a technical base only.

### Task 1: Replace the page composition with the new visual skeleton

**Files:**
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\App.jsx`

- [ ] **Step 1: Replace the current screen-stack import list with a smaller composition that reflects the restart**

Replace the imports in `src/App.jsx` so they become:

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
```

This looks identical to the current import list on purpose — the reset happens in the returned composition, props, and the components themselves. The goal of this step is to lock the restart into the same technical entry while acknowledging that all imported components are about to be redefined.

- [ ] **Step 2: Replace the `App` return tree with the new zone-based composition**

Replace the `return (...)` body in `src/App.jsx` with:

```jsx
return (
  <div className="app-shell">
    <TopNav dict={dict} lang={lang} onLanguageChange={handleLanguage} session={session} />

    <main className="page-canvas">
      <HeroProduct dict={dict} lang={lang} />

      <section className="proof-zone" id="proof-zone">
        <div className="shell proof-zone__inner">
          <ModelExperience dict={dict} lang={lang} />
          <DeveloperFlow dict={dict} lang={lang} />
        </div>
      </section>

      <PricingSurface dict={dict} lang={lang} />

      <section className="utility-zone" id="utility-zone">
        <div className="shell utility-zone__inner">
          <TrustAndFAQ dict={dict} lang={lang} />
          <FinalAction dict={dict} />
        </div>
      </section>
    </main>

    <Footer />
  </div>
)
```

- [ ] **Step 3: Keep state and side effects intact**

Ensure the state and session/language logic stays exactly in this shape:

```jsx
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
```

This preserves working behavior while the visual system is being replaced.

- [ ] **Step 4: Run a build immediately after the composition reset**

Run:

```powershell
npm run build
```

Workdir: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa`

Expected: build succeeds even if visuals are still mid-transition.

- [ ] **Step 5: Commit**

```bash
git add src/App.jsx
git commit -m "feat: reset indexv3 page composition"
```

### Task 2: Rebuild the foundation and kill the generic-SaaS atmosphere

**Files:**
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\app.css`
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\layout.css`

- [ ] **Step 1: Replace the visual variables in `app.css` with a tighter foundation**

Replace the whole `:root` block in `src/styles/app.css` with:

```css
:root {
  --bg: #06070a;
  --bg-2: #0a0c12;
  --surface: #10141d;
  --surface-2: #131823;
  --surface-3: #0d1119;
  --border: rgba(255, 255, 255, 0.08);
  --border-2: rgba(138, 162, 255, 0.18);
  --text: #f6f8fb;
  --text-2: #adb5c6;
  --text-3: rgba(237, 241, 250, 0.5);
  --brand: #ff7d3d;
  --violet: #8a72ff;
  --blue: #8aa2ff;
  --ok: #73f0bc;
  --radius-xl: 18px;
  --radius-lg: 14px;
  --radius-md: 10px;
  --radius-sm: 8px;
  --shadow-lg: 0 30px 80px rgba(0, 0, 0, 0.42);
  --shadow-md: 0 18px 38px rgba(0, 0, 0, 0.28);
  --container: min(1320px, calc(100% - 48px));
}
```

- [ ] **Step 2: Replace the `body` background so it becomes quieter and more product-like**

Replace the `body { ... }` block in `src/styles/app.css` with:

```css
body {
  margin: 0;
  min-height: 100vh;
  font-family: 'Inter', system-ui, sans-serif;
  background:
    radial-gradient(circle at 78% 10%, rgba(120, 139, 255, 0.08), transparent 20%),
    radial-gradient(circle at 18% 0%, rgba(138, 114, 255, 0.07), transparent 22%),
    linear-gradient(180deg, #07080b 0%, #090b10 45%, #0b0d13 100%);
  color: var(--text);
  overflow-x: hidden;
}
```

- [ ] **Step 3: Replace the global layout shell in `layout.css`**

Replace the full contents of `src/styles/layout.css` with:

```css
.shell {
  width: var(--container);
  margin: 0 auto;
}

.app-shell {
  position: relative;
  isolation: isolate;
}

.page-canvas {
  position: relative;
}

.proof-zone,
.utility-zone {
  position: relative;
}

.proof-zone__inner,
.utility-zone__inner {
  display: grid;
  gap: 28px;
}

.top-nav {
  position: sticky;
  top: 0;
  z-index: 50;
  background: rgba(6, 8, 11, 0.78);
  backdrop-filter: blur(16px) saturate(130%);
  -webkit-backdrop-filter: blur(16px) saturate(130%);
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.top-nav__inner {
  min-height: 66px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
}

.top-nav__links,
.top-nav__actions,
.site-footer__links {
  display: flex;
  align-items: center;
  gap: 18px;
}

.brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-weight: 700;
  letter-spacing: -0.03em;
}

.brand__mark {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #8a72ff 0%, #8aa2ff 70%, #ff7d3d 100%);
  color: white;
}

.brand__text {
  color: var(--text);
  font-size: 15px;
}

.top-nav__links a,
.site-footer__links a {
  color: var(--text-2);
  font-size: 14px;
  transition: color .16s ease;
}

.top-nav__links a:hover,
.site-footer__links a:hover {
  color: var(--text);
}

.site-footer {
  padding: 26px 0 34px;
  border-top: 1px solid rgba(255,255,255,0.06);
}

.site-footer__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  color: var(--text-3);
  font-size: 12px;
}

@media (max-width: 980px) {
  .top-nav__links {
    display: none;
  }

  .site-footer__inner {
    flex-direction: column;
    align-items: flex-start;
  }
}
```

- [ ] **Step 4: Rebuild and verify foundation reset**

Run:

```powershell
npm run build
```

Expected: build passes.

- [ ] **Step 5: Commit**

```bash
git add src/styles/app.css src/styles/layout.css
git commit -m "feat: reset indexv3 visual foundation"
```

### Task 3: Replace pill-heavy controls and soft card language

**Files:**
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\components.css`

- [ ] **Step 1: Replace the entire shared component style file**

Replace the contents of `src/styles/components.css` with:

```css
.panel {
  position: relative;
  background: linear-gradient(180deg, rgba(17, 21, 31, 0.96), rgba(12, 16, 24, 0.96));
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
}

.button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 44px;
  padding: 0 16px;
  border: 1px solid transparent;
  border-radius: 10px;
  font-weight: 600;
  font-size: 14px;
  transition: transform .16s ease, border-color .16s ease, background-color .16s ease, box-shadow .16s ease;
}

.button:hover {
  transform: translateY(-1px);
}

.button--primary {
  background: linear-gradient(135deg, #8a72ff 0%, #8aa2ff 65%, #ff7d3d 100%);
  color: white;
  box-shadow: 0 14px 30px rgba(94, 108, 255, 0.22);
}

.button--ghost {
  background: rgba(255,255,255,0.03);
  border-color: rgba(255,255,255,0.08);
  color: var(--text);
}

.eyebrow-chip {
  display: inline-flex;
  align-items: center;
  min-height: 24px;
  padding: 0 10px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,0.08);
  background: rgba(255,255,255,0.03);
  color: var(--text-3);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.eyebrow-chip--soft {
  border-color: rgba(138, 114, 255, 0.16);
  background: rgba(138, 114, 255, 0.08);
  color: #d2c7ff;
}

.trust-pill {
  display: inline-flex;
  align-items: center;
  min-height: 24px;
  padding: 0 8px;
  border-radius: 6px;
  border: 1px solid rgba(255,255,255,0.08);
  background: rgba(255,255,255,0.02);
  color: var(--text-3);
  font-size: 11px;
  font-family: 'JetBrains Mono', monospace;
}

.lang-switch {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.lang-switch button {
  min-width: 38px;
  min-height: 30px;
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 8px;
  background: transparent;
  color: var(--text-3);
  cursor: pointer;
}

.lang-switch .is-active {
  background: rgba(255,255,255,0.06);
  color: var(--text);
}

.nav-cta {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 38px;
  padding: 0 14px;
  border-radius: 10px;
  border: 1px solid rgba(255,255,255,0.08);
  background: rgba(255,255,255,0.03);
  color: var(--text);
}
```

- [ ] **Step 2: Build after replacing the shared UI language**

Run:

```powershell
npm run build
```

Expected: build passes.

- [ ] **Step 3: Commit**

```bash
git add src/styles/components.css
git commit -m "feat: replace soft ui language in indexv3"
```

### Task 4: Rebuild the hero into a true product canvas

**Files:**
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\hero\HeroProduct.jsx`
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\hero\HeroDemoPanel.jsx`
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\screens.css`
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\lib\i18n.js`

- [ ] **Step 1: Replace the current hero copy strings in `i18n.js` with tighter product-style messaging**

Replace these keys in both dictionaries in `src/lib/i18n.js`:

```js
heroEyebrow: 'SCARLEX V3 · CLAUDE ACCESS',
heroTitle: 'Claude API that feels like a real product.',
heroBody: 'Claude access without VPN, with clean OpenAI-compatible integration and Russian payment rails.',
heroPrimary: 'Get API access',
heroSecondary: 'View integration',
heroTrust: ['No VPN', 'OpenAI-compatible', 'RU payments', 'Claude-only'],
```

And in Russian:

```js
heroEyebrow: 'SCARLEX V3 · ДОСТУП К CLAUDE',
heroTitle: 'Claude API, который ощущается как нормальный продукт.',
heroBody: 'Доступ к Claude без VPN, с чистой OpenAI-compatible интеграцией и оплатой через российские платёжные рельсы.',
heroPrimary: 'Получить доступ',
heroSecondary: 'Смотреть интеграцию',
heroTrust: ['Без VPN', 'OpenAI-compatible', 'RU payments', 'Claude-only'],
```

- [ ] **Step 2: Replace `HeroProduct.jsx` with a stricter hero composition**

Replace the full component with:

```jsx
import HeroDemoPanel from './HeroDemoPanel'

export default function HeroProduct({ dict }) {
  return (
    <section className="hero-canvas" id="hero">
      <div className="shell hero-canvas__inner">
        <div className="hero-copy">
          <div className="eyebrow-chip eyebrow-chip--soft">{dict.heroEyebrow}</div>
          <h1 className="hero-copy__title">{dict.heroTitle}</h1>
          <p className="hero-copy__body">{dict.heroBody}</p>

          <div className="hero-copy__actions">
            <a className="button button--primary" href="authorization.html">
              {dict.heroPrimary}
            </a>
            <a className="button button--ghost" href="#proof-zone">
              {dict.heroSecondary}
            </a>
          </div>

          <div className="hero-copy__meta-row">
            {dict.heroTrust.map((item) => (
              <span className="trust-pill" key={item}>{item}</span>
            ))}
          </div>
        </div>

        <HeroDemoPanel />
      </div>
    </section>
  )
}
```

- [ ] **Step 3: Replace `HeroDemoPanel.jsx` so it feels like a product window, not a terminal card**

Replace the full component with:

```jsx
import { useEffect, useMemo, useState } from 'react'

const scenes = [
  {
    tab: 'Response',
    status: 'claude-opus-4.7 · 2.4s · 287 tokens',
    resultTitle: 'Structured completion',
    resultBody: 'Привет! Я помогу с кодом, архитектурой и отладкой. Можем разобрать проблему по шагам и дать готовый патч.',
    footer: 'OpenAI-compatible · RU billing · no VPN',
  },
  {
    tab: 'SDK',
    status: 'claude-sonnet-4 · 1.8s · Python SDK',
    resultTitle: 'Same integration surface',
    resultBody: 'Меняешь base_url и api_key, оставляя привычный OpenAI-style код. Без новой экосистемной боли.',
    footer: 'base_url=https://api.scarlex.ru/v1',
  },
]

export default function HeroDemoPanel() {
  const [sceneIndex, setSceneIndex] = useState(0)
  const [charCount, setCharCount] = useState(0)

  const scene = useMemo(() => scenes[sceneIndex], [sceneIndex])

  useEffect(() => {
    if (charCount < scene.resultBody.length) {
      const timer = window.setTimeout(() => {
        setCharCount((value) => Math.min(value + 2, scene.resultBody.length))
      }, 22)

      return () => window.clearTimeout(timer)
    }

    const timer = window.setTimeout(() => {
      setSceneIndex((value) => (value + 1) % scenes.length)
      setCharCount(0)
    }, 1800)

    return () => window.clearTimeout(timer)
  }, [scene, charCount])

  return (
    <div className="hero-window panel">
      <div className="hero-window__chrome">
        <div className="hero-window__dots">
          <span />
          <span />
          <span />
        </div>
        <div className="hero-window__tabs">
          <span className="is-active">{scene.tab}</span>
          <span>API</span>
          <span>Usage</span>
        </div>
      </div>

      <div className="hero-window__body">
        <div className="hero-window__status">{scene.status}</div>
        <div className="hero-window__result panel">
          <div className="hero-window__label">Live response surface</div>
          <h3>{scene.resultTitle}</h3>
          <p>
            {scene.resultBody.slice(0, charCount)}
            <span className="hero-window__cursor" />
          </p>
        </div>

        <div className="hero-window__footer">{scene.footer}</div>
      </div>
    </div>
  )
}
```

- [ ] **Step 4: Replace the entire hero section of `screens.css`**

Replace the hero-related CSS at the top of `src/styles/screens.css` with:

```css
.hero-canvas {
  padding: 52px 0 34px;
}

.hero-canvas__inner {
  display: grid;
  grid-template-columns: minmax(0, 1.08fr) minmax(460px, 0.92fr);
  gap: 28px;
  align-items: stretch;
}

.hero-copy {
  padding-top: 34px;
}

.hero-copy__title {
  margin: 16px 0 0;
  max-width: 10ch;
  font-size: clamp(3.3rem, 7vw, 6.5rem);
  line-height: 0.92;
  letter-spacing: -0.065em;
  color: var(--text);
  text-wrap: balance;
}

.hero-copy__body {
  margin: 20px 0 0;
  max-width: 48ch;
  color: var(--text-2);
  line-height: 1.72;
  font-size: 17px;
}

.hero-copy__actions,
.hero-copy__meta-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.hero-copy__actions {
  margin-top: 26px;
}

.hero-copy__meta-row {
  margin-top: 18px;
}

.hero-window {
  overflow: hidden;
}

.hero-window__chrome {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 54px;
  padding: 0 16px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
}

.hero-window__dots {
  display: flex;
  gap: 6px;
}

.hero-window__dots span {
  width: 9px;
  height: 9px;
  border-radius: 999px;
  background: rgba(255,255,255,0.18);
}

.hero-window__tabs {
  display: flex;
  gap: 18px;
  color: var(--text-3);
  font-size: 12px;
  font-family: 'JetBrains Mono', monospace;
}

.hero-window__tabs .is-active {
  color: var(--text);
}

.hero-window__body {
  display: grid;
  gap: 14px;
  padding: 18px;
}

.hero-window__status,
.hero-window__label,
.hero-window__footer {
  color: var(--text-3);
  font-size: 12px;
  font-family: 'JetBrains Mono', monospace;
}

.hero-window__result {
  padding: 18px;
  background: linear-gradient(180deg, rgba(17, 22, 33, 0.98), rgba(13, 17, 25, 0.98));
}

.hero-window__result h3 {
  margin: 10px 0 0;
  font-size: 20px;
  letter-spacing: -0.03em;
}

.hero-window__result p {
  margin: 14px 0 0;
  color: var(--text-2);
  line-height: 1.8;
  min-height: 118px;
}

.hero-window__cursor {
  display: inline-block;
  width: 7px;
  height: 16px;
  margin-left: 4px;
  background: #d0c6ff;
  vertical-align: text-bottom;
  animation: blink 1s steps(1, end) infinite;
}

@keyframes blink {
  0%, 49% { opacity: 1; }
  50%, 100% { opacity: 0; }
}

@media (max-width: 1080px) {
  .hero-canvas__inner {
    grid-template-columns: 1fr;
  }

  .hero-copy {
    padding-top: 10px;
  }
}
```

- [ ] **Step 5: Build and confirm the hero restart compiles**

Run:

```powershell
npm run build
```

Expected: build passes.

- [ ] **Step 6: Commit**

```bash
git add src/components/hero/HeroProduct.jsx src/components/hero/HeroDemoPanel.jsx src/styles/screens.css src/lib/i18n.js
git commit -m "feat: rebuild indexv3 hero canvas"
```

### Task 5: Collapse models and developer flow into one product-proof zone

**Files:**
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\models\ModelExperience.jsx`
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\models\ModelCard.jsx`
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\dev\DeveloperFlow.jsx`
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\dev\CodeTabs.jsx`
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\screens.css`
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\lib\i18n.js`

- [ ] **Step 1: Replace `ModelCard.jsx` with a tighter selector row component**

Replace the full component with:

```jsx
export default function ModelCard({ model, active, onSelect }) {
  return (
    <button
      type="button"
      className={`capability-row ${active ? 'is-active' : ''}`}
      onClick={() => onSelect(model.id)}
    >
      <div>
        <div className="capability-row__name">{model.name}</div>
        <div className="capability-row__id">{model.id}</div>
      </div>
      <span className="capability-row__tag">{model.tag}</span>
    </button>
  )
}
```

- [ ] **Step 2: Replace `ModelExperience.jsx` with a proof-surface component**

Replace the full component with:

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
    <section className="proof-surface proof-surface--models" id="models">
      <div className="proof-surface__header">
        <div className="eyebrow-chip">Capabilities</div>
        <h2 className="proof-title">{dict.modelsTitle}</h2>
        <p className="proof-body">{dict.modelsBody}</p>
      </div>

      <div className="proof-models panel">
        <div className="proof-models__rail">
          {models.map((model) => (
            <ModelCard
              key={model.id}
              model={{ ...model, blurb: model.blurb[lang] }}
              active={model.id === activeModelId}
              onSelect={setActiveModelId}
            />
          ))}
        </div>

        <div className="proof-models__detail">
          <div className="proof-models__meta">active capability</div>
          <h3>{activeModel.name}</h3>
          <p>{activeModel.blurb[lang]}</p>
          <div className="proof-models__detail-grid">
            <div>
              <span>Model ID</span>
              <strong>{activeModel.id}</strong>
            </div>
            <div>
              <span>Context / tier</span>
              <strong>{activeModel.meta}</strong>
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}
```

- [ ] **Step 3: Replace `CodeTabs.jsx` with a denser dev-panel version**

Replace the full component with:

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
    <div className="dev-panel panel">
      <div className="dev-panel__head">
        <div className="dev-panel__tabs">
          {tabOrder.map(([id, label]) => (
            <button key={id} className={active === id ? 'is-active' : ''} onClick={() => setActive(id)}>
              {label}
            </button>
          ))}
        </div>

        <button className="dev-panel__copy" onClick={handleCopy}>Copy</button>
      </div>

      <pre className="dev-panel__code"><code>{snippet}</code></pre>
    </div>
  )
}
```

- [ ] **Step 4: Replace `DeveloperFlow.jsx` with a proof-surface peer**

Replace the full component with:

```jsx
import CodeTabs from './CodeTabs'

export default function DeveloperFlow({ dict }) {
  return (
    <section className="proof-surface proof-surface--dev" id="developer-flow">
      <div className="proof-surface__header">
        <div className="eyebrow-chip">Developer flow</div>
        <h2 className="proof-title">{dict.devTitle}</h2>
        <p className="proof-body">{dict.devBody}</p>
      </div>

      <div className="proof-dev panel">
        <div className="proof-dev__meta-row">
          <span>Endpoint: https://api.scarlex.ru/v1</span>
          <span>OpenAI-compatible</span>
          <span>SBP / Card / CryptoBot</span>
        </div>
        <CodeTabs copiedLabel={dict.copied} />
      </div>
    </section>
  )
}
```

- [ ] **Step 5: Replace the non-hero portion of `screens.css` with a proof-zone-first layout**

Replace everything in `src/styles/screens.css` after the hero section with:

```css
.proof-zone {
  padding: 26px 0 30px;
}

.proof-zone__inner {
  display: grid;
  gap: 22px;
}

.proof-surface {
  display: grid;
  gap: 16px;
}

.proof-surface__header {
  max-width: 760px;
}

.proof-title {
  margin: 12px 0 0;
  font-size: clamp(2rem, 4vw, 3.2rem);
  line-height: 1.03;
  letter-spacing: -0.05em;
  color: var(--text);
}

.proof-body {
  margin: 12px 0 0;
  color: var(--text-2);
  font-size: 16px;
  line-height: 1.75;
}

.proof-models {
  display: grid;
  grid-template-columns: 380px minmax(0, 1fr);
  gap: 0;
  overflow: hidden;
}

.proof-models__rail {
  padding: 10px;
  border-right: 1px solid rgba(255,255,255,0.06);
  display: grid;
  gap: 8px;
}

.capability-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  min-height: 68px;
  padding: 0 12px;
  border: 1px solid transparent;
  border-radius: 10px;
  background: transparent;
  color: var(--text);
  text-align: left;
  cursor: pointer;
  transition: border-color .16s ease, background-color .16s ease, transform .16s ease;
}

.capability-row:hover,
.capability-row.is-active {
  border-color: var(--border-2);
  background: rgba(255,255,255,0.03);
  transform: translateY(-1px);
}

.capability-row__name {
  font-size: 15px;
  font-weight: 700;
}

.capability-row__id,
.capability-row__tag,
.proof-models__meta,
.proof-models__detail-grid span,
.dev-panel__tabs,
.dev-panel__copy,
.proof-dev__meta-row {
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
  color: var(--text-3);
}

.capability-row__tag {
  white-space: nowrap;
}

.proof-models__detail {
  padding: 22px;
}

.proof-models__detail h3 {
  margin: 10px 0 0;
  font-size: 28px;
  letter-spacing: -0.04em;
}

.proof-models__detail p {
  margin: 14px 0 0;
  color: var(--text-2);
  line-height: 1.8;
}

.proof-models__detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
  margin-top: 20px;
}

.proof-models__detail-grid strong {
  display: block;
  margin-top: 8px;
  font-size: 14px;
}

.proof-dev {
  padding: 16px;
  display: grid;
  gap: 14px;
}

.proof-dev__meta-row {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
}

.dev-panel {
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 12px;
  overflow: hidden;
  background: rgba(8, 10, 15, 0.66);
}

.dev-panel__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  min-height: 48px;
  padding: 0 12px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
}

.dev-panel__tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.dev-panel__tabs button,
.dev-panel__copy {
  min-height: 30px;
  padding: 0 10px;
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 8px;
  background: transparent;
  color: var(--text-3);
  cursor: pointer;
}

.dev-panel__tabs .is-active {
  color: var(--text);
  border-color: var(--border-2);
}

.dev-panel__code {
  margin: 0;
  padding: 18px;
  color: #edf2ff;
  line-height: 1.7;
  overflow: auto;
}

@media (max-width: 1100px) {
  .proof-models {
    grid-template-columns: 1fr;
  }

  .proof-models__rail {
    border-right: 0;
    border-bottom: 1px solid rgba(255,255,255,0.06);
  }
}
```

- [ ] **Step 6: Build and verify the proof-zone restart**

Run:

```powershell
npm run build
```

Expected: build passes.

- [ ] **Step 7: Commit**

```bash
git add src/components/models/ModelCard.jsx src/components/models/ModelExperience.jsx src/components/dev/CodeTabs.jsx src/components/dev/DeveloperFlow.jsx src/styles/screens.css src/lib/i18n.js
git commit -m "feat: rebuild indexv3 proof zone"
```

### Task 6: Replace pricing cards with a pricing decision zone

**Files:**
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\pricing\PricingSurface.jsx`
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\pricing\PlanCard.jsx`
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\screens.css`
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\lib\i18n.js`

- [ ] **Step 1: Tighten pricing copy in `i18n.js`**

Replace these keys in both dictionaries:

```js
pricingTitle: 'Choose the package that fits your usage profile.',
pricingBody: 'Token bundles with predictable cost, direct billing context, and no subscription theater.',
```

And in Russian:

```js
pricingTitle: 'Выбери пакет под свой профиль использования.',
pricingBody: 'Покупка токенов с понятной стоимостью, прямым биллинг-контекстом и без подписочного театра.',
```

- [ ] **Step 2: Replace `PlanCard.jsx` with a lean plan row component**

Replace the full component with:

```jsx
export default function PlanCard({ plan, featured = false }) {
  return (
    <a className={`plan-row ${featured ? 'is-featured' : ''}`} href={plan.href}>
      <div className="plan-row__left">
        <div className="plan-row__name">{plan.name}</div>
        <div className="plan-row__tokens">{plan.tokens}</div>
      </div>

      <div className="plan-row__mid">
        <div className="plan-row__price">{plan.price}</div>
        <div className="plan-row__per">{plan.perMillion}</div>
      </div>

      <div className="plan-row__right">
        {plan.badge ? <div className="plan-row__badge">{plan.badge}</div> : null}
        {plan.compare ? <div className="plan-row__compare">{plan.compare}</div> : null}
      </div>
    </a>
  )
}
```

- [ ] **Step 3: Replace `PricingSurface.jsx` with a pricing decision interface**

Replace the full component with:

```jsx
import { anthropicComparison, pricingPlans } from '../../lib/content'
import PlanCard from './PlanCard'

export default function PricingSurface({ dict, lang }) {
  return (
    <section className="pricing-zone" id="pricing">
      <div className="shell pricing-zone__inner">
        <div className="pricing-zone__intro">
          <div className="eyebrow-chip">Pricing</div>
          <h2 className="proof-title">{dict.pricingTitle}</h2>
          <p className="proof-body">{dict.pricingBody}</p>
        </div>

        <div className="pricing-surface panel">
          <div className="pricing-surface__list">
            {pricingPlans.map((plan, index) => (
              <PlanCard key={plan.slug} plan={plan} featured={index === 2 || index === 4} />
            ))}
          </div>

          <div className="pricing-surface__context">
            <div className="pricing-surface__context-label">Pricing context</div>
            <p>{anthropicComparison[lang]}</p>
            <div className="pricing-surface__context-meta">
              <span>SBP</span>
              <span>Bank card</span>
              <span>CryptoBot</span>
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}
```

- [ ] **Step 4: Add pricing-zone styles to `screens.css`**

Append to `src/styles/screens.css`:

```css
.pricing-zone {
  padding: 34px 0 30px;
}

.pricing-zone__inner {
  display: grid;
  gap: 18px;
}

.pricing-zone__intro {
  max-width: 760px;
}

.pricing-surface {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 0;
  overflow: hidden;
}

.pricing-surface__list {
  display: grid;
}

.plan-row {
  display: grid;
  grid-template-columns: 1.2fr .9fr 1fr;
  gap: 16px;
  align-items: center;
  min-height: 90px;
  padding: 0 18px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  color: inherit;
  transition: background-color .16s ease, border-color .16s ease;
}

.plan-row:hover,
.plan-row.is-featured {
  background: rgba(255,255,255,0.03);
}

.plan-row__name {
  font-size: 18px;
  font-weight: 700;
}

.plan-row__tokens,
.plan-row__per,
.plan-row__badge,
.plan-row__compare,
.pricing-surface__context-label,
.pricing-surface__context-meta {
  font-size: 12px;
  color: var(--text-3);
  font-family: 'JetBrains Mono', monospace;
}

.plan-row__price {
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.05em;
}

.plan-row__right {
  display: grid;
  gap: 8px;
  justify-items: end;
  text-align: right;
}

.pricing-surface__context {
  padding: 20px;
  border-left: 1px solid rgba(255,255,255,0.06);
  display: grid;
  align-content: start;
  gap: 14px;
}

.pricing-surface__context p {
  margin: 0;
  color: var(--text-2);
  line-height: 1.75;
}

.pricing-surface__context-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

@media (max-width: 1080px) {
  .pricing-surface {
    grid-template-columns: 1fr;
  }

  .pricing-surface__context {
    border-left: 0;
    border-top: 1px solid rgba(255,255,255,0.06);
  }
}

@media (max-width: 760px) {
  .plan-row {
    grid-template-columns: 1fr;
    align-items: start;
    padding: 16px;
  }

  .plan-row__right {
    justify-items: start;
    text-align: left;
  }
}
```

- [ ] **Step 5: Build and verify the pricing restart**

Run:

```powershell
npm run build
```

Expected: build passes.

- [ ] **Step 6: Commit**

```bash
git add src/components/pricing/PlanCard.jsx src/components/pricing/PricingSurface.jsx src/styles/screens.css src/lib/i18n.js
git commit -m "feat: rebuild indexv3 pricing decision zone"
```

### Task 7: Compress the lower zone and final CTA into a quiet utility layer

**Files:**
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\trust\TrustAndFAQ.jsx`
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\trust\FAQItem.jsx`
- Modify or Replace: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\cta\FinalAction.jsx`
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\components\layout\Footer.jsx`
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\styles\screens.css`
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa\src\lib\i18n.js`

- [ ] **Step 1: Tighten FAQ/final strings in `i18n.js`**

Replace these keys in both dictionaries:

```js
faqTitle: 'Payments, access, support, and limits without surprises.',
finalTitle: 'Move to Claude access without VPN.',
finalBody: 'Open the account area, choose a package, and start with the same OpenAI-style integration flow.',
finalPrimary: 'Open account area',
finalSecondary: 'Review pricing',
```

And in Russian:

```js
faqTitle: 'Оплата, доступ, поддержка и лимиты без сюрпризов.',
finalTitle: 'Переходи на Claude-доступ без VPN.',
finalBody: 'Открой кабинет, выбери пакет и начни с тем же OpenAI-style интеграционным флоу.',
finalPrimary: 'Открыть кабинет',
finalSecondary: 'Посмотреть цены',
```

- [ ] **Step 2: Replace `FAQItem.jsx` with a quieter item**

Replace the full component with:

```jsx
export default function FAQItem({ item, lang }) {
  return (
    <details className="utility-faq-item">
      <summary>{item.q[lang]}</summary>
      <div className="utility-faq-item__body">{item.a[lang]}</div>
    </details>
  )
}
```

- [ ] **Step 3: Replace `TrustAndFAQ.jsx`**

Replace the full component with:

```jsx
import { faqItems, trustFacts } from '../../lib/content'
import FAQItem from './FAQItem'

export default function TrustAndFAQ({ dict, lang }) {
  return (
    <section className="utility-surface panel" id="trust-faq">
      <div className="utility-surface__intro">
        <div className="eyebrow-chip">Utility</div>
        <h2 className="proof-title">{dict.faqTitle}</h2>
        <div className="utility-surface__facts">
          {trustFacts.map((fact) => (
            <span className="trust-pill" key={fact}>{fact}</span>
          ))}
        </div>
      </div>

      <div className="utility-surface__faq-list">
        {faqItems.map((item) => (
          <FAQItem key={item.q.ru} item={item} lang={lang} />
        ))}
      </div>
    </section>
  )
}
```

- [ ] **Step 4: Replace `FinalAction.jsx`**

Replace the full component with:

```jsx
export default function FinalAction({ dict }) {
  return (
    <section className="final-utility panel">
      <div className="eyebrow-chip">Next step</div>
      <h2 className="proof-title">{dict.finalTitle}</h2>
      <p className="proof-body">{dict.finalBody}</p>

      <div className="hero-copy__actions">
        <a className="button button--primary" href="authorization.html">
          {dict.finalPrimary}
        </a>
        <a className="button button--ghost" href="#pricing">
          {dict.finalSecondary}
        </a>
      </div>
    </section>
  )
}
```

- [ ] **Step 5: Simplify `Footer.jsx`**

Replace the full component with:

```jsx
export default function Footer() {
  return (
    <footer className="site-footer">
      <div className="shell site-footer__inner">
        <span>© 2026 Scarlex</span>
        <div className="site-footer__links">
          <a href="authorization.html">Account</a>
          <a href="#pricing">Pricing</a>
          <a href="#developer-flow">API</a>
        </div>
      </div>
    </footer>
  )
}
```

- [ ] **Step 6: Add utility-zone styles to `screens.css`**

Append to `src/styles/screens.css`:

```css
.utility-zone {
  padding: 30px 0 40px;
}

.utility-zone__inner {
  display: grid;
  gap: 18px;
}

.utility-surface,
.final-utility {
  padding: 20px;
}

.utility-surface__facts {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 16px;
}

.utility-surface__faq-list {
  display: grid;
  gap: 6px;
  margin-top: 18px;
}

.utility-faq-item {
  border-top: 1px solid rgba(255,255,255,0.06);
  padding-top: 12px;
}

.utility-faq-item summary {
  list-style: none;
  cursor: pointer;
  color: var(--text);
  font-weight: 600;
}

.utility-faq-item__body {
  margin-top: 10px;
  color: var(--text-2);
  line-height: 1.75;
}

.final-utility {
  display: grid;
  gap: 12px;
}
```

- [ ] **Step 7: Build and verify the lower-zone restart**

Run:

```powershell
npm run build
```

Expected: build passes.

- [ ] **Step 8: Commit**

```bash
git add src/components/trust/TrustAndFAQ.jsx src/components/trust/FAQItem.jsx src/components/cta/FinalAction.jsx src/components/layout/Footer.jsx src/styles/screens.css src/lib/i18n.js
git commit -m "feat: rebuild indexv3 utility zone"
```

### Task 8: Visual verification and cleanup pass

**Files:**
- Modify: any files above, if the review reveals mismatches with the restart spec

- [ ] **Step 1: Run the final build**

Run:

```powershell
npm run build
```

Expected: successful build with generated `dist/` assets.

- [ ] **Step 2: Start local preview**

Run:

```powershell
npm run preview -- --host 127.0.0.1 --port 4173
```

Workdir: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3-spa`

Expected: local preview server starts on `http://127.0.0.1:4173/`.

- [ ] **Step 3: Manual browser review against restart criteria**

Open:

```text
http://127.0.0.1:4173/
```

Check these exact criteria:

```text
- Does the page still look like a generic SaaS landing? If yes, fail the review.
- Does hero feel like a product scene rather than text + card? If no, fail the review.
- Do radii still feel too soft? If yes, tighten them.
- Do pricing rows still read like pricing cards? If yes, fail the review.
- Does the lower utility zone stay quieter than hero/proof/pricing? If no, fail the review.
- Does the page now feel materially closer to wellflow-class design? If no, fail the review.
```

- [ ] **Step 4: Apply one focused polish pass if review fails any criterion**

Allowed polish categories only:

```text
- tighten radii further
- reduce spacing between major blocks
- reduce chip/button softness further
- increase hero mass and reduce card feel
- flatten pricing into denser rows
- reduce decorative contrast in lower zone
```

After the polish pass, rerun:

```powershell
npm run build
```

Expected: build still passes.

- [ ] **Step 5: Commit**

```bash
git add src
git commit -m "feat: finalize indexv3 spa restart"
```

## Self-Review

- Spec coverage checked: the plan explicitly replaces the rejected page composition, visual grammar, hero, proof zone, pricing zone, and lower utility zone while preserving Scarlex facts and behaviors.
- Placeholder scan checked: no `TODO`, `TBD`, or vague "later" steps remain.
- Type consistency checked: component/file names, zone names, anchors, and data helpers are consistent across all tasks.
