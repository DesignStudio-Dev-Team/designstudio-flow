# DesignStudio Flow Landing Page Design System

## Instructions for Claude

Use this document as the authoritative design brief for a marketing landing page promoting DesignStudio Flow, also called DSFlow.

Create a complete, responsive landing page from announcement bar and header through the footer. Every design decision should reinforce the product's central idea: DSFlow provides creative freedom inside a carefully controlled WordPress design system.

Do not create a generic SaaS website. Avoid purple gradients, glassmorphism, excessive floating cards, oversized decorative headlines, fake analytics, and visual effects that do not explain the product.

When a requirement is ambiguous, prioritize decisions in this order:

1. Clarity and conversion
2. Visual consistency
3. Accessibility
4. Responsive usability
5. Product accuracy
6. Decorative novelty

If producing code, use semantic HTML, accessible controls, reusable components, CSS custom properties, and responsive layouts. Keep content easy to replace. Do not invent customer logos, testimonials, performance statistics, prices, or security certifications.

---

## Product Definition

DesignStudio Flow is a curated WordPress page builder for creating polished, responsive pages from pre-coded content blocks.

Instead of giving editors an unrestricted blank canvas, DSFlow provides intentional layouts with useful customization boundaries. This helps agencies, designers, developers, marketers, and site owners build pages that remain consistent, responsive, secure, and maintainable.

### Core Product Capabilities

- Curated visual block library
- Inline text editing
- WordPress Media Library integration
- Drag-and-drop block ordering
- Content, style, and responsive controls
- Desktop, tablet, and mobile previews
- Theme-level typography and color settings
- Headers, mega menus, mobile navigation, and footers
- WooCommerce product, category, brand, and promotion blocks
- Page-specific popups and campaign controls
- Link and modal CTA actions
- WordPress page title, slug, status, and parent settings
- SEO-friendly saved HTML snapshots
- Secure validation, sanitization, escaping, nonce, and capability patterns
- Shared block rendering between the editor and frontend

### Product Philosophy

Use these ideas throughout the page:

- Curated, not restrictive
- Flexible, not fragile
- Consistent by default
- Difficult to break
- Built for real WordPress workflows
- Designed for both creators and maintainers

### Primary Positioning Statement

> The freedom to build, without the freedom to break everything.

### Hero Message

> Build freely. Stay beautifully consistent.

### Supporting Description

> DesignStudio Flow gives you a carefully crafted library of responsive WordPress blocks, powerful page controls, and flexible editing without the chaos of an unrestricted blank canvas.

---

## Audience

### Agencies

Agencies need clients to update content without damaging the design system. Emphasize reusable layouts, controlled options, frontend editing, and maintainability.

### Designers

Designers need visual consistency across an entire site. Emphasize shared typography, color, spacing, purposeful blocks, and high-quality defaults.

### Developers

Developers need predictable architecture and secure data handling. Emphasize structured block contracts, shared editor/frontend rendering, WordPress integration, testing, and security gates.

### Marketing and Content Teams

Content teams need to publish campaigns without navigating complex layout tools. Emphasize inline editing, campaign blocks, forms, popups, countdowns, and responsive previews.

### WooCommerce Site Owners

Store owners need curated product experiences. Emphasize category-aware product grids, filters, search, manual ordering, featured products, brands, and add-to-cart support.

---

## Brand Personality

The brand should feel:

- Crafted
- Confident
- Intelligent
- Calm
- Architectural
- Editorial
- Practical
- Trustworthy
- Modern without being trendy

The brand should not feel:

- Corporate and cold
- Playful to the point of being unserious
- Overly technical
- Template-driven
- Loud or visually chaotic
- Like a generic venture-backed SaaS dashboard

### Voice

Use concise, confident language. Explain benefits in plain English. Technical content should remain understandable to non-developers.

Prefer:

> Your editors can change the content without redesigning the website by accident.

Avoid:

> Leverage our revolutionary next-generation platform to unlock synergistic digital experiences.

---

## Visual Concept

Treat the page as a digital design studio where finished website blocks are the materials.

Combine:

- Editorial typography
- Architectural grids
- Warm paper-like surfaces
- Precise technical labels
- Browser and editor frames
- Layered block previews
- Restrained motion
- Purposeful asymmetry

The blocks are the primary visual identity. Whenever possible, demonstrate a real block rather than representing a feature with an abstract icon.

