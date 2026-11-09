# The Miner Island — Custom OpenCart 3.x Catalog Theme

**Theme directory name:** `minerisland`
**Target install:** `theminerisland.com`
**OpenCart version:** 3.0.3.7
**PHP version:** 7.4
**Build phase:** Phase 1 — Global stylesheet foundation

---

## What is in this folder

A complete OpenCart 3.x catalog theme skeleton with the global stylesheet
already written. All other folders are empty placeholders that will be
filled in subsequent phases (header, footer, homepage, product page,
category page, cart, account pages, etc).

```
minerisland/
├── README.md                       ← this file (do not upload)
├── stylesheet/
│   └── stylesheet.css              ← Phase 1 — DONE (2,178 lines)
├── javascript/                     ← Phase 8 — empty
├── image/                          ← Logo + favicons go here later
└── template/
    ├── common/                     ← Phase 2: header.twig, footer.twig, menu.twig
    ├── product/                    ← Phase 4-5: product.twig, category.twig
    ├── information/                ← Phase 7: information.twig, contact.twig
    ├── account/                    ← Phase 6: login.twig, register.twig, etc
    ├── checkout/                   ← Phase 6: cart.twig, checkout.twig
    ├── extension/module/           ← Phase 3: featured.twig, slideshow.twig
    ├── error/                      ← Phase 6: not_found.twig
    └── mail/                       ← Phase 7: email templates
```

---

## Deployment instructions for Phase 1

### Step 1 — Upload the theme folder
Via cPanel File Manager or FTP, upload the entire `minerisland/` folder
(but **not** this README.md) to:

```
public_html/catalog/view/theme/minerisland/
```

The end result on the server should look like:
```
public_html/catalog/view/theme/minerisland/stylesheet/stylesheet.css
public_html/catalog/view/theme/minerisland/template/common/  (empty)
public_html/catalog/view/theme/minerisland/javascript/        (empty)
public_html/catalog/view/theme/minerisland/image/             (empty)
... etc
```

### Step 2 — Make the theme selectable in admin
1. Log in to OpenCart admin
2. Go to **System → Settings**
3. Click **Edit** on your store
4. Open the **Store** tab
5. **Theme dropdown** → select `minerisland`
6. Click **Save** (top right)

The theme is now active. **However, the site will look unchanged at this
point** — that is expected. We have not yet uploaded the templates that
load the stylesheet. The stylesheet is on the server but no `.twig` file
is referencing it yet.

### Step 3 — Verify the stylesheet is reachable
Open this URL directly in your browser (replace with your real domain):
```
https://theminerisland.com/catalog/view/theme/minerisland/stylesheet/stylesheet.css
```
You should see the raw CSS load. If you get a 404, the upload path is wrong.
If you get a 403, file permissions need to be `644`.

---

## What this stylesheet covers (Phase 1 scope)

A complete design system foundation, ready for every page we build next:

- Font imports (Manrope + JetBrains Mono via Google Fonts)
- CSS reset (modern, minimal)
- 100+ design tokens (colors, typography scale, spacing, radius, shadows, motion)
- Base typography (h1–h6, paragraphs, links, mono spans)
- Layout primitives (container, grid, stack, cluster, sections, dividers)
- OpenCart Bootstrap 3 grid overrides (tighter gutters)
- Promo bar, top utility bar, header, sticky-header, search, navigation
- Buttons (primary, orange/cart, secondary, ghost, whatsapp, danger; xs-xl sizes)
- Forms (input, textarea, select, custom checkbox/radio, validation states)
- Cards (generic + product cards with white image container, spec strip,
  ROI chip, sale tag, hover lift)
- Price display (large, with old-price strikethrough, savings badge)
- Badges, alerts, tables, breadcrumbs, pagination, tabs
- Footer (dark, multi-column)
- WhatsApp floating button (with pulsing halo + label tooltip)
- Cookie consent bar (slide-up)
- Sticky mobile add-to-cart bar (auto-hides on desktop)
- Hero section (with video frame slot)
- Section headers, crypto ticker (auto-scroll), USP bar, stats, testimonials,
  trust badges
- Category page header, filter sidebar, sort bar, grid/list toggle
- Animations (fade-in, fade-up, pop, spin, shimmer skeleton)
- 60+ utility classes (display, flex, gap, alignment, margins, sr-only)
- Responsive breakpoints (mobile-first: 480 / 640 / 768 / 1024 / 1280)
- Reduced-motion respect (accessibility)
- Print styles

Every reusable component you will see on the site is in here. The next
phase (header and footer twig templates) will reference these classes —
no new CSS needed for those.

---

## What is next — Phase 2

Header, footer, and main navigation:
- `template/common/header.twig`        ← branded header with logo, search, cart
- `template/common/footer.twig`        ← dark footer with WhatsApp, contact, links
- `template/common/menu.twig`          ← triple mega-menu (Brand × Coin × Algorithm)
- `template/common/whatsapp.twig`      ← floating button partial (included in footer)

These three files are what causes the stylesheet to actually load on every
page. Until they are deployed, the live site will keep using OpenCart's
default theme appearance even though `minerisland` is the active theme.

---

## Notes for future-me

- All custom classes are prefixed `.mi-*` (Miner Island) to never collide
  with Bootstrap or OpenCart core classes.
- Stock OpenCart classes (`.product-thumb`, `.btn`, `.form-control`,
  `.breadcrumb`, `.pagination`, `.alert`, `.table`) are also styled so
  the site looks correct even on pages that have not yet been customized.
- The stylesheet is mobile-first. Base styles are mobile; min-width
  media queries enhance for tablet and desktop.
- 16px base font size on all form inputs prevents iOS Safari from
  zooming when the user taps into a field.
- Tap targets are enforced at 44 × 44 px minimum (Apple HIG).
- `prefers-reduced-motion` is respected — animations get disabled.
- All slugs in this theme stay lowercase, with hyphens, no uppercase
  characters anywhere.
