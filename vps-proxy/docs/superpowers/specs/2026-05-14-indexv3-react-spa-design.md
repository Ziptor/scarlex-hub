# Indexv3 React SPA Design

## Summary

Build a new `indexv3` as a React + Vite product-style SPA that is visually much closer to `wellflow.dev` than the current HTML drafts. This is not a refinement of `indexv2` or the current `indexv3`; it is a new front-end system that preserves Scarlex business facts and working user flows while replacing the old landing-page structure and visual language.

The result should feel like the front page of a real AI product, not a conventional marketing landing page.

## Goals

- Make `indexv3` feel like a premium product SPA rather than a generic landing page.
- Get much closer to the visual discipline and product feel of `wellflow.dev`.
- Preserve Scarlex business facts:
  - Claude-only positioning
  - available models
  - pricing plans and 1M token pricing
  - Anthropic comparison
  - payment methods
  - auth CTA flow
- Preserve existing useful interactive behavior:
  - RU/EN switching
  - session-aware nav state
  - code tabs
  - copy buttons
  - terminal/typewriter concept
- Use React + Vite as the implementation base.

## Non-Goals

- Do not preserve the existing `indexv2` or current `indexv3` visual structure.
- Do not force the old section anatomy into the new SPA.
- Do not add backend features.
- Do not add complex application routing unless implementation clearly requires it.
- Do not produce a literal 1:1 clone of `wellflow.dev`.

## Product Direction

The new page should be a product-style SPA, not a marketing-section stack.

This means:

- the page is experienced as a sequence of product screens
- each major area looks like part of a real software product surface
- design emphasis comes from layout, type, panel hierarchy, and density rather than decorative gradients
- the selling happens through product credibility, not conventional landing-page tropes

## Technology Direction

## Stack

- React
- Vite
- vanilla CSS or lightweight CSS file organization
- no heavy UI framework by default

## Routing

Start with a single-route SPA.

- one React entrypoint
- no mandatory React Router requirement in v1
- section navigation can be handled by in-page anchors and app state

This preserves SPA architecture without adding unnecessary routing complexity.

## Project Shape

The implementation should be a new front-end app rather than another static HTML patch.

Expected shape:

- `src/main.jsx`
- `src/App.jsx`
- `src/components/...`
- `src/lib/content.js`
- `src/lib/i18n.js`
- `src/lib/session.js`
- `src/styles/...`

The exact file layout may vary slightly, but the design intent is clear separation between:

- app shell
- screen components
- shared components
- content/config data
- behaviors/utilities

## Visual Direction

## Core Aesthetic

The visual target is strongly influenced by `wellflow.dev`:

- dark premium base
- product-surface hierarchy
- strong typography
- sparse and disciplined use of accent color
- high-quality glass/panel treatment
- calm, dense, app-like composition

The new SPA should feel:

- product-like
- expensive
- technically credible
- restrained

It should not feel:

- noisy
- template-like
- gradient-driven
- like a generic "AI SaaS landing" clone

## Color System

Use a wellflow-like foundation with Scarlex accents.

- base background: deep graphite / black-blue
- secondary hues: slate / indigo / violet-blue
- accent glow: subtle cool violet-blue
- Scarlex orange: sparse, strategic brand accent only
- green: reserved for positive/status cues

The page should look primarily product-dark and cool-toned, with Scarlex orange acting as a brand signature, not the dominant atmosphere.

## Typography

Typography is a primary design tool.

- large, high-confidence H1 in hero
- large but disciplined H2s
- tighter supporting copy
- restrained metadata and labels
- monospace used for developer surfaces and metadata only

Gradient text should be used minimally, if at all.

## Background and Atmosphere

The background should be quiet.

- no heavy orb-based visual identity
- no loud glowing blobs
- no bright hover fill effects
- if ambient lighting exists, it should be subtle and cool-toned

The atmosphere should come from panel layering, spacing, and typography rather than large decorative effects.

## Screen Architecture

The SPA should be organized as a sequence of product screens.

## AppShell

Responsibilities:

- page background and container system
- top-level language state
- session-aware nav state
- scroll rhythm and layout composition

Contains:

- `TopNav`
- `HeroProduct`
- `ModelExperience`
- `DeveloperFlow`
- `PricingSurface`
- `TrustAndFAQ`
- `FinalAction`
- `Footer`

## TopNav

Should behave as a minimal product header rather than a landing-page navbar.

Includes:

- Scarlex branding
- compact anchors
- language switch
- auth state action (`Войти` / `Кабинет`)

Should remain visually light and sticky.

## HeroProduct

This is the highest-priority screen and must carry the strongest resemblance to the desired product-style reference.

Layout:

- left: large editorial/product messaging block
- right: premium product demo panel

Left content:

- small product label
- strong large H1
- concise supporting paragraph
- primary and secondary CTA
- compact trust chips

