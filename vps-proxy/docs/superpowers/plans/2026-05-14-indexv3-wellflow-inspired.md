# Indexv3 Wellflow-Inspired Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `indexv3.html` as a calmer, more premium, wellflow-inspired Scarlex landing page that preserves the current approved content structure, business messaging, i18n, and static behavior.

**Architecture:** Reuse the current `indexv2.html` content model and JS behavior, but rebuild the visual system around a cleaner dark product aesthetic. The implementation should minimize behavioral changes and focus on HTML/CSS refactoring, section rhythm, hero composition, and unified glass-card styling while keeping cross-browser stability.

**Tech Stack:** Static HTML, CSS, vanilla JavaScript, local file preview in desktop browsers

---

## File Map

- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3.html`
  - New wellflow-inspired landing page draft.
- Reference: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv2.html`
  - Source of content, i18n keys, JS behaviors, and section structure.
- Reference: `C:\Users\lucky\AppData\Local\Temp\opencode\indexv2_tail.html`
  - Existing script/i18n/typewriter behavior to port if needed.
- Docs: `docs/superpowers/specs/2026-05-14-indexv2-wellflow-inspired-design.md`
  - Approved design spec.

## Implementation Notes

- `indexv3.html` is a new draft file. Do not rename or deploy in this plan.
- Prefer copying working behavior from `indexv2.html` over inventing new JS.
- Avoid `mix-blend-mode`, `transition: all`, and hover background fills on large cards.
- Keep RU as primary content and preserve EN translations already present where practical.

### Task 1: Create the indexv3 base file from indexv2

**Files:**
- Create: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3.html`
- Reference: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv2.html`

- [ ] **Step 1: Read the approved spec and source file boundaries**

Read:
- `docs/superpowers/specs/2026-05-14-indexv2-wellflow-inspired-design.md`
- `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv2.html`

Expected: confirm the section order and identify the script block, i18n dictionaries, hero markup, and card-heavy sections to preserve.

- [ ] **Step 2: Duplicate `indexv2.html` into `indexv3.html` before any redesign**

Run:

```powershell
Copy-Item "C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv2.html" "C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3.html" -Force
```

Expected: `indexv3.html` exists and is byte-for-byte identical to the current `indexv2.html` starting point.

- [ ] **Step 3: Verify the copied file is present and readable**

Run:

```powershell
Test-Path -LiteralPath "C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3.html"
```

Expected: `True`

- [ ] **Step 4: Commit the draft file creation checkpoint**

```bash
git add "C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3.html" docs/superpowers/plans/2026-05-14-indexv3-wellflow-inspired.md docs/superpowers/specs/2026-05-14-indexv2-wellflow-inspired-design.md
git commit -m "feat: scaffold indexv3 landing draft"
```

### Task 2: Rebuild the page-level visual system

