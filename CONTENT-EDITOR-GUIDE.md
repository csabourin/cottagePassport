# Stamp Passport for Content Editors

This guide explains the plugin’s **editor-facing features** and the **design decisions behind the Control Panel (CP) interface**.

## Audience
This is for content editors and campaign managers (not developers).

---

## What the plugin is built to support
Stamp Passport runs QR-based campaigns where visitors:
- open a passport page,
- scan location QR codes,
- collect stamps,
- and unlock rewards/forms when they meet campaign thresholds.

The editor experience is designed so you can manage content and campaign settings, while technical behavior (scan validation, progress tracking, syncing) is handled automatically.

---

## Why the CP interface is designed this way

## 1) One item identity, per-site content fields
Each location item keeps one shared identity (short code, enabled state, coordinates, image) and separate per-site content (title, description, CTA text, linked entry).

**Reason:** consistency + localization. You avoid duplicating the same location for each language/site, while still tailoring copy per site.

## 2) Entries and assets are selected as Craft elements (not pasted URLs)
In item editing and settings, editors choose linked entries and images through element pickers.

**Reason:** this reduces broken links and keeps relationships inside Craft. If a URL changes, linking to the entry/asset ID is generally safer than hardcoding links.

## 3) Display text is split into a dedicated per-site screen
UI copy is managed in **Display Text**, with placeholders from language defaults and optional per-site overrides.

**Reason:** translation/editorial copy is treated as content work, separate from technical settings, so teams can localize faster and more safely.

## 4) Language/site switch in CP is optimized for faster editing
Multi-site views provide a site switch menu that updates the working view while keeping editing context.

**Reason:** editors often compare/update multiple site versions in one session; faster switching reduces repetitive navigation.

## 5) Settings are grouped into tabs
Settings are split into **General, Appearance, Integrations, Advanced** tabs.

**Reason:** as configuration grew, tabbed sections improved scanability and reduced long-form fatigue for editors/admins.

## 6) Stats dashboard focuses on campaign operations, not raw logs
The dashboard surfaces practical metrics (qualifiers, scans, visitors, averages, most visited location), charts, and date-range filtering.

**Reason:** campaign teams need “how are we doing?” answers quickly (reward qualification, top locations, trend windows) without exporting data.

## 7) Route and language behavior are settings-driven
The plugin supports global + per-site route prefixes and a configurable frontend language switcher.

**Reason:** multilingual campaigns often need localized URLs and optional language controls depending on brand/governance requirements.

---

## Main CP features for editors

## A) Items (`Stamp Passport → Items`)
For each location, editors can manage:
- enabled/disabled status,
- coordinates,
- location image,
- per-site title/description,
- optional linked Craft entry,
- CTA link text.

## B) Display Text (`Stamp Passport → Display Text`)
Per-site UI copy management for labels, modal copy, and helper text.

Editor workflow:
- switch site/language,
- edit only the fields needed,
- leave others blank to inherit defaults.

## C) Contest Rules (`Stamp Passport → Contest Rules`)
Per-site rules content can be managed separately from generic display text, so legal/marketing copy can be updated independently.

## D) Settings (`Stamp Passport → Settings`)
High-level campaign controls include:
- route prefix (and per-site route prefixes),
- geofence enable/radius,
- draw/sticker thresholds,
- branding assets,
- colors and custom CSS,
- optional integrations (GA4, Freeform),
- language switcher and disclaimer behavior,
- QR visual settings.

## E) Stats Dashboard (`Stamp Passport → Dashboard`)
Operational insights include:
- draw qualifiers,
- sticker qualifiers,
- total scans,
- total visitors,
- average scans per visitor,
- average visits per site/location,
- most visited location,
- charts by weekday and recent periods,
- location filter,
- date-range filtering.

Use this page for weekly campaign reviews and in-flight optimizations (e.g., underperforming locations).

---

## Feature evolution (inferred from recent commit history)
Recent updates indicate the CP was intentionally improved for editor operations:
- **Stats dashboard introduced and iterated** (new dashboard, date filtering, chart/data presentation refinements, clearer labels, improved card layout).
- **Multi-site routing expanded** (per-site route prefixes and template updates).
- **Site switching UX refined** (switcher behavior and template consistency improvements).
- **Settings usability improved** (tabbed navigation and layout polish).
- **Content modeling strengthened** (entry relations and text-management split).

In short: the direction has been to make multilingual campaign editing faster, safer, and more measurable from inside Craft.

---

## Recommended editor workflow
1. Confirm campaign settings (route, branding, thresholds, integrations).
2. Add/review all location items.
3. Localize item content per site.
4. Localize Display Text and Contest Rules per site.
5. Validate linked entries/assets.
6. Test QR scans on mobile in each language.
7. Use Dashboard metrics to monitor and adjust during campaign.

---

## Questions to confirm scope of this guide
1. Should we add a short **“How to read dashboard metrics”** section with interpretation examples (e.g., low scans but high qualifiers)?
2. Should we include a **“when to use linked entries vs plain CTA text”** guideline for editorial consistency?
3. Should we include **role boundaries** (what editors should edit vs what should be admin-only)?
4. Should we add a **multilingual QA checklist** (site switch pass, fallback text checks, route prefix checks)?
5. Should we add **common issue playbooks** (e.g., missing location image, geofence complaints, or untranslated UI labels)?
