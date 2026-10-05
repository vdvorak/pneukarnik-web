# Simple Web Design System

A small, themeable design system for simple small-business websites (local services, schools, studios). It was extracted from one sample site — **Autoškola U Bouráka**, a two-instructor driving school in Velké Meziříčí (CZ) — which serves as the default theme. **Brand colours are expected to change per website**; everything else (type, radii, shadows, layout, component shapes) stays.

## Source
- `design/prototype.html` (attached local folder, read-only) — a bundled, self-contained multi-page prototype of the sample site: 17 pages + shared Header/Footer, built as Design Components with inline styles.
- Unpacked page sources are kept in `_source/pages/*.dc.txt` for reference (Uvod = homepage, Cenik, Ridicsky-kurz-B, Prihlaska, Kontakt, …).

## Index
- `styles.css` — entry point (imports only)
- `tokens/` — `webfonts.css`, `colors.css`, `typography.css`, `spacing.css` (spacing, layout, radii, shadows, motion), `base.css`
- `assets/` — `fonts/` (Montserrat, Nunito Sans variable woff2), `logo-sample.jpg` (sample brand mascot logo), `icons/` (facebook, instagram, tiktok SVG)
- `guidelines/` — foundation specimen cards (Colors, Type, Spacing, Brand)
- `components/` — React primitives (below)
- `ui_kits/website/` — click-through recreation of the sample site
- `SKILL.md` — Agent Skill entry

