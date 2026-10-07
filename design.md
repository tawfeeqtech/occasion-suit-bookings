# SuitRent SaaS Design System

**Version:** 1.0 (MVP baseline)  
**Status:** Design specification  
**Product:** Internal rental operations dashboard for premium wedding suits and formalwear  
**Audience:** Shop Owners, Staff, fitting and tailoring coordinators, branch managers where branch support is explicitly modeled, and platform System Admins  
**Application stack:** Laravel 13, Filament, Tailwind CSS 4, PostgreSQL  
**Default interface locale:** Arabic (`ar`) with right-to-left (`rtl`) layout

This document is the normative visual and interaction reference for the SuitRent web dashboard. It covers the internal staff and administrative application, not a customer-facing booking site. The Telegram bot and its messages are separate surfaces; they may reuse status terminology and color semantics but are not styled by this document.

## 1. Product Experience

SuitRent should feel like the operations room of a premium tailoring house: dark textile surfaces, restrained satin-gold accents, precise tables, and calm transaction feedback. Luxury comes from material contrast, typography, spacing, and disciplined detail, not ornamental frames or decorative effects.

The interface must optimize repeated work: fast inventory entry, accurate date and amount entry, clear availability, safe return inspection, and immediate visibility of collateral state. Primary actions and current operational status must remain easy to scan during busy shop hours.

### Design principles

1. **Premium, never theatrical:** use gold as a focused accent; do not turn every heading, border, or icon gold.
2. **Operational clarity:** status, dates, amount due, and the next safe action are more prominent than decoration.
3. **One shop context at a time:** tenant data must never be blended in a shared list or dashboard.
4. **Arabic-first:** Arabic is the canonical UI language; numbers, identifiers, and Latin barcodes use explicitly isolated LTR runs.
5. **Safe transactions:** destructive, financial, collateral, and booking actions show a clear consequence and confirmation state.

## 2. Color Tokens

All values below are normative HEX values. Use tokens instead of one-off color literals in application components. `Midnight Tuxedo Black` and `Royal Navy Slate` are supplied brand anchors; related values below complete the working palette.

### Core palette

| Token | HEX | Use |
|---|---|---|
| `color.canvas` | `#090D16` | Main page background |
| `color.shell` | `#0B1220` | Navigation shell and fixed application chrome |
| `color.surface-1` | `#0F172A` | Primary panels, cards, modal surfaces |
| `color.surface-2` | `#111C31` | Nested surfaces, inputs, table header |
| `color.surface-3` | `#172033` | Hovered rows, selected list items, raised controls |
| `color.surface-inset` | `#080B12` | Inset numeric inputs and dense data regions |
| `color.border-subtle` | `#1E293B` | Dividers and quiet card boundaries |
| `color.border-default` | `#334155` | Input borders and standard component outlines |
| `color.border-strong` | `#475569` | High-emphasis outlines and table separators |
| `color.text-primary` | `#F8FAFC` | Main text and headings |
| `color.text-secondary` | `#CBD5E1` | Labels and supporting text |
| `color.text-muted` | `#94A3B8` | Hints, timestamps, secondary metadata |
| `color.text-disabled` | `#64748B` | Disabled labels; never use for essential information |
| `color.gold` | `#F59E0B` | Primary action, selected navigation, key active state |
| `color.gold-hover` | `#D97706` | Gold action hover and emphasized pressed state |
| `color.gold-pressed` | `#B45309` | Gold action pressed state |
| `color.gold-soft` | `#422006` | Gold status background |
| `color.gold-text` | `#FDE68A` | Text/icon on gold-soft background |
| `color.overlay` | `#030712` | Modal and drawer scrim base |

### Status palette

Status background, text, and border are paired tokens. Never use the saturated accent alone as small text on a dark background. Always show a readable status label; color is supplementary.

