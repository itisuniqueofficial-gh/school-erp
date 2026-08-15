# Accessibility Guidelines — School ERP

Target: **WCAG 2.2 AA**. Improvements are made in the existing Tailwind UI (no
separate accessible version, no Bootstrap). Prefer **native semantics → correct
component behavior → ARIA only when required**. Never use ARIA to mask broken UI.

## Global patterns (already in place)
- **Skip link** — `layouts/app.php` renders `<a class="skip-link" href="#main-content">`, visible on focus; `<main id="main-content" tabindex="-1">` is the focus target.
- **Landmarks** — `<aside aria-label="Sidebar">`, `<nav aria-label="Main navigation">`, `<header>`, `<main>`, `<footer>`, breadcrumb `<nav aria-label="Breadcrumb">`.
- **`<html lang>`** is set from config.
- **Focus** — global `:focus-visible` ring in `app.css`; never remove outlines without an equal replacement.
- **Active nav** — the sidebar `$link()` helper adds `aria-current="page"` on the active item.
- **Mobile sidebar toggle** — `aria-controls="app-sidebar"`; `aria-expanded` is kept in sync by `app.js`.
- **Flash / live regions** — `partials/flash.php`: success/info use `role="status" aria-live="polite"`, error/warning use `role="alert" aria-live="assertive"`.
- **Confirmations** — destructive actions use `data-confirm` → SweetAlert2 (keyboard-operable, focus-trapped) with a native `confirm()` fallback.

## Authoring rules
- **Icons**: decorative Font Awesome icons get `aria-hidden="true"`. An **icon-only** button/link MUST have an accessible name via `aria-label` (or `title`). Example: `<button aria-label="Delete student"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>`.
- **Forms**: every control needs a `<label for>` (or `aria-label`); associate help/errors with `aria-describedby`; use `aria-invalid="true"` on invalid fields; never communicate state with color alone (add text/icon). Use correct `type` and `autocomplete`.
- **Buttons vs links**: buttons perform actions, links navigate — never a clickable `<div>`/`<span>`.
- **Tables**: use `<thead>/<tbody>/<th>`; add `scope` where headers are ambiguous; give action buttons meaningful labels; provide understandable empty states.
- **Images**: meaningful images need real `alt`; decorative images use `alt=""`. Never use a filename as alt text.
- **Charts (Chart.js)**: never the *only* representation — pair with the underlying numbers/summary text (dashboard KPI cards already show the figures).

## Automated guard
`tests/integration_accessibility.php` fails the build if any view reintroduces:
- an icon-only `<button>`/`<a>` without an accessible name, or
- an `<img>` without an `alt` attribute,
and asserts the skip link, focusable `<main>`, and `<html lang>` are present. Run with the suite under `APP_TESTING=1`.

## What automation does NOT cover (needs a human/browser pass)
Keyboard-only walkthroughs, screen-reader output quality, colour-contrast ratios, 200% zoom reflow, focus-trap behavior in every dynamic widget, and responsive layout at each breakpoint. Use a browser + axe DevTools for these.
