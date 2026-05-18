# Indexv4 Pricing Surface Refresh Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the `04 / ЦЕНЫ` cards in `indexv4` look lighter and cleaner without changing the pricing structure or content.

**Architecture:** Keep the existing HTML intact and scope the refresh to pricing-specific CSS selectors in `v4style.css` so hero, features, and code blocks are unaffected. The popular plan gets a slightly brighter panel treatment than the rest, but no glow overlays or heavy hover motion.

**Tech Stack:** Static HTML, vanilla CSS, existing `indexv4.html` + `v4style.css`

---

### Task 1: Adjust Base Pricing Card Surface

**Files:**
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\v4style.css`
- Verify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv4.html`

- [ ] **Step 1: Inspect the existing pricing selectors**

Open `v4style.css` and locate this block:

```css
.pr-card{padding:28px;display:flex;flex-direction:column;position:relative}
.pr-card.pop{border-color:var(--s5);background:rgba(17,17,20,.9)}
.pr-card.pop::after{content:'';position:absolute;inset:0;border-radius:var(--radius);background:radial-gradient(circle at 50% 0%,rgba(255,255,255,.04),transparent 50%);pointer-events:none}
```

- [ ] **Step 2: Replace the base pricing card style with a lighter panel treatment**

Update the pricing block to:

```css
.pr-card{padding:28px;display:flex;flex-direction:column;position:relative;border-color:rgba(255,255,255,.09);background:rgba(24,24,28,.92);box-shadow:none}
.pr-card:hover{border-color:rgba(255,255,255,.14);background:rgba(28,28,33,.96);transform:none;box-shadow:none}
```

- [ ] **Step 3: Save the CSS change**

No other selectors should be changed in this step.

- [ ] **Step 4: Reload `indexv4.html` in the browser and verify the base cards are lighter**

Expected:
- cards are more separated from the page background
- cards no longer feel smoky/faded
- no other sections visually change

### Task 2: Tone the Popular Card Above the Rest

**Files:**
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\v4style.css`
- Verify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv4.html`

- [ ] **Step 1: Remove the glow overlay from the popular card**

Delete this selector entirely:

```css
.pr-card.pop::after{content:'';position:absolute;inset:0;border-radius:var(--radius);background:radial-gradient(circle at 50% 0%,rgba(255,255,255,.04),transparent 50%);pointer-events:none}
```

- [ ] **Step 2: Replace the popular card style with a slightly brighter surface**

Update the `.pr-card.pop` rule to:

```css
.pr-card.pop{border-color:rgba(255,255,255,.18);background:rgba(34,34,40,.98)}
```

- [ ] **Step 3: Verify the visual hierarchy manually**

Reload `indexv4.html` and confirm:
- the popular card is visible as the recommended plan
- it is only slightly brighter than the other cards
- there is no glow, radial flare, or banner-like effect

### Task 3: Keep the Rest of Pricing Calm and Consistent

**Files:**
- Modify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\v4style.css`
- Verify: `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv4.html`

- [ ] **Step 1: Tighten the CTA and badge contrast only if needed**

If the lighter cards make the CTA or badge too weak, update only these selectors:

```css
.pr-badge{position:absolute;top:-10px;right:20px;padding:4px 10px;border-radius:999px;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--bg);background:var(--white)}
.pr-card .btn-p,.pr-card .btn-g{width:100%;justify-content:center}
```

Allowed changes:
- keep text readable
- keep the badge visible
- do not change layout
- do not add glow or shadows

- [ ] **Step 2: Verify pricing still matches the approved layout**

Check that all of these remain unchanged:
- plan order
- price text
- token amounts
- CTA placement
- “show more” behavior

- [ ] **Step 3: Final manual acceptance check**

Reload `indexv4.html` and confirm:
- pricing blocks are lighter inside
- popular card is slightly brighter than the rest
- pricing section fits the same overall visual system as hero/features/integration
- no layout shifts or regressions are introduced

## Self-Review

- **Spec coverage:** The plan covers lighter base pricing cards, a slightly brighter popular card, removal of glow, and keeping all content/layout intact.
- **Placeholder scan:** No `TODO`, `TBD`, or vague “handle appropriately” language remains.
- **Type consistency:** All referenced selectors match the current `v4style.css` naming: `.pr-card`, `.pr-card.pop`, `.pr-badge`, `.btn-p`, `.btn-g`.