| Semantic state | Accent HEX | Badge background | Badge text | Border | UI label |
|---|---|---|---|---|---|
| Available / clean pass | `#10B981` | `#052E2B` | `#6EE7B7` | `#065F46` | متاح / سليم |
| Booked | `#60A5FA` | `#172554` | `#BFDBFE` | `#1D4ED8` | محجوز |
| Cleaning / turnaround buffer | `#F59E0B` | `#422006` | `#FDE68A` | `#92400E` | قيد التنظيف |
| Maintenance | `#FBBF24` | `#3B2F0B` | `#FDE68A` | `#854D0E` | قيد الصيانة |
| Damaged / penalty pending | `#EF4444` | `#450A0A` | `#FCA5A5` | `#991B1B` | تالف / غرامة معلقة |
| Missing item | `#DC2626` | `#450A0A` | `#FCA5A5` | `#991B1B` | مفقود |
| National ID held | `#A855F7` | `#2E1065` | `#E9D5FF` | `#7E22CE` | الهوية محتجزة |
| National ID released | `#10B981` | `#052E2B` | `#6EE7B7` | `#065F46` | الهوية مُسلّمة |
| Retired / inactive | `#64748B` | `#1E293B` | `#CBD5E1` | `#475569` | خارج الخدمة |
| Neutral / draft | `#94A3B8` | `#1E293B` | `#CBD5E1` | `#334155` | مسودة |

### Semantic usage rules

- Gold means primary action, selected state, or attention requiring no destructive interpretation. It does **not** mean success.
- Emerald means available, completed, or settled. A green color must not imply an unverified payment.
- Red means damage, missing property, validation failure, overdue risk, or destructive action.
- Purple is reserved for the National ID collateral lifecycle. Do not use it as the application-wide brand accent.
- Blue means booked or informational state only; do not introduce a second primary brand color.
- A state transition must update its label and icon as well as its color.

## 3. Typography

### Font families

| Token | Font stack | Use |
|---|---|---|
| `font.arabic` | `"IBM Plex Sans Arabic", "Cairo", sans-serif` | Arabic UI, headings, forms, tables, navigation |
| `font.latin` | `Inter, "IBM Plex Sans Arabic", sans-serif` | Latin text, mixed-language labels, English identifiers |
| `font.mono` | `"JetBrains Mono", "SFMono-Regular", Consolas, monospace` | Barcode values, UUID fragments, booking references, technical IDs |

Load IBM Plex Sans Arabic as the primary family. Cairo is a fallback only. Inter is used for Latin numerals when loaded; all numeric text must remain legible if it falls back to the Arabic family. Do not use a display serif for operational data.

### Type scale

Sizes are fixed pixels at all viewport widths; do not use viewport-scaled font sizes. Line height is a unitless multiplier. Arabic headings use the same sizes and weights, with enough line height to avoid clipping diacritics.

| Level | Size | Weight | Line height | Typical use |
|---|---:|---:|---:|---|
| H1 | 30px | 600 | 1.35 | Page title; one per view |
| H2 | 24px | 600 | 1.4 | Major page section |
| H3 | 20px | 600 | 1.45 | Panel or form section heading |
| H4 | 18px | 600 | 1.5 | Card or table group title |
| Body / Large | 16px | 400 | 1.65 | Important explanatory copy and mobile body |
| Body | 14px | 400 | 1.6 | Default table and form content |
| Label | 13px | 500 | 1.5 | Form labels, tabs, compact metadata |
| Caption | 12px | 400 | 1.5 | Timestamps and helper text; never sole critical signal |

Use weights 400, 500, and 600 only. Use 700 only for a high-priority total or a short critical alert. Keep long Arabic body lines between 45 and 75 characters where layout permits. Numeric values use tabular figures (`font-variant-numeric: tabular-nums`).

## 4. Layout and Navigation

### Desktop shell

| Element | Dimension | Behavior |
|---|---:|---|
| Top bar | 64px high | Sticky at viewport top; contains page context, shop context, and account actions |
| Expanded sidebar | 264px wide | Fixed to the right in RTL; full labels and icons |
| Collapsed sidebar | 72px wide | Icon-only; provide accessible names and hover/focus tooltips |
| Main content | `minmax(0, 1fr)` | Scrolls independently of the fixed navigation shell |
| Main content padding | 32px | Reduce to 24px below 1280px viewport width |
| Page content max width | 1600px | Center wider content; dense tables may use the full available width |

### Breakpoints and mobile behavior

| Breakpoint | Layout rule |
|---|---|
| `< 640px` | Mobile: 56px top bar, 16px content padding, sidebar becomes a right-side drawer |
| `640px–767px` | Compact: 56px top bar, 20px content padding, drawer navigation |
| `768px–1023px` | Tablet: 60px top bar, collapsible 72px sidebar, 24px content padding |
| `>= 1024px` | Desktop shell as specified above |