## Components
- **core/** — Button, Pill, NumberBadge, Avatar, Notice, ArrowLink
- **cards/** — Card, CourseCard, PlanCard
- **content/** — PriceTable, Accordion, Steps, ReasonItem, CheckList
- **forms/** — Field, Input, Select, Textarea, Checkbox, ChoiceChips
- **layout/** — TopBar, SiteHeader, SiteFooter, MobileActionBar, PageHero, Section, Eyebrow

The source has no formal component library; this inventory is the set of patterns repeated across its pages.

**Intentional additions:** Notice `info`/`danger` tones and Button `outline`/`muted`/`disabled` (the source only has inline one-offs); Pill `tint`/`accent` tones. They exist so other sites have semantic variants.

## UI kit
`ui_kits/website/index.html` — Úvod (home with tempo picker + FAQ), Ceník, Řidičák B (plan cards), Přihláška (validated form → success state), Kontakt. Navigate via header links. Pages not recreated: Kurzy list, O nás, Časté dotazy, Jak to probíhá and the small course detail pages (same patterns).

## Rebranding a site
Override only the `--brand-*` tokens:
```css
:root{--brand-primary:#0F7A5C;--brand-primary-hover:#0B5E47;--brand-primary-tint:#E6F5EF;--brand-accent:#FF7A3D;--brand-accent-hover:#E8682E;--brand-accent-strong:#B24E1E;--brand-accent-tint:#FFEFE6}
```
Hover shadows and CTA glow are derived from brand tokens via `color-mix`. Keep the accent light/bright enough for ink (#232323) text on it; keep the primary dark enough for white text.

---

## CONTENT FUNDAMENTALS
- **Language:** Czech. Formal *Vy* with capitalised **Vás / Vám / Váš** ("my Vás řídit naučíme", "Pošleme Vám e-mail"). The business speaks as **"my"** (we); the legal text slips to "Nejsem plátce DPH" (sole trader).
- **Tone:** friendly, plain, reassuring, concrete. Short declarative sentences, no marketing superlatives. Facts over claims: "Na lekci teorie je nejvýš 5 lidí." "Žádná fabrika na řidičáky."
- **Transparency:** prices everywhere, including what is *not* included ("Co v ceně není", storno fees). Numbers written Czech-style with thin spacing: `22 000 Kč`, `2 × 11 000 Kč`, `Po–Pá 8–17`, en-dash ranges.
- **Casing:** headings in UPPERCASE via CSS (written in sentence case in source). Buttons sentence case: "Přihlásit se do kurzu", "Zobrazit ceník". Eyebrows uppercase, letter-spaced.
- **Link copy:** action + arrow — "Více o kurzu →", "Jak to probíhá podrobně →", back links "← Kurzy".
- **Headings as questions / statements a customer would say:** "Na co se nás často ptáte", "Máte otázku? Zavolejte nám.", "Kdo Vás naučí řídit".
- **Mascot voice:** the turtle speaks in first person once ("Dobrý den, jsem želva Bourák! Pomůžu Vám vybrat kurz.").
- **Errors** are polite and specific: "Vyplňte prosím toto pole.", "Zkontrolujte prosím e-mail, něco v něm chybí."
- **No emoji.** Unicode glyphs only (✓ → ← ☎ + −).

## VISUAL FOUNDATIONS
- **Colour:** white page; one saturated **primary** (blue #1F5FD6) for links, active nav, numbers, header CTA; one warm **accent** (yellow #F4B200) for the main CTA, wordmark prefix, active-nav underline and highlights on dark. Ink #232323 instead of black. Cool greys #F5F7F9 (muted sections/tiles) and #EEF0F2 (dividers). Accent text on dark is the only coloured text besides primary.
- **Type:** Montserrat 900 uppercase for all headings, prices and numbers (tight 1.12–1.2 line-height); Nunito Sans for body (400) and UI labels (700/800). Body 16–17px/1.6, lead 18px, secondary text #555.
- **Backgrounds:** flat. The only gradient is the hero band: brand tint → white, top to bottom. Sections alternate white / #F5F7F9 full-bleed; occasional dark #232323 rounded panels for emphasis. No textures, patterns or photos in the sample; imagery = the cartoon mascot logo (blended with `mix-blend-mode:multiply` on the tint).
- **Corners:** generous. Cards 20px, smaller tiles/FAQ items 16px, inputs 10px, large dark panel 24px; **every button and chip is a full pill (999px)**; avatars/number discs round.
- **Cards:** white + soft ink shadow `0 4px 24px rgba(35,35,35,.08)`, no border. Flat tiles (muted/tint) have no shadow. Coloured header band on plan cards. Never a coloured left border.
- **Shadows:** low-opacity, ink-tinted, large blur; brand-tinted on hover (`0 12px 36px` primary @18%). Hero CTA has an accent glow. Floating card overlapping the hero uses `0 10px 40px`.
- **Borders:** only 1px #EEF0F2 row dividers, 1.5px #D5D9DE input borders, 1.5–2px coloured borders on chips/notices, 3px accent underline on active nav.
- **Hover:** colour swaps, no motion — accent → darker accent, primary → darker primary, secondary button text turns primary, links darken, cards gain a brand-tinted shadow, footer socials fill with accent. **Press:** no special state. No transitions in source; if you add them keep ≤200ms.
- **Layout:** 1200px container (860–1100 for reading pages), 24px gutters, 80px section rhythm, 20px grid gaps, grids `repeat(auto-fit,minmax(min(100%,300px),1fr))` — fully fluid, no breakpoints except header (<900px → Menu pill) and mobile action bar (<720px, fixed bottom: Zavolat + Přihlásit se).
- **Transparency/blur:** none except white-on-dark opacity (.6–.9) for secondary text, rgba(255,255,255,.1) social discs and .15 dividers on dark.
- **Animation:** none.

## ICONOGRAPHY
- No icon font or icon library. Icons are **Unicode characters** styled with weight/colour: ✓ (check lists, 900 weight in brand colour), → / ← (links), ☎ (top bar phone), + / − (accordion toggles inside round tinted discs).
- Numbers in round discs replace pictograms for feature lists and steps.
- Only SVGs: three social glyphs in the footer (Facebook, Instagram, TikTok), copied to `assets/icons/`, single-colour `currentColor` (rendered white on dark discs via `filter:invert(1)` when used as `<img>`).
- If a site truly needs pictograms, use [Lucide](https://lucide.dev) at 2px stroke (matches the Instagram glyph) — *substitution, not in source*.
- No emoji.

## Brand assets
`assets/logo-sample.jpg` is the **sample site's** logo (cartoon turtle with L-plate, "U BOURÁKA / AUTOŠKOLA"). Other sites must supply their own logo; when absent, use the type wordmark (accent prefix + ink name, Montserrat 900 uppercase).
