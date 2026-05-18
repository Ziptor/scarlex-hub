# Indexv2 Wellflow-Inspired Redesign

## Summary

Redesign `indexv2.html` from a flashy landing-page aesthetic into a calmer, more premium, product-site look inspired by `wellflow.dev`, while keeping Scarlex branding, the existing section structure, and the Claude-only commercial message.

This is a visual redesign, not a scope expansion. The content model, CTA flow, pricing, models, i18n support, and session-aware nav behavior remain in place. The main change is the visual system: typography, spacing, hero composition, card treatment, and overall restraint.

## Goals

- Make the page feel more premium, minimal, and product-like.
- Preserve the current business messaging and conversion structure.
- Keep the landing fully static: vanilla HTML/CSS/JS only.
- Avoid browser rendering fragility like the previous Opera hover issue.
- Keep the page visually distinct from the current `index.html`, but not as noisy or effects-heavy as the current `indexv2.html` draft.

## Non-Goals

- No backend changes.
- No pricing/content policy changes.
- No new sections beyond the current approved landing structure.
- No framework migration.
- No attempt to clone `wellflow.dev` exactly.

## Constraints

- Work in `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\indexv2.html`.
- Russian remains the primary language; English i18n stays supported.
- CTA buttons continue to target `authorization.html` and section anchors.
- Existing terminal/typewriter concept stays, but is visually toned down.
- Must remain stable in Opera and Edge.

## Design Direction

### Overall Visual Language

The page should shift from a bright marketing aesthetic to a darker, cleaner developer-product aesthetic. The visual mood should be:

- calmer
- more spacious
- more typographically driven
- less gradient-heavy
- more "serious dev tool" than "promo microsite"

The visual reference taken from `wellflow.dev` is not exact layout parity but the sense of restraint: dark background, large confident typography, strong content hierarchy, soft glass surfaces, and fewer decorative distractions.

### Color System

Use a dark neutral foundation with cooler hues as the dominant atmospheric color.

- Primary page background: near-black / graphite.
- Ambient accent: indigo / violet / muted blue glows.
- Brand accent: Scarlex orange remains available, but only for key emphasis:
  - primary CTA
  - small accent text
  - active states where appropriate
- Pink should be reduced or removed from dominant use.

This keeps Scarlex recognizable without making the page feel loud.

### Typography

Typography becomes the primary design tool.

- Hero H1 should become larger, cleaner, and less decorated.
- Section headings should be shorter, stronger, and more product-like.
- Secondary text should be reduced in length where possible and given more breathing room.
- Gradient-highlighted words should be used sparingly, only in a few intentional places.

The desired effect is stronger hierarchy and less visual fatigue.

## Information Architecture

Keep the existing approved section order:

1. Nav
2. Hero
3. Trust bar
4. Why V2
5. Models
6. Pricing
7. How it works
8. Code examples
9. FAQ
10. CTA
11. Footer

The content and order remain intact because the current structure matches the commercial intent well. Only the presentation changes.

## Section Design

### Nav

Keep the current behavior but simplify the appearance.

- More minimal spacing and less "chip-like" feel.
- Lighter visual presence so it frames the page instead of competing with hero.
- Preserve session-aware button behavior:
  - unauthenticated: login action
  - authenticated: dashboard/user state
- Keep RU/EN switch, but style it more quietly.

### Hero

Hero is the most important redesign area.

#### Layout

Keep a two-column structure on desktop:

- left: messaging
- right: product-style terminal panel

On mobile, stack vertically with the message first.

#### Left Column

Include:

- compact eyebrow
- large H1
- tighter subheadline
- primary and secondary CTA
- small trust pills beneath actions

The text should feel more confident and less like stacked marketing fragments.

#### Right Column

Keep the terminal/typewriter concept, but restyle it as a premium product panel.

- darker glass shell
- subtler border and shadow
- less saturated syntax colors
- calmer header treatment
- overall less visual noise

The typewriter remains the same feature conceptually, but should read like a refined demo panel rather than a flashy gimmick.

### Trust Bar

Make it thinner and more understated.

- less contrast
- more even spacing
- product-site metadata feel

It should support credibility without drawing focus away from hero.

### Why V2