### Recurring Visual Motifs

- Fine grid lines
- Section index numbers
- Small uppercase labels
- Block outlines and drag handles
- Browser chrome
- Responsive viewport frames
- Editor side panels
- Stacked page sections
- Subtle blueprint or paper grain
- Small technical annotations

---

## Color System

Define all colors as reusable tokens.

```css
:root {
  --color-teal-900: #193F3E;
  --color-teal-700: #2C5F5D;
  --color-teal-500: #4F7F7C;
  --color-ink: #111827;
  --color-ink-soft: #374151;
  --color-slate: #5F6978;
  --color-ivory: #F7F4ED;
  --color-paper: #FCFBF7;
  --color-white: #FFFFFF;
  --color-stone-100: #EFEEE9;
  --color-stone-200: #E2E0D8;
  --color-sage: #A7B9A5;
  --color-coral: #E86A45;
  --color-coral-dark: #C94F30;
  --color-success: #38785D;
  --color-focus: #E86A45;
}
```

### Color Roles

- Use deep teal for brand authority, major CTAs, and important section backgrounds.
- Use ink for primary text and high-contrast dark sections.
- Use warm ivory as the dominant page background.
- Use white for editor surfaces, cards, and focused content areas.
- Use stone for borders and dividers.
- Use coral sparingly for active states, highlights, and conversion emphasis.
- Use sage for quiet supporting graphics and diagrams.

### Color Rules

- Maintain WCAG AA contrast for text and controls.
- Do not place muted gray text on colored backgrounds without checking contrast.
- Coral is an accent, not the dominant brand color.
- Avoid gradients unless they represent an image overlay or subtle depth.
- Never introduce purple as a primary or secondary color.

### Runtime Theme Tokens (implemented system)

Live landing blocks read these CSS custom properties so per-block and per-page overrides cascade over the theme. Set them on the block/page wrapper; each falls back to the theme default when unset.

```css
/* Theme */
--dsf-theme-primary    /* accent / "blue" */
--dsf-theme-secondary  /* secondary / "coral" */
--dsf-theme-text
--dsf-theme-background
--dsf-theme-heading-font
--dsf-theme-body-font
--dsf-theme-container-width
--dsf-theme-h1 / --dsf-theme-h2 / --dsf-theme-h3 / --dsf-theme-h4 / --dsf-theme-p-size

/* Eyebrow label */
--dsf-eyebrow-size        /* default 14px */
--dsf-eyebrow-color       /* text */
--dsf-eyebrow-line-color  /* accent mark (line/dot/tick) */

/* Buttons */
--dsf-button-bg
--dsf-button-text
```

---

## Typography

Use an expressive editorial or geometric sans-serif for headings and a highly readable humanist sans-serif for body text.

Suggested pairings include:

- Heading: Manrope, Sora, General Sans, or a similar confident display sans
- Body: Source Sans 3, Public Sans, or a similar readable humanist sans

Do not default to Inter, Roboto, Arial, or the system font stack unless required by the implementation environment.

### Type Scale

```css
:root {
  --font-display: "Manrope", sans-serif;
  --font-body: "Source Sans 3", sans-serif;
  --text-xs: 0.75rem;
  --text-sm: 0.875rem;
  --text-base: 1rem;
  --text-lg: 1.25rem;
  --text-xl: 1.5rem;
  --text-2xl: clamp(1.75rem, 3vw, 2.5rem);
  --text-3xl: clamp(2.4rem, 5vw, 4.75rem);
  --text-hero: clamp(3rem, 7vw, 6.75rem);
}
```

### Typography Rules

- Use sentence case for headlines.
- Keep body copy between 55 and 75 characters per line.
- Use uppercase only for short eyebrow labels, and keep **all eyebrows at one consistent size** (see Eyebrow Label).
- Keep button text at normal paragraph size.
- Use tight heading line-height between 0.95 and 1.12.
- Use body line-height between 1.5 and 1.7.
- Avoid enormous headlines that force supporting content below the fold.

### Configurable Typography (Settings → Content Sizing)

Base sizes and the main container width are global settings that flow to both the editor canvas and the published page:

- **Base font sizes** for `p`, `h1`, `h2`, `h3`, `h4`
- **Container max width** for the main page content (per-page override available in the Theme panel)
- Heading and body font families plus a modular type scale (Settings → Typography)