**Files:**
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3.html`

- [ ] **Step 1: Replace the top-level CSS variable set with a calmer dark palette**

Update the `:root` block so it uses a darker base and cooler ambient colors while preserving a Scarlex accent:

```css
:root {
  --bg: #06070b;
  --bg-soft: #0b0d14;
  --panel: rgba(13, 16, 26, 0.78);
  --panel-strong: rgba(16, 20, 32, 0.92);
  --surface: rgba(255,255,255,0.035);
  --surface-2: rgba(255,255,255,0.05);
  --border: rgba(255,255,255,0.08);
  --border-2: rgba(167, 139, 250, 0.22);
  --text: #f5f7fb;
  --text-2: #a8b0c3;
  --text-3: rgba(224,229,240,0.52);
  --brand: #ff7d3d;
  --brand-2: #8b5cf6;
  --ok: #73f0bc;
  --blue: #7db7ff;
  --radius-xl: 28px;
  --radius-lg: 22px;
  --radius-md: 16px;
  --radius-sm: 10px;
  --shadow-lg: 0 24px 80px rgba(0,0,0,0.42);
  --shadow-md: 0 16px 40px rgba(0,0,0,0.30);
  --gradient-brand: linear-gradient(135deg, #a78bfa 0%, #7c9cff 55%, #ff7d3d 100%);
}
```

- [ ] **Step 2: Simplify the background treatment**

Replace the existing background-orb styling with a quieter version that avoids visual dominance:

```css
.bg-grid {
  position: absolute; inset: 0;
  background-image:
    linear-gradient(rgba(255,255,255,0.018) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,0.018) 1px, transparent 1px);
  background-size: 64px 64px;
  mask-image: radial-gradient(circle at 50% 18%, black 28%, transparent 75%);
  -webkit-mask-image: radial-gradient(circle at 50% 18%, black 28%, transparent 75%);
  opacity: .55;
}
.bg-orb { position: absolute; border-radius: 999px; filter: blur(160px); opacity: .18; }
.bg-orb-1 { width: 460px; height: 460px; left: -140px; top: -120px; background: rgba(124,156,255,0.65); }
.bg-orb-2 { width: 380px; height: 380px; right: -120px; top: 20px; background: rgba(139,92,246,0.55); }
.bg-orb-3 { width: 520px; height: 520px; left: 38%; top: 720px; background: rgba(255,125,61,0.20); }
```

- [ ] **Step 3: Unify section spacing and page rhythm**

Adjust the section and heading spacing to create more breathing room:

```css
.section { padding: 104px 0; position: relative; }
.section-head { max-width: 720px; margin: 0 auto 56px; text-align: center; }
.eyebrow {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 999px;
  background: rgba(167,139,250,0.08);
  border: 1px solid rgba(167,139,250,0.16);
  color: #c9bcff;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  margin-bottom: 18px;
}
.h2 {
  color: var(--text);
  font-weight: 800;
  letter-spacing: -0.03em;
  line-height: 1.02;
  font-size: clamp(2rem, 4vw, 3.25rem);
  margin-bottom: 16px;
}
.lead {
  color: var(--text-2);
  font-size: 17px;
  line-height: 1.7;
  max-width: 620px;
  margin: 0 auto;
}
```

- [ ] **Step 4: Replace the current `.gradient` usage with a restrained variant**

Use:

```css
.gradient {
  background-image: linear-gradient(135deg, #eef2ff 0%, #c4b5fd 52%, #8ab4ff 100%) !important;
  -webkit-background-clip: text !important;
  background-clip: text !important;
  -webkit-text-fill-color: transparent !important;
  color: transparent !important;
  display: inline-block;
}
```

- [ ] **Step 5: Validate the page still has a single `<style>` and `<script>` block**

Run:

```powershell
$html = Get-Content -Raw "C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3.html"; @("<style>","</style>","<script>","</script>") | ForEach-Object { "$_ $(([regex]::Matches($html,[regex]::Escape($_))).Count)" }
```

Expected: each tag count is `1`.

### Task 3: Redesign nav and hero composition

**Files:**
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3.html`

- [ ] **Step 1: Restyle the nav to a quieter product-style header**

Replace the nav-related CSS with:

```css
.navbar {
  position: sticky; top: 0; z-index: 50;
  backdrop-filter: blur(18px) saturate(135%);
  -webkit-backdrop-filter: blur(18px) saturate(135%);
  background: rgba(6, 7, 11, 0.70);
  border-bottom: 1px solid rgba(255,255,255,0.06);
}
.nav-inner { display: flex; align-items: center; justify-content: space-between; padding: 14px 0; }
.nav-left { display: flex; align-items: center; gap: 14px; }
.nav-left img { height: 34px; }
.nav-right { display: flex; align-items: center; gap: 8px; }
.nav-link {
  padding: 8px 12px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 500;
  color: var(--text-2);
  transition: color .15s ease, background-color .15s ease;
}
.nav-link:hover { background: rgba(255,255,255,0.04); color: var(--text); }
.lang-switch {
  display: inline-flex;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 999px;
  padding: 3px;
  margin-left: 10px;
}
.lang-switch button {
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-3);
  transition: color .15s ease, background-color .15s ease;
}
.lang-switch button.is-active { background: rgba(255,255,255,0.08); color: var(--text); }
```

- [ ] **Step 2: Replace the hero layout CSS with a larger, calmer layout**

Use:

```css
.hero {
  padding: 72px 0 92px;
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(420px, .9fr);
  gap: 56px;
  align-items: center;
}
@media (max-width: 980px) {
  .hero { grid-template-columns: 1fr; gap: 34px; padding: 44px 0 72px; }
}
.hero-left { max-width: 640px; }
.hero-eyebrow {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 7px 14px;
  border-radius: 999px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.08);
  color: var(--text-2);
  font-size: 12px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase;
  margin-bottom: 24px;
}
.hero h1 {
  font-size: clamp(3.3rem, 7vw, 5.8rem);
  font-weight: 850;
  color: var(--text);
  line-height: .98;
  letter-spacing: -0.05em;
  margin-bottom: 22px;
}
.hero h1 .price { color: #d8c8ff; }
.hero-sub {
  color: var(--text-2);
  font-size: 18px;
  line-height: 1.72;
  max-width: 560px;
  margin-bottom: 28px;
}
.hero-badges { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
.hero-badge {
  padding: 8px 12px;
  border-radius: 999px;
  background: rgba(255,255,255,0.035);
  border: 1px solid rgba(255,255,255,0.08);
  font-size: 12px;
  font-weight: 500;
  color: var(--text-2);
  display: inline-flex; align-items: center; gap: 6px;
}
```

- [ ] **Step 3: Rework the hero terminal shell into a premium panel**

Update the terminal CSS to:

```css
.terminal {
  position: relative;
  border-radius: 28px;
  background: linear-gradient(180deg, rgba(12,14,22,0.96) 0%, rgba(9,11,17,0.94) 100%);
  border: 1px solid rgba(255,255,255,0.08);
  overflow: hidden;
  box-shadow: var(--shadow-lg), inset 0 1px 0 rgba(255,255,255,0.03);
}
.terminal::before {
  content: '';
  position: absolute; inset: 0;
  background: radial-gradient(circle at 10% 0%, rgba(124,156,255,0.10), transparent 42%), radial-gradient(circle at 100% 100%, rgba(139,92,246,0.10), transparent 34%);
  pointer-events: none;
}
.term-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 18px;
  background: rgba(255,255,255,0.025);
  border-bottom: 1px solid rgba(255,255,255,0.06);
}
.term-title { font-size: 11px; color: var(--text-3); letter-spacing: 0.08em; font-family: "JetBrains Mono"; }
.term-body {
  padding: 24px 24px 28px;
  min-height: 430px;
  max-height: 500px;
  overflow: hidden;
  font-family: "JetBrains Mono", monospace;
  font-size: 12.5px;
  line-height: 1.62;
  color: #e6ebf5;
  white-space: pre-wrap;
  word-break: break-word;
}
.term-body .ln-cmd { color: #f7f9ff; }
.term-body .ln-flag { color: #c7b8ff; }
.term-body .ln-str { color: #8fe4c1; }
.term-body .ln-key { color: #96b9ff; }
.term-body .ln-num { color: #f5cf8f; }
.term-body .ln-comment { color: rgba(255,255,255,0.35); font-style: italic; }
```

- [ ] **Step 4: Tighten the hero copy wrapping without changing the approved message**

Update the hero markup so the H1 breaks remain intentional and minimal, following this structure:

```html
<h1 data-i18n-html="hero.title">
  Claude API.<br>
  <span class="price">От 9.8 ₽ за 1M</span><br>
  токенов, без VPN<br>
  и в разы дешевле Anthropic.
</h1>
```

Then update the RU/EN i18n dictionaries so `hero.title` contains the same structure in each language and remove dependence on the previous `hero.t1` ... `hero.t6` fragments for the H1 only.

- [ ] **Step 5: Run a syntax sanity check after the hero rewrite**

Run:

```powershell
node -e "var fs=require('fs');var html=fs.readFileSync('C:/Users/lucky/OneDrive/Desktop/project/scarlex_v2.02/indexv3.html','utf8');var m=html.match(/<script>([\s\S]*?)<\/script>/);try{new Function(m[1]);console.log('OK')}catch(e){console.log('ERR:',e.message)}"
```

Expected: `OK`

### Task 4: Unify all card systems into a premium glass language

**Files:**
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3.html`

- [ ] **Step 1: Replace the shared card styles**

Use:

```css
.card,
.model-card,
.price-card,
.step,
.faq-item,
.code-tabs,
.price-compare {
  background: rgba(14,17,27,0.72);
  border: 1px solid rgba(255,255,255,0.08);
  box-shadow: 0 10px 30px rgba(0,0,0,0.16), inset 0 1px 0 rgba(255,255,255,0.025);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
}
```

- [ ] **Step 2: Replace `.card` styles for the Why section**

Use:

```css
.card {
  padding: 28px;
  border-radius: 24px;
  transition: transform .22s ease, border-color .22s ease, box-shadow .22s ease;
  position: relative;
}
.card:hover {
  transform: translateY(-4px);
  border-color: rgba(167,139,250,0.22);
  box-shadow: 0 18px 36px rgba(7,10,18,0.38), 0 0 0 1px rgba(167,139,250,0.06);
}
.card-icon {
  width: 48px; height: 48px;
  border-radius: 14px;
  background: rgba(124,156,255,0.10);
  border: 1px solid rgba(124,156,255,0.16);
  display: flex; align-items: center; justify-content: center;
  color: #b4c9ff;
  margin-bottom: 18px;
}
.card h3 { color: var(--text); font-size: 19px; font-weight: 700; margin-bottom: 10px; letter-spacing: -0.02em; }
.card p { color: var(--text-2); font-size: 14px; line-height: 1.7; }
```

- [ ] **Step 3: Replace model card styles**

Use:

```css
.model-card {
  padding: 24px;
  border-radius: 24px;
  transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
}
.model-card:hover {
  transform: translateY(-3px);
  border-color: rgba(124,156,255,0.28);
  box-shadow: 0 18px 36px rgba(7,10,18,0.32);
}
.model-card.is-new { border-color: rgba(115,240,188,0.18); }
.model-card.is-fast { border-color: rgba(125,183,255,0.18); }
.model-name { color: var(--text); font-size: 19px; font-weight: 720; letter-spacing: -0.02em; }
.model-desc { color: var(--text-2); font-size: 13px; line-height: 1.65; margin-bottom: 16px; min-height: 42px; }
.model-id-row {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 12px;
  background: rgba(7,10,18,0.72);
  border-radius: 12px;
  border: 1px solid rgba(255,255,255,0.08);
}
.model-id { flex: 1; color: #d9dfff; font-family: "JetBrains Mono"; font-size: 12px; }
```

- [ ] **Step 4: Replace pricing card styles**

Use:

```css
.price-card {
  position: relative;
  padding: 30px 26px;
  border-radius: 26px;
  display: flex;
  flex-direction: column;
  transition: transform .22s ease, border-color .22s ease, box-shadow .22s ease;
}
.price-card:hover {
  transform: translateY(-4px);
  border-color: rgba(124,156,255,0.25);
  box-shadow: 0 20px 40px rgba(7,10,18,0.36);
}
.price-card.is-trial {
  border-color: rgba(115,240,188,0.22);
  background: linear-gradient(180deg, rgba(18,24,27,0.88), rgba(12,16,24,0.88));
}
.price-card.is-popular {
  border-color: rgba(167,139,250,0.28);
  background: linear-gradient(180deg, rgba(20,18,33,0.92), rgba(13,16,26,0.88));
  box-shadow: 0 20px 42px rgba(12, 10, 24, 0.42);
}
.price-name { color: var(--text-3); font-size: 12px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 10px; }
.price-amount { color: var(--text); font-size: 38px; font-weight: 820; letter-spacing: -0.04em; line-height: 1; margin-bottom: 4px; }
.price-per-m { color: var(--text-3); font-size: 12px; margin-bottom: 8px; font-family: "JetBrains Mono"; }
```

- [ ] **Step 5: Replace step and FAQ card styles**

Use:

```css
.step {
  padding: 28px;
  border-radius: 24px;
  transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
}
.step:hover { transform: translateY(-3px); border-color: rgba(124,156,255,0.20); box-shadow: 0 16px 32px rgba(7,10,18,0.28); }
.faq-item {
  border-radius: 20px;
  overflow: hidden;
  transition: border-color .18s ease, box-shadow .18s ease;
}
.faq-item:hover { border-color: rgba(124,156,255,0.18); box-shadow: 0 10px 24px rgba(7,10,18,0.22); }
```

- [ ] **Step 6: Confirm no `transition: all` remains in `indexv3.html`**

Run:

```powershell
Select-String -Path "C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3.html" -Pattern "transition:\s*all"
```

Expected: no matches.

### Task 5: Refine pricing, code, CTA, and copy density

**Files:**
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3.html`

- [ ] **Step 1: Tighten pricing comparison and trust copy blocks visually**

Update these CSS blocks:

```css
.trust-strip {
  display: flex; flex-wrap: wrap; justify-content: center; gap: 18px;
  padding: 18px 0;
  border-top: 1px solid rgba(255,255,255,0.06);
  border-bottom: 1px solid rgba(255,255,255,0.06);
  color: var(--text-3);
  font-size: 12px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;
}
.price-compare {
  margin-top: 18px;
  border-radius: 22px;
  padding: 22px 24px;
  color: var(--text-2);
  line-height: 1.7;
}
.price-compare strong { color: var(--text); }
```

- [ ] **Step 2: Restyle the code examples section to look more like docs/devtools**

Use:

```css
.code-tabs {
  border-radius: 24px;
  overflow: hidden;
}
.code-tabs-head {
  display: flex; align-items: center; gap: 4px;
  padding: 10px 12px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  background: rgba(255,255,255,0.02);
}
.code-tab {
  padding: 10px 12px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-3);
  transition: color .15s ease, background-color .15s ease;
}
.code-tab:hover { color: var(--text); background: rgba(255,255,255,0.04); }
.code-tab.is-active { color: var(--text); background: rgba(255,255,255,0.06); border-bottom-color: transparent; }
.code-copy {
  margin-left: auto;
  padding: 8px 12px;
  font-size: 12px;
  color: var(--text-3);
  border-radius: 10px;
  transition: color .15s ease, background-color .15s ease;
}
.code-copy:hover { background: rgba(255,255,255,0.05); color: var(--text); }
.code-pane {
  display: none;
  padding: 22px 24px 26px;
  background: rgba(8,10,17,0.88);
}
.code-pane.is-active { display: block; }
.code-pane pre { margin: 0; overflow: auto; font-size: 13px; line-height: 1.7; color: #e8edf7; }
```

- [ ] **Step 3: Restyle the final CTA block into a restrained premium close**

Use:

```css
.cta-block {
  padding: 72px 42px;
  border-radius: 30px;
  background: linear-gradient(180deg, rgba(17,20,31,0.95), rgba(11,13,20,0.92));
  border: 1px solid rgba(255,255,255,0.08);
  text-align: center;
  position: relative;
  overflow: hidden;
  box-shadow: 0 24px 60px rgba(0,0,0,0.32);
}
.cta-block::before {
  content: '';
  position: absolute; inset: 0;
  background: radial-gradient(circle at 50% -10%, rgba(124,156,255,0.16), transparent 44%);
  pointer-events: none;
}
.cta-block h2 {
  color: var(--text);
  font-size: clamp(2.2rem, 4.2vw, 3.4rem);
  font-weight: 820;
  margin-bottom: 16px;
  letter-spacing: -0.04em;
}
.cta-block p {
  color: var(--text-2);
  font-size: 17px;
  line-height: 1.75;
  margin-bottom: 28px;
  max-width: 620px;
  margin-left: auto;
  margin-right: auto;
}
```

- [ ] **Step 4: Reduce overlong copy blocks only where wrapping hurts layout**

Edit text only where necessary to reduce visual clutter, while preserving meaning. Use these constraints:

```text
- Keep pricing facts unchanged.
- Keep Anthropic comparison unchanged in substance.
- Keep payment methods unchanged.
- Keep CTA destinations unchanged.
- Shorten only secondary descriptions that are visibly too wordy for the new layout.
```

- [ ] **Step 5: Validate the HTML still contains all required sections and IDs**

Run:

```powershell
$html = Get-Content -Raw "C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3.html"; foreach($id in @('pricing','models','faq','pane-curl','pane-py','pane-js','pane-oc','loginNavBtn','termBody','termStat')) { "$id => $($html.Contains($id))" }
```

Expected: all results are `True`.

### Task 6: Verify behavior and prepare user review

**Files:**
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3.html` if fixes are needed

- [ ] **Step 1: Run the JS syntax validation again on the final file**

Run:

```powershell
node -e "var fs=require('fs');var html=fs.readFileSync('C:/Users/lucky/OneDrive/Desktop/project/scarlex_v2.02/indexv3.html','utf8');var re=/<script>([\s\S]*?)<\/script>/g;var m,n=0;while(m=re.exec(html)){n++;try{new Function(m[1]);console.log('script #'+n+': OK')}catch(e){console.log('script #'+n+' ERR:',e.message)}}console.log('total scripts:',n)"
```

Expected:

```text
script #1: OK
total scripts: 1
```

- [ ] **Step 2: Confirm UTF-8 readability and key Russian strings**

Run:

```powershell
$bytes = [System.IO.File]::ReadAllBytes("C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3.html"); $txt = [System.Text.Encoding]::UTF8.GetString($bytes); @('Claude API','Скопировано','Какие модели доступны?','СБП') | ForEach-Object { "$_ => $($txt.Contains($_))" }
```

Expected: all `True`.

- [ ] **Step 3: Manual browser check**

Open:

```text
C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3.html
```

Check:

```text
- Hero feels calmer and more premium
- Cards no longer feel flashy
- Opera hover no longer whites out sections
- RU/EN switch still works
- Copy buttons still work
- Code tabs still switch
- Terminal animation still runs
- Mobile width remains readable
```

- [ ] **Step 4: Apply any final polish fixes from manual review**

If the review reveals issues, make the smallest possible CSS/HTML fix in `indexv3.html`, then rerun:

```powershell
node -e "var fs=require('fs');var html=fs.readFileSync('C:/Users/lucky/OneDrive/Desktop/project/scarlex_v2.02/indexv3.html','utf8');var m=html.match(/<script>([\s\S]*?)<\/script>/);try{new Function(m[1]);console.log('OK')}catch(e){console.log('ERR:',e.message)}"
```

Expected: `OK`

- [ ] **Step 5: Commit the finished draft**

```bash
git add "C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv3.html"
git commit -m "feat: redesign indexv3 landing page"
```

## Self-Review

- Spec coverage checked: the plan covers visual system, hero redesign, typography, card unification, reduced background noise, code/docs styling, CTA restraint, Opera-safe hover behavior, and preservation of section structure and existing behavior.
- Placeholder scan checked: no `TODO`, `TBD`, or vague "implement later" steps remain.
- Type consistency checked: all referenced IDs, file paths, and classes are consistent with the current landing structure and verification steps.
