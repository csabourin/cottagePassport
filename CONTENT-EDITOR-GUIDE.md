# Stamp Passport for Content Editors

This document explains **why the plugin works the way it does** and what features you can control in the Craft control panel.

## What this plugin is designed to do

Stamp Passport creates a QR-based challenge experience where visitors:
- open a passport page,
- scan location QR codes,
- collect stamps,
- and optionally unlock rewards or contest forms.

The content model is designed so editors can manage messaging and location content in one place, while technical reliability (scan validation, progress sync, offline support) stays automatic.

---

## Design decisions (editor-focused)

## 1) One location identity, translated content per site
Each location has one shared record (code, image, coordinates, enabled status), plus site-specific content (title, description, CTA text, linked entry).

**Why this matters:**
- You can keep one canonical location while tailoring copy per language/site.
- Editors avoid duplicated item setup across sites.

## 2) Frontend copy supports per-site overrides with fallbacks
The plugin supports per-site UI text overrides in settings. If a field is blank, built-in defaults are used.

**Why this matters:**
- Editors can localize only what is needed.
- Missing translations won’t break the experience.

## 3) Check-ins are reliable even with weak connectivity
The passport stores progress locally and syncs when possible.

**Why this matters:**
- Visitors can still complete scans in patchy network conditions.
- Editor-managed campaigns are less likely to fail due to connectivity.

## 4) Geofence validation is configurable
Admins can enable/disable geofence globally and set a radius.

**Why this matters:**
- Campaigns can enforce on-location scans when needed.
- Teams can disable strict location checks for accessibility, indoor locations, or temporary constraints.

## 5) Route and branding are settings-driven
Editors/admins can configure route prefix, logos, background assets, colors, and custom CSS from plugin settings.

**Why this matters:**
- Most campaign-level customization can happen without code changes.
- Seasonal refreshes are easier.

## 6) Optional integrations are kept optional
Freeform integration is used only if installed/configured.

**Why this matters:**
- Core passport/check-in experience still works independently.
- Form workflows can be added later without replatforming.

---

## Main editor features in the Control Panel

## Location management (`Stamp Passport → Items`)
For each location/item, editors can manage:
- Enabled/disabled status
- Coordinates (latitude/longitude)
- Location image
- Per-site content:
  - Title
  - Description
  - Optional linked entry
  - CTA link text

## Campaign/settings management (`Stamp Passport → Settings`)
Depending on permissions and workflow, editors/admins can adjust:
- Frontend route prefix (and site-specific prefixes if configured)
- Geofence on/off and radius
- Reward thresholds (draw threshold, sticker cap)
- Optional form handles (Freeform)
- Brand assets (logos, backgrounds, marker image, favicon, OG image)
- Theme colors and custom CSS
- Per-site UI text labels/messages
- Per-site contest rules content
- Display behaviors (language switcher, disclaimer modal, org name visibility)
- QR appearance (foreground/background colors)

## Frontend behavior editors should know
- Visitors land on the configured route (default `/passport`).
- Scan/check-in progress is reflected immediately in the UI.
- Language switching keeps visitor identity/progress continuity where possible.
- Contest progress can sync with revision checks to avoid overwriting newer data.

---

## Recommended content workflow
1. Confirm campaign settings (branding, route, thresholds, UI text).
2. Create/verify all location items.
3. Add per-site translated content for each item.
4. Verify linked entries and CTA copy.
5. Test sample QR scans on mobile in each language.
6. Publish campaign and monitor progress/stats.

---

## Open questions (to decide whether they should be documented for editors)
1. Should this guide include a **quick troubleshooting section** (e.g., “scan not counting”, geolocation denied, no network)?
2. Should we add a **permissions matrix** (what content editors can change vs what only admins should change)?
3. Should we explicitly document **Freeform-dependent features** as optional, with a fallback message pattern when Freeform is not installed?
4. Should we include **recommended copy standards** (CTA length, title limits, bilingual tone guidance) for consistency across locations?
5. Should this file be expanded into a **step-by-step launch checklist** used before every campaign go-live?