The mobile navigation drawer is 304px wide, capped at `calc(100vw - 48px)`. It closes on Escape, backdrop click, route selection, or explicit close. Touch targets are at least 44×44px. Keep the page title and primary action visible without horizontal scrolling.

### Navigation and tenant context

- Sidebar order: اليوم، الحجوزات، المخزون، الإرجاع والفحص، التنظيف والصيانة، التقارير المالية، سجل التدقيق، الموظفون، إعدادات المحل. Hide entries the current role cannot access; server-side authorization remains mandatory.
- Active item: `color.surface-3` background, 3px gold logical-start indicator, `color.gold` icon, `color.text-primary` label, and `aria-current="page"`.
- Top bar shop-context control: minimum 220px, maximum 320px; show the current shop name and a chevron only when the account can legitimately change context.
- Tenant users belong to one shop in the current architecture. For Shop Owner and Staff, render the shop name as a non-switching context label; never offer access to another tenant.
- System Admin may choose a tenant in the central platform console, but must enter one tenant context explicitly. Never combine records from several tenants in operational lists or reports.
- The current data model defines a **Tenant/shop**, not branches within a shop. Do not label the MVP selector as a branch selector or show branch controls. Add branch selection only after a `Branch` entity, access rules, and `branch_id` filtering are approved and implemented.

## 5. Buttons and Actions

### Sizes

| Variant | Height | Horizontal padding | Text | Icon |
|---|---:|---:|---:|---:|
| Compact | 32px | 10px | 12px / 500 | 14px |
| Default | 40px | 16px | 14px / 500 | 16px |
| Large primary | 48px | 20px | 14px / 600 | 18px |

On touch layouts, every actionable target has a minimum 44×44px hit area, even if the visible compact control is smaller. Button radius is 6px. Icon-label gap is 8px. Use icon-only buttons only for familiar actions and provide an Arabic tooltip and accessible name.

### Variants and states

| Variant | Default | Hover | Pressed | Disabled |
|---|---|---|---|---|
| Primary Gold | `#F59E0B`, text `#090D16` | `#D97706`, text `#090D16` | `#B45309`, text `#FFF7ED` | `#422006`, text `#94A3B8`, no shadow |
| Secondary Slate | `#1E293B`, text `#F8FAFC`, border `#334155` | `#29364A` | `#334155` | `#172033`, text `#64748B` |
| Destructive Red | `#DC2626`, text `#FFF7F7` | `#B91C1C`, text `#FFF7F7` | `#991B1B`, text `#FFF7F7` | `#450A0A`, text `#94A3B8` |
| Success Emerald | `#059669`, text `#04130F` | `#047857`, text `#F0FDF4` | `#065F46`, text `#F0FDF4` | `#052E2B`, text `#94A3B8` |
| Quiet / Ghost | transparent, text `#CBD5E1` | `#172033` | `#1E293B` | transparent, text `#64748B` |

- Hover transition: 160ms, ease-out; pressed state is immediate or 80ms.
- Keyboard focus: 2px `#F59E0B` outline with 2px offset; never remove the browser-visible focus indication.
- Loading state: retain button width, show a 16px spinner, disable duplicate submission, and keep the action label available to assistive technology.
- Destructive actions require a confirmation step with the affected record and consequence. Never use red for routine navigation.

## 6. Modals and Drawers

| Surface | Width | Max height | Use |
|---|---:|---:|---|
| Confirmation modal | 480px | `min(360px, calc(100dvh - 48px))` | Short, reversible confirmation |
| Standard modal | 560px | `calc(100dvh - 64px)` | Single record form or inspection detail |
| Wide modal | 800px | `calc(100dvh - 64px)` | Multi-column booking or return form |
| Desktop side drawer | 480px | `100dvh` | Contextual details and quick edits |

