# Indexv3 React SPA Restart Design

## Summary

The current `indexv3-spa` is a failed visual direction and should be treated as a technical scaffold only. Keep the React + Vite foundation, but discard the current visual architecture, component composition, spacing logic, card language, and overall product presentation.

The new goal is an aggressive restart of the front-end so the result lands much closer to the visual class and product feel of `wellflow.dev`. This does not mean literal brand cloning, but it does mean maximizing similarity in layout grammar, visual density, panel hierarchy, typography, and whole-page composition.

## Goals

- Rebuild `indexv3-spa` so it no longer feels like a generic SaaS or landing page.
- Make the visual result much closer to `wellflow.dev` in overall design class.
- Preserve Scarlex business facts and essential behaviors while fully replacing the current product-page composition.
- Use the existing React + Vite project only as a technical base, not as a design base.

## Non-Goals

- Do not preserve the current `indexv3-spa` component architecture just because it already exists.
- Do not keep the current screen layout if it harms the target result.
- Do not optimize for minimal code churn.
- Do not compromise toward a "good enough SaaS landing" look.

## Core Decision

The current SPA should be considered a discarded visual prototype.

What remains useful:

- React + Vite scaffold
- build pipeline
- content facts
- auth/session concept
- i18n concept

What should be discarded and redesigned:

- page composition
- hero composition
- panel language
- card system
- spacing rhythm
- typography hierarchy
- CTA treatment
- overall visual tone

## Design Strategy

## Work Sequence

The redesign must proceed in this order:

1. recreate the visual grammar first
2. establish the page skeleton second
3. only then map Scarlex content into it

This order is mandatory because previous attempts started from content blocks and then tried to style them, producing a generic result.

## Visual Target

Target the same design class as `wellflow.dev`:

- restrained dark product aesthetic
- strong typography-led composition
- dense but controlled layout
- product-window feel in hero and proof areas
- fewer, stronger surfaces
- lower reliance on rounded cards and decorative gradients

The page should read as a product front page, not a stack of marketing sections.

## Page Skeleton

The new page should be structured as a unified product canvas with a few large compositional zones rather than many evenly weighted sections.

## Zone 1: Top Header

The header should be:

- thin
- visually quiet
- stable
- minimally interactive

Includes:

- Scarlex mark/wordmark
- a few compact navigation items
- language switch
- auth action

The header should support the hero, not compete with it.

## Zone 2: Massive Hero Canvas

This is the central scene of the entire page.

It should occupy more visual weight than a standard landing hero and must feel like a product canvas, not a text-and-card split.

### Left Side

Contains:

- a very small quiet label
- a large product statement H1
- a short supporting paragraph
- primary and secondary action row
- a compressed proof/metadata line

### Right Side

Contains a product window, not a terminal card.

This product window should feel like a real application surface with:

- app chrome or view controls
- structured content region
- product metadata
- a possible terminal/typewriter layer embedded within a larger UI shell

The right side must no longer read as a generic glass card or a decorative code mock.

## Zone 3: Product Proof Zone

Immediately after hero, the page should prove itself through interface-like product surfaces rather than conventional explanatory sections.

This zone should combine what were previously separate blocks:

- models
- integration/dev proof
- short infrastructure/value signals

This area should feel like the continuation of the product, not the beginning of the marketing narrative.

## Zone 4: Pricing Decision Zone

Pricing remains important but should be redesigned as a product decision interface rather than a set of colorful pricing cards.

It should feel:

- denser
- more system-like
- more interface-like
- less card-grid-like

It may still use multiple plans, but the plans should appear as part of a clean decision matrix with strong numerical hierarchy.

Anthropic comparison remains, but as pricing context rather than promo copy.

## Zone 5: Lower Utility Zone

The lower part of the page should be quieter and more compressed.

This includes:

- trust signals
- payments/support clarity
- FAQ
- final CTA
- footer

This zone should feel like a lower-intensity utility/reassurance layer, not a continuation of hero-level drama.

## Visual Grammar

## Typography

Typography is the primary structural tool.

Required characteristics:

- dominant H1
- editorial/product-level confidence
- shorter supporting lines
- cleaner metadata text
- more disciplined text scale differences

The headline should not feel like ad copy. It should feel like a product-defining statement.

## Geometry

The current UI is too soft and rounded.

The restart should move toward:

- smaller radii
- more rectilinear surfaces
- tighter geometry
- less toy-like button and panel shapes

Large pill-heavy UI should be avoided.

## Surface Hierarchy

Use fewer, stronger surface types.

Surfaces should feel:

- product-like
- structured
- dense
- calm

They should not feel like decorative glass cards.

Heavy glassmorphism is explicitly discouraged.

## Color

Color must support structure, not replace it.

Use:

- dark graphite / black-blue foundation
- quiet cool-toned atmosphere
- sparse accent usage
- Scarlex orange only as a deliberate brand accent

Avoid:

- bright multicolor gradient identity
- large glow blobs
- color-driven page hierarchy

## Composition Rules

The page should not use the usual rhythm of:

- section title
- paragraph
- three cards

Instead, composition should be more asymmetric and product-interface-like.

Desired traits:

- stronger massing
- larger primary scene
- fewer repeated section patterns
- more panel adjacency
- more whole-page cohesion

## Content Mapping Rules

Scarlex content must be preserved, but the current presentation model can be replaced completely.

## Must Preserve

- Claude-only positioning
- model list and model IDs
- pricing plans and 1M framing
- Anthropic comparison facts
- payment methods
- OpenAI-compatible positioning
- auth entrypoints
- RU-first support with EN option
- session-aware login/dashboard behavior
- developer code snippets and copy behavior

## Can Be Remapped Freely

- page order
- screen boundaries
- wording tone
- layout of model information
- layout of pricing information
- placement of FAQ within lower utility zone
- treatment of trust facts

## Hard Visual Prohibitions

The following should not reappear in the restart:

- generic SaaS landing section rhythm
- large rounded glass cards everywhere
- oversized pill buttons and chips everywhere
- heavy glow/blob atmosphere
- evenly repeated card-based sections
- decorative gradients as the main source of visual interest
- soft "friendly startup" UI tone

## Technical Direction

The project remains React + Vite.

However, component architecture should be re-derived from the new visual skeleton, not from the current screen list.

This means existing components may be deleted, merged, or replaced if they reinforce the wrong page structure.

## Behavioral Direction

Keep only useful interactions:

- auth-aware nav
- language switching
- code tabs
- copy interactions
- any demo interaction that strengthens product feel

Do not add spectacle interactions that distract from the layout target.

## Success Criteria

The restart is successful only if:

1. the page no longer reads as a standard SaaS landing page
2. the hero feels like a product scene rather than a text-plus-card hero
3. the whole page feels like a cohesive system rather than a stack of sections
4. pricing no longer feels like a generic pricing-card grid
5. the visual class is recognizably much closer to `wellflow.dev`
6. the user's reaction is materially better than to the rejected version

## Recommended Implementation Strategy

The correct implementation strategy is:

- keep the React/Vite app shell
- delete or rewrite the current visual/component structure aggressively
- build the new page from visual skeleton first
- only then remap Scarlex content into the new layout

This is a front-end restart, not a refactor.