Leave a size blank to use the automatic value from the scale.

---

## Spacing and Layout

Use an 8-pixel spacing foundation with a few smaller values for fine alignment.

```css
:root {
  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-5: 24px;
  --space-6: 32px;
  --space-7: 48px;
  --space-8: 64px;
  --space-9: 96px;
  --space-10: 128px;
  --container-wide: 1440px;
  --container-content: 1180px;
  --container-reading: 760px;
}
```

### Grid

- Desktop: 12 columns
- Tablet: 8 columns
- Mobile: 4 columns
- Desktop gutters: 24 to 32 pixels
- Mobile gutters: 16 pixels
- Maximum content width: 1180 pixels
- Maximum visual width: 1440 pixels

### Section Spacing

- Large desktop section padding: 96 to 128 pixels
- Standard desktop section padding: 72 to 96 pixels
- Mobile section padding: 56 to 72 pixels
- Use less spacing inside technical demonstrations than editorial sections.
- Keep related controls visually close.

### Shape

```css
:root {
  --radius-sm: 6px;
  --radius-md: 12px;
  --radius-lg: 22px;
  --radius-pill: 999px;
  --border-subtle: 1px solid #E2E0D8;
}
```

Use square or modestly rounded editor surfaces. Reserve large rounding for major panels and promotional imagery. Do not make every element a rounded card.

---

## Buttons and Links

> Every button exposes an **independent background color and text color** (`--dsf-button-bg` and `--dsf-button-text`), so the label color is never tied to the button background, body text, or accent. Always set both for adequate contrast.

### Primary Button

- Deep teal or coral background
- High-contrast white text
- Minimum height: 48 pixels
- Horizontal padding: 22 to 28 pixels
- Medium or bold body font
- Modest 6 to 10 pixel radius

### Secondary Button

- Transparent or white background
- Ink or deep teal text
- Visible one-pixel border
- Same height and type size as primary button

### Text Link

- Use an arrow or underline treatment
- Underline must not depend on hover alone
- Use clear focus styles

### CTA Copy

Primary:

> Get DesignStudio Flow

Secondary:

> Explore the Block Library

Alternative secondary:

> View the Demo

Do not use vague CTAs such as Learn More when a specific action is available.

---

## Component System

### Announcement Bar

- Height: approximately 36 to 42 pixels
- Deep teal background
- Warm white text
- One short message and optional text link
- Hide nonessential copy on small mobile screens

Suggested copy:

> DesignStudio Flow for WordPress - Build better pages without blank-canvas chaos.

### Main Header

- Sticky after the initial scroll
- Warm ivory or white background
- Thin stone border
- Logo on the left
- Navigation in the center or right
- Secondary and primary actions on the far right
- Mobile uses an accessible slide-out drawer

Navigation:

- Why DSFlow
- Blocks
- WooCommerce
- For Agencies
- Security
- Documentation

### Eyebrow Label

- Uppercase
- **14 pixels, standardized across every block** for a single consistent eyebrow size (use the `--dsf-eyebrow-size` token; default `14px`)
- Letter spacing `0.13em`, weight 850
- **Separate colors for the text and the accent mark (line/dot/tick)** — the text uses `--dsf-eyebrow-color` (default deep teal/accent) and the mark uses `--dsf-eyebrow-line-color` (default coral/secondary). They are independent so the line can be one color and the text another.
- May include a small line, dot, or tick mark; may include a section number

### Feature Card

- Use cards only when content is genuinely grouped.
- Prefer thin borders over heavy shadows.
- Include a small label, concise title, description, and optional product preview.
- Hover may lift by 2 to 4 pixels.

### Browser Frame

- Neutral window chrome
- Small traffic-light or tab indicators only when useful
- Realistic page proportions
- Avoid making the frame visually louder than its content

### Editor Mockup

Include:

- DSFlow top toolbar
- Block canvas
- Settings sidebar
- Content and Style tabs
- Responsive preview buttons
- Drag handles
- Inline text cursor
- Add Block control

### Block Preview Card

- Show a miniature real section
- Label the block category and name
- Use consistent preview ratios
- Allow hover expansion or reveal of available controls
- Do not reduce blocks to generic icons

### Technical Diagram