- Modal radius: 12px; drawer radius: 0. Do not nest a card inside another card inside a modal.
- Backdrop: `rgba(3, 7, 18, 0.72)` with `backdrop-filter: blur(6px)`. If blur is unavailable, opacity alone must preserve separation.
- Header: minimum 64px high, title at H3, close button at least 44×44px, bottom border `#1E293B`.
- Body: scrolls internally; desktop padding 24px. Keep title and actions fixed while body scrolls.
- Footer: minimum 72px high, top border `#1E293B`, actions grouped at the visual end in RTL. Primary action appears before the cancel action in the RTL reading order.
- Mobile form modal: full-screen sheet with `100dvh`; mobile confirmation modal: width `calc(100vw - 32px)`, centered, max 420px. Lock background scroll while open.
- Escape closes non-destructive dialogs. Unsaved form data requires a discard confirmation before closing.

## 7. Forms and Inputs

| Control | Height / dimensions | Specification |
|---|---:|---|
| Text, number, phone, select | 44px | Full-width by default; horizontal padding 12px |
| Date input | 44px | Visible local format `DD/MM/YYYY`; persist ISO date/time with the tenant's configured timezone |
| Search field | 40px | Search icon at RTL start; clear control at RTL end |
| Textarea | 112px minimum | Resize vertically; count/helper text below the field |
| Checkbox / radio / switch | 20px visual, 44px hit area | Label is clickable; do not use color alone for selected state |
| Form label | 13px / 500 | Always visible above the input; do not rely on placeholder text |

- Input default: background `#111C31`, text `#F8FAFC`, border `#334155`, placeholder `#94A3B8`, radius 6px.
- Hover: border `#64748B`. Focus: border `#F59E0B` plus a 2px translucent gold ring. Error: border `#EF4444`, ring `rgba(239, 68, 68, 0.24)`, inline error text `#FCA5A5`.
- Disabled fields use background `#0B1220`, text `#64748B`, and an explicit disabled state; do not simulate disabled by lowering opacity of the entire form.
- Show validation immediately beside the relevant field, with a concise Arabic message. Preserve entered values after server validation errors.
- Required fields show a visible marker and accessible required state. Group related fields with H4 and 16px vertical spacing.
- Date ranges must show pickup and return labels independently. Never infer an omitted date from voice/AI data in the web form.
- Amounts use decimal precision 2 and tabular numerals. Currency is the tenant's configured currency, default ILS. Display the shekel sign as `₪` with the amount in an isolated LTR span, e.g. `₪ 1,250.00`; do not allow punctuation or digits to reorder around Arabic text. Do not imply support for mixed currencies.
- National ID handling is limited to collateral status (`held` / `released`) and permitted text notes. Never provide an ID image upload, scanning, or stored ID-number field.

## 8. Tables, Cards, and Operational Data

### Tables

| Part | Height / value |
|---|---:|
| Table header | 44px |
| Standard row | 56px |
| Dense row | 44px; use only when the user chooses compact density |
| Cell horizontal padding | 12px |
| Sticky header | `top: 64px` below the desktop top bar |

- Header surface: `#111C31`, label `#CBD5E1`, weight 500. Body surface: `#0F172A`; alternate row may use `#111827` only if contrast remains clear. Hover: `#172033`.
- Separate rows with 1px `#1E293B`; avoid strong vertical gridlines. Selected rows use a 3px gold logical-start marker and a selected checkbox.
- Keep record name and status in the first visible columns. Place secondary metadata after them. Align Arabic text to the right; align dates, amounts, and IDs consistently by data type.
- Barcode/reference values use JetBrains Mono, 13px, tabular figures, `dir="ltr"`, and `unicode-bidi: isolate`. Provide a copy action with an accessible Arabic label. Never encode customer personal data in the barcode.
- On screens under 768px, transform simple record tables into stacked rows/cards. For genuinely comparative dense tables, allow a labeled horizontal-scroll region; never shrink text below 12px to force fit.
- Empty states state the missing data and offer one relevant action, such as إضافة قطعة or إنشاء حجز, only when authorized.

### Cards and return inspection

- Standard card: `#0F172A`, 1px `#1E293B` border, 8px radius, 20px padding. Compact card padding: 16px.
- Booking summary shows booking reference, customer, date range, item count, outstanding balance, and collateral state. The most consequential pending action is visually dominant.
- Return inspection uses one card per booked item with item name/category/size, barcode/reference, and a mutually exclusive segmented choice: `سليم`, `تالف`, `مفقود`.
- `Clean Pass`: emerald background/text tokens, check icon, and label. `Damaged`: crimson background/text tokens, warning icon, and required amount/reason fields. `Missing`: crimson background/text tokens, missing-item icon, and required amount/reason fields.
- All options must be distinguishable without color (label + icon + selected outline). Do not preselect a condition. Require an explicit inspection result for every booked item before submission.
- Collateral card displays a purple `الهوية محتجزة` or emerald `الهوية مُسلّمة` badge and the release guard reason. Never display or upload an ID-card image.