Keep 4 cards, but redesign them into consistent premium glass cards.

- more spacing
- cleaner icon treatment
- stronger title hierarchy
- less decorative color

These cards should feel like product benefits, not promotional tiles.

### Models

Keep the flat grid and copyable model IDs.

Redesign goals:

- make each card feel denser and more product-oriented
- make the model id row look like a serious developer UI element
- reduce "showcase card" energy and increase "dashboard card" energy

Tags like `NEW` or `FAST` should remain but appear more restrained.

### Pricing

Pricing remains one of the key conversion sections and should feel cleaner and more credible.

- cards should align visually with models/benefits
- the featured plan should be highlighted, but not with loud gradients
- information density should be improved through cleaner typography rather than more effects

The pricing comparison block under the grid should remain and be styled more like an editorial/product note than a banner.

### How It Works

Keep the 3-step flow, but align it visually with the rest of the page.

- clear numbering
- compact copy
- consistent card system

This section should feel instructional rather than decorative.

### Code Examples

This section should move closer to a developer-tool aesthetic.

- tabs become cleaner and more product-like
- code panel styling should feel closer to IDE/docs UI
- copy button remains but should be visually quieter
- syntax highlight remains lightweight and local, no external library

### FAQ

Keep FAQ as details/summary, but soften its styling.

- less chunky borders
- more refined spacing
- less visual contrast spikes on hover/open

### CTA

The final CTA should still feel stronger than surrounding sections, but in a controlled way.

- stronger typography
- premium surface
- subtle accent lighting
- no loud gradient slab

### Footer

Keep simple and minimal. It should visually disappear compared to the rest of the page.

## Component System

Unify the page around a single glass-card language.

### Card Principles

All major cards should share:

- similar radius system
- subtle semi-transparent dark surface
- thin border
- restrained shadow
- hover response using movement + border/shadow, not bright fill changes

This applies to:

- benefit cards
- model cards
- pricing cards
- step cards
- FAQ items where practical

### Hover Behavior

Hover behavior must remain browser-stable.

- Do not rely on blend modes.
- Avoid hover styles that animate broad background whitening.
- Avoid `transition: all` on complex containers.
- Prefer animating:
  - `transform`
  - `border-color`
  - `box-shadow`

Opera compatibility is an explicit requirement because the current page already exposed cross-browser rendering issues.

## Background Treatment

Background should be simplified significantly.

- Remove or greatly reduce decorative orb dominance.
- If ambient lighting remains, it should be soft and cool-toned.
- The page should feel grounded by surface hierarchy, not by large glow effects.

The grid background can remain if it is subtle and not visually busy.

## Motion

Motion should be reduced and refined.

- Keep fade-in on scroll.
- Keep terminal typewriter.
- Remove any effect that feels decorative without improving comprehension.
- Use hover motion conservatively.

The design goal is polished calmness, not spectacle.

## Content Handling

The approved messaging stays intact with only minor copy tightening where needed for visual rhythm.

Specific content that must remain conceptually preserved:

- Claude-only positioning
- price-per-1M framing
- Anthropic comparison
- RU payment methods: SBP, card, CryptoBot
- no-VPN positioning
- OpenAI-compatible message
- CTA to auth flow

## Technical Plan Boundaries

Implementation should primarily touch:

- CSS variables and visual styles
- section layout spacing
- hero markup/classes if needed
- card markup/classes where consistency is required

Implementation should avoid unnecessary JS changes unless required by the new layout.

## Testing Expectations

The redesign should be validated for:

- local rendering in desktop browser
- mobile responsiveness
- RU/EN switching still functioning
- session-aware nav behavior still functioning
- copy buttons and code tabs still functioning
- terminal animation still functioning
- Opera hover stability
- no broken encoding issues

## Open Decisions Resolved

The user approved the following design direction:

- use wellflow-inspired minimalism
- strengthen typography
- redesign cards into more premium glass surfaces
- restructure hero visually without changing its purpose
- keep current section structure rather than doing a full scope reset

## Recommended Implementation Approach

Use a medium redesign rather than a full rebuild:

- preserve the approved information architecture
- replace the current visual system
- refine hero composition
- unify cards and typography

This is the best tradeoff between speed, safety, and achieving the requested visual improvement.