- Use simple connected steps
- Prefer labels and arrows over decorative illustrations
- Keep text readable on mobile
- Use teal for the normal path and coral for key security boundaries

### Footer

- Dark ink or deep teal background
- Clear brand statement
- Four logical link columns at desktop
- Accordion columns on mobile if necessary
- Include copyright, privacy, terms, and version label

---

## Motion System

Motion should explain hierarchy and product behavior.

### Allowed Motion

- Staggered section reveals
- Blocks snapping into a page stack
- A cursor changing inline text
- A settings control updating a preview
- A mega menu opening
- Expander cards growing on hover
- Viewport frame resizing between desktop, tablet, and mobile

### Timing

- Hover: 150 to 220 milliseconds
- Panel transitions: 220 to 320 milliseconds
- Page reveals: 400 to 650 milliseconds
- Use natural ease-out curves

### Motion Rules

- Avoid continuous floating animation.
- Do not animate every decorative element.
- Never make content dependent on hover.
- Respect `prefers-reduced-motion`.
- Keep layout movement predictable and avoid cumulative layout shift.

---

## Responsive System

### Desktop

- Use strong two-column compositions.
- Let editor and block previews occupy significant visual space.
- Use asymmetry without breaking the grid.

### Tablet

- Reduce navigation density.
- Preserve two columns where content remains readable.
- Stack dense technical sections.
- Keep touch targets at least 44 pixels.

### Mobile

- Stack hero copy above the product preview.
- Use a right-side navigation drawer.
- Make primary CTAs full width where helpful.
- Convert feature comparisons into vertical sections.
- Use horizontal preview scrolling only when it improves comprehension.
- Replace hover-only demonstrations with tap or automatic preview states.
- Keep all body copy at 16 pixels or larger.
- Preserve generous horizontal padding and avoid edge-to-edge text.

Suggested breakpoints:

```css
--breakpoint-mobile: 640px;
--breakpoint-tablet: 900px;
--breakpoint-desktop: 1200px;
```

---

## Accessibility Requirements

- Meet WCAG 2.2 AA contrast requirements.
- Use semantic landmarks: header, nav, main, section, and footer.
- Maintain one primary H1.
- Follow a logical heading hierarchy.
- Provide visible keyboard focus states.
- Ensure all menus, dialogs, drawers, and accordions work with a keyboard.
- Add meaningful alternative text to informative images.
- Treat decorative images as decorative.
- Do not communicate meaning through color alone.
- Use accessible names for icon-only controls.
- Provide close buttons for menus and dialogs.
- Prevent background scrolling when modal interfaces are open.
- Respect reduced motion.

---

## Landing Page Architecture

Build the page in this order.

### 1. Announcement Bar

Message:

> DesignStudio Flow for WordPress - Build better pages without blank-canvas chaos.

Optional action:

> See what is new

### 2. Header

Use the navigation and CTAs defined in the component system.

### 3. Hero

Eyebrow:

> A CURATED WORDPRESS PAGE BUILDER

H1:

> Build freely. Stay beautifully consistent.

Body:

> DesignStudio Flow gives you a carefully crafted library of responsive WordPress blocks, powerful page controls, and flexible editing without the chaos of an unrestricted blank canvas.

Primary CTA:

> Get DesignStudio Flow

Secondary CTA:

> Explore the Block Library

Supporting note:

> Built for real WordPress pages. WooCommerce ready. Designed with security in mind.

Hero visual: a large DSFlow editor mockup with several real blocks stacked on the canvas.

### 4. Philosophy Statement

Headline:

> Most page builders give you every possible option. DSFlow gives you the right ones.

Support this with three principles:

- Curated, not restrictive
- Consistent by default
- Difficult to break

### 5. Interactive Block Library

Eyebrow:

> THE BUILDING BLOCKS

Headline:

> Everything you need to tell the whole story.

Filters:

- Heroes
- Content
- Marketing
- Ecommerce
- Forms
- Headers and Footers

Include previews for Hero, Bento Hero, Spotlight Hero, Duo Hero, Expander Hero, Content, FAQ, Text and Image, Features Grid, Testimonials, Countdown, Pricing, Product Grid, Featured Promo Banner, CTA Banner, Forms, Mega Menu Headers, and Footers.

### 6. Editor Experience

Use a dark background and large editor demonstration.

Headline:

> Edit the page where the page actually lives.