## 9. Badges and Alerts

### Badges

- Height 24px; horizontal padding 8px; radius 999px; font 12px / 500; icon 12px; gap 4px.
- Use the exact status pairs from the Status Palette table. A small badge always includes text; status colors alone are insufficient.
- Inventory state, booking lifecycle, financial settlement, collateral state, and access role are separate concepts and must not share one ambiguous badge.
- For `Buffer / Turnaround`, show a clock icon and the ready-at timestamp in the item's detail or list secondary text.

### Alerts

| Type | Background | Border | Text/icon |
|---|---|---|---|
| Info | `#172554` | `#1D4ED8` | `#BFDBFE` |
| Success | `#052E2B` | `#065F46` | `#6EE7B7` |
| Warning | `#422006` | `#92400E` | `#FDE68A` |
| Error / destructive | `#450A0A` | `#991B1B` | `#FCA5A5` |

Alerts use a 4px logical-start border, 12px radius, 16px padding, 16px icon, and a concise heading plus explanation. Errors state what failed and how to recover. Do not rely on auto-dismiss for booking conflicts, unpaid balances, rejected collateral release, or destructive outcomes.

## 10. Spacing, Radius, Elevation, and Motion

### Spacing scale

Use only this 4px-based scale unless a fixed component dimension is specified above:

| Token | Value | Typical use |
|---|---:|---|
| `space-1` | 4px | Icon/label micro-gap |
| `space-2` | 8px | Compact component gap |
| `space-3` | 12px | Input and badge internal spacing |
| `space-4` | 16px | Standard component gap |
| `space-5` | 20px | Card padding compact |
| `space-6` | 24px | Form section/card padding |
| `space-8` | 32px | Page section gap |
| `space-10` | 40px | Major section separation |
| `space-12` | 48px | Large page separation |
| `space-16` | 64px | Desktop-only major composition gap |

### Radius scale

| Token | Value | Use |
|---|---:|---|
| `radius-none` | 0px | Full-height drawers and separators |
| `radius-sm` | 4px | Small chips only |
| `radius-control` | 6px | Buttons and form controls |
| `radius-card` | 8px | Cards, panels, tables, and framed sections |
| `radius-dialog` | 12px | Modals only |
| `radius-pill` | 999px | Status badges and compact toggles |

### Elevation shadows

Use subtle shadows to separate layers, not to make every surface appear to float.

| Token | CSS shadow | Use |
|---|---|---|
| `shadow-1` | `0 1px 2px rgba(0, 0, 0, 0.24)` | Small controls |
| `shadow-2` | `0 4px 12px rgba(0, 0, 0, 0.28)` | Popovers and menus |
| `shadow-3` | `0 12px 32px rgba(0, 0, 0, 0.40)` | Modals and drawers |
| `shadow-focus` | `0 0 0 2px rgba(245, 158, 11, 0.32)` | Supplementary focus ring only |

### Motion and stacking

- Hover/focus transition: 160ms. Dialog/drawer transition: 200ms. Maximum routine transition: 240ms.
- Animate opacity and transform only; do not animate layout dimensions or table row height.
- Respect `prefers-reduced-motion: reduce`; remove non-essential transitions and all animated entrance movement.
- Z-index tokens: base 0, sticky table header 10, top bar 20, dropdown 30, modal backdrop 40, modal/drawer 50, toast 60.

## 11. RTL Discipline