Right content:

- app-like panel shell
- terminal or response preview inside a refined interface
- metadata such as model, tokens, or latency
- preserved typewriter concept if it supports the product feel

The hero should immediately signal “working AI product” rather than “sales landing”.

## ModelExperience

This replaces a simple models grid with a product capability screen.

Behavior:

- one model can be active at a time
- model selection updates a preview area or metadata area

Required models:

- `claude-opus-4.7`
- `claude-opus-4.6`
- `claude-sonnet-4-thinking`
- `claude-sonnet-4`
- `claude-haiku-3.5`

This screen should make models feel like an actual usable capability layer, not just a list of cards.

## DeveloperFlow

This combines the intent of “how it works” and “code example” into a product/developer screen.

Includes:

- short narrative about integration speed
- code tabs:
  - cURL
  - Python
  - JavaScript
  - OpenCode
- endpoint and OpenAI-compatible framing
- copy-to-clipboard behavior

This screen should feel closer to docs or a devtool panel than to a typical marketing section.

## PricingSurface

Pricing should remain prominent, but be presented as product pricing rather than traditional sales cards.

Required plans:

- Trial
- Starter
- Basic
- Standard
- Pro
- Business

Required data:

- token amount
- total price
- price per 1M
- relative comparison to Anthropic where applicable
- CTA link into `authorization.html?plan=...`

The Anthropic comparison note must remain in substance, but can be visually reformatted.

## TrustAndFAQ

This is a compact credibility screen rather than multiple scattered sections.

Includes:

- trust / support / payment notes
- FAQ accordion
- no-VPN / RU / OpenAI-compatible reassurance where appropriate

This part should remain useful, but not break the product-style rhythm.

## FinalAction

Final closing screen.

Includes:

- strong closing line
- primary CTA
- secondary CTA if needed
- short reassurance line

It should close the SPA cleanly and confidently, without reverting to a bright marketing-banner style.

## Data Architecture

Business content should be stored as structured data, not scattered across components.

## Content Data

Create central content structures for:

- models
- pricing plans
- code snippets
- FAQ items
- trust facts
- CTA links

This content should be render-driven from data objects so the UI remains clean and maintainable.

## I18n

Use a lightweight custom i18n approach.

- default language: `ru`
- alternative language: `en`
- state stored in `localStorage`
- dictionary-based text source

Avoid introducing a large i18n framework unless implementation reveals a real need.

## Session State

Keep the existing session-aware nav behavior.

- fetch `api/check_session.php`
- if authenticated, show `Кабинет` or user-aware state
- if unauthenticated, show `Войти`
- if request fails, default to safe unauthenticated UI

This logic should be isolated in a helper or small hook-like utility.

## Behaviors

Retain only purposeful interaction.

Required:

- language switching
- session-aware nav
- code tab switching
- copy buttons
- terminal/typewriter concept
- model selection state
- subtle scroll reveal or panel polish if useful

Avoid:

- heavy parallax
- 3D/canvas spectacle
- overcomplicated animation systems
- animation that interferes with clarity or browser stability

## Styling Architecture

CSS should be organized for clarity rather than novelty.

Recommended separation:

- app-level foundation styles
- layout styles
- shared component styles
- screen styles

CSS Modules are not required. The goal is maintainable styling, not CSS abstraction for its own sake.

## Preservation Rules

## Must Preserve

- business facts and pricing logic
- model IDs and available model list
- Anthropic comparison facts
- payment methods: SBP, card, CryptoBot
- links into `authorization.html`
- RU-first messaging with EN option
- session-aware login/dashboard behavior
- developer snippets and copy behavior

## Can Be Rebuilt Freely

- hero composition
- visual system
- page flow
- section ordering within the approved product-style SPA shape
- wording polish, so long as business meaning remains intact
- panel/card patterns
- FAQ placement within the trust screen

## Explicitly Discarded From Old Drafts

- old static section rhythm
- heavy gradient-driven identity
- loud orb/glow styling
- conventional landing-card treatment
- dependence on the old `indexv2`/`indexv3` markup as a design base

## Quality Criteria

The implementation should be considered successful if:

1. the page feels like a real AI product front page rather than a generic landing page
2. the visual quality is much closer to `wellflow.dev`
3. Scarlex identity and business facts remain clear
4. the developer-facing parts are compelling enough to encourage deeper scrolling
5. interactions remain practical and stable
6. the user reaction is materially improved from the rejected draft

## Implementation Strategy Recommendation

Treat this as a new SPA project, not as a restyle of the old HTML draft.

Recommended strategy:

- scaffold a fresh React + Vite front-end for `indexv3`
- extract business content into structured data
- build the product screens as React components
- port only the behaviors worth preserving
- validate visually against the desired product-style target, not against the old draft