Feature callouts:

- Inline editing
- Drag-and-drop ordering
- Media Library support
- Content and Style panels
- Responsive previews
- WordPress page settings
- Frontend Edit with DSFlow access
- Link and modal CTA actions

### 7. Theme Consistency

Headline:

> One visual language across every block.

Show typography, color, spacing, and button controls updating several different blocks together.

Key statement:

> Your editors can change the content without redesigning the website by accident.

### 8. WooCommerce

Eyebrow:

> BUILT FOR COMMERCE

Headline:

> Turn your product catalog into a flexible landing-page system.

Demonstrate product categories, child-category inclusion, filters, search, tags, manual product ordering, brand displays, featured product banners, and add-to-cart behavior.

### 9. Headers and Footers

Headline:

> The page does not stop at the first or last block.

Show announcement bars, utility navigation, mega menus, location panels, mobile navigation, promotional cards, and a structured footer.

### 10. Popups and Campaign Tools

Headline:

> Launch campaigns without leaving the page builder.

Show a page-specific popup with delay, active dates, dismissal-cookie duration, width, rich text, full-image mode, CTA, and modal or drawer layouts.

### 11. SEO Foundations

Headline:

> Interactive for visitors. Readable for search engines.

Diagram:

> Editor -> HTML Snapshot -> WordPress Page -> Search Engine -> Interactive Frontend

Explain saved HTML snapshots without promising rankings.

### 12. Security

Eyebrow:

> SECURITY IS PART OF THE BLOCK

Headline:

> Creative tools should not create new attack surfaces.

Explain validation, sanitization, escaping, nonces, capability checks, safe URLs, secure AJAX, and documented security gates.

### 13. Audience Benefits

Headline:

> Made for the people who build the system and the people who maintain it.

Create concise benefits for designers, developers, agencies, and content teams.

### 14. Four-Step Workflow

1. Add a block
2. Add your content
3. Customize the design
4. Preview and publish

Closing line:

> From empty page to polished campaign without rebuilding the design system every time.

### 15. Final CTA

Eyebrow:

> YOUR NEXT PAGE STARTS HERE

Headline:

> Build pages that feel designed, not assembled.

Body:

> Bring structure, visual consistency, ecommerce tools, campaign features, and secure WordPress editing together in one focused workflow.

Actions:

- Get DesignStudio Flow
- View the Demo

### 16. Footer

Brand statement:

> Artisanal content blocks for better WordPress pages.

Columns:

- Product
- Resources
- Support
- Company

Bottom line:

> Built for WordPress with clarity, control, and care.

---

## Content Rules

- Do not invent pricing.
- Do not invent customer counts or performance percentages.
- Do not use fake testimonials.
- Do not display company logos without permission.
- Do not promise guaranteed SEO rankings.
- Do not claim formal security compliance or certification unless supplied.
- Keep paragraphs concise and scannable.
- Lead with user outcomes, then support them with technical details.
- Use DesignStudio Flow on first mention and DSFlow afterward.
- Refer to WordPress and WooCommerce by their correct names.

---

## Design Do and Do Not List

### Do

- Make real blocks the visual centerpiece.
- Use warm editorial backgrounds.
- Use a clear grid and deliberate spacing.
- Demonstrate editor interactions realistically.
- Balance product benefits with technical credibility.
- Use dark sections to create pacing.
- Keep conversion actions visible but not aggressive.
- Create a polished mobile experience.

### Do Not

- Use purple gradient backgrounds.
- Use glass cards as the main design language.
- Put every feature inside an identical rounded card.
- Add decorative 3D objects unrelated to WordPress or page building.
- Use excessive shadows or neon glows.
- Create fake dashboards or metrics.
- Depend on hover to communicate important content.
- Make the entire site dark mode.
- Copy the visual identity of Elementor, Webflow, or another builder.

---

## Expected Claude Output

When using this system to create the design, provide:

1. A complete desktop landing-page design
2. Tablet and mobile responsive behavior
3. A reusable token system
4. Header, navigation, button, card, editor-frame, diagram, CTA, and footer components
5. All page sections in the required order
6. Accessible interaction states
7. Purposeful motion specifications
8. Real DSFlow product messaging from this document

The finished design should leave the visitor with one clear impression:

> DesignStudio Flow is a professional design system disguised as an easy WordPress page builder.