- Set the document root to `lang="ar"` and `dir="rtl"`. Filament panels use Arabic locale and RTL direction.
- Use CSS logical properties (`margin-inline-start`, `padding-inline-end`, `border-inline-start`) rather than physical left/right values for layout.
- Sidebar is on the right; breadcrumbs and navigation flow right-to-left. Form labels and Arabic table text align right by default.
- Use `dir="ltr"` plus `unicode-bidi: isolate` for phone numbers, booking codes, barcodes, UUID fragments, timestamps in ISO form, and currency numerals.
- Mirror directional icons only when their meaning is directional (back, next, expand direction). Do not mirror garment icons, checkmarks, warning symbols, logos, or non-directional actions.
- In mixed Arabic/Latin content, isolate the Latin run with `<bdi>` or equivalent. Do not insert manual whitespace to repair bidi ordering.
- Keep keyboard focus order aligned with the visual RTL order. Use semantic labels and accessible names for icon-only controls.
- English may appear only where required for a technical identifier, barcode, or configured customer/tenant data; standard navigation and primary workflow labels are Arabic.

## 12. Accessibility and Responsive Quality Gates

- Meet WCAG 2.2 AA: minimum 4.5:1 contrast for normal text, 3:1 for large text and essential non-text controls. Verify token pairings with a contrast checker before implementing new colors.
- Never use `color.text-disabled` for information needed to complete a task. Color is never the only indicator of state, error, or selection.
- Keyboard operation is required for navigation, tables, menus, dialogs, date controls, and segmented inspection controls. Focus must remain visible and trapped inside an open modal.
- Minimum hit target is 44×44px for touch. Body text on mobile is at least 16px; dense table labels may use 12px only when a readable alternative is available.
- Test layouts at 375px, 768px, 1024px, and 1440px. Confirm no clipped Arabic text, broken number direction, overlapping controls, or unintended page-level horizontal scroll.
- Loading states reserve space to prevent layout shift. Use skeleton rows for table loading and preserve table column widths while loading.

## 13. Do's and Don'ts

### Do

- Use black/navy textile-like surfaces with clear depth between canvas, shell, and cards.
- Reserve satin gold for the primary action, current section, or a deliberately highlighted value.
- Use consistent status badge pairs and explicit Arabic state names across tables, forms, dashboards, and returns.
- Keep high-frequency tasks close to the top of the page and minimize the number of steps to create or inspect a booking.
- Show booking dates, item status, outstanding balance, and collateral release eligibility before a user commits a consequential action.
- Use precise, restrained icons and typography; preserve enough whitespace for a premium feel without wasting table area.
- Keep every view tenant-scoped; show shop context persistently and make any System Admin tenant switch explicit.

### Don't

- Do not build a public customer booking portal or show customer self-service actions in the internal dashboard.
- Do not show a branch selector until branches exist as an approved, tenant-scoped domain entity.
- Do not mix or aggregate different tenants' inventory, customers, bookings, financials, or audit logs in one operational view.
- Do not use gold gradients, metallic bevels, glow effects, glassmorphism, decorative flourishes, or oversized hero layouts in operational screens.
- Do not use purple outside the National ID collateral lifecycle, or red as a generic accent.
- Do not use emoji as interface icons, color-only state communication, tiny touch targets, placeholder-only labels, or hidden focus rings.
- Do not add ID image uploads, ID scans, gateway payment actions, or customer-facing communications.
- Do not make a card for every piece of information or nest cards inside cards; use unframed sections and tables where appropriate.
- Do not use viewport-based font sizing, arbitrary spacing values, physical left/right layout rules, or hand-tuned one-off status colors.

## 14. Implementation Mapping

Map these tokens into the Filament panel theme and the Tailwind CSS 4 theme layer. Keep this document as the source of truth; component-level exceptions require a documented reason and must preserve the semantic state mapping. Filament defaults may be used where they satisfy this specification; avoid fragile overrides of internal Filament markup.

```css
:root {
    color-scheme: dark;
    --sr-canvas: #090D16;
    --sr-shell: #0B1220;
    --sr-surface-1: #0F172A;
    --sr-surface-2: #111C31;
    --sr-surface-3: #172033;
    --sr-border-subtle: #1E293B;
    --sr-border-default: #334155;
    --sr-text-primary: #F8FAFC;
    --sr-text-secondary: #CBD5E1;
    --sr-text-muted: #94A3B8;
    --sr-gold: #F59E0B;
    --sr-gold-hover: #D97706;
    --sr-emerald: #10B981;
    --sr-crimson: #EF4444;
    --sr-velvet: #A855F7;
    --sr-radius-control: 6px;
    --sr-radius-card: 8px;
    --sr-focus-ring: 0 0 0 2px rgba(245, 158, 11, 0.32);
}
```