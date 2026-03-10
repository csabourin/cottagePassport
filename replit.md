# Stamp Passport — Craft CMS Plugin

## Overview

Stamp Passport is a self-contained Craft CMS 4/5 plugin that enables QR-based passport or challenge experiences with location-aware check-ins. Users scan QR codes at physical locations to collect digital stamps in a passport booklet. The plugin supports geofence validation, multi-site content, optional contest/reward tracking, and optional Freeform integration for reward forms.

The repository contains:
- A full Craft CMS plugin (`src/`) with CP management UI, REST-style JSON APIs, Twig frontend templates, and a variable API
- A `_legacy/` directory with the original standalone PHP/HTML/JS prototype that preceded the Craft CMS plugin

The plugin is distributed via Composer as `csabourin/craft-stamp-passport`.

## User Preferences

Preferred communication style: Simple, everyday language.

## System Architecture

### Plugin Entry Point
- `src/Plugin.php` — bootstraps the plugin, registers services, routes, CP sections, and Twig variables
- Plugin handle: `stamp-passport`, class namespace: `csabourin\stamppassport`

### Control Panel (CP) Layer
- `src/controllers/CpController.php` — handles CP routes for managing passport items
- `src/web/assets/cp/css/cp.css` — minimal CP UI styles
- The CP has both a settings page (`hasCpSettings: true`) and a dedicated section (`hasCpSection: true`)

### Public API Layer
Two controllers expose JSON endpoints consumed by the frontend JavaScript:
- `src/controllers/ApiController.php` — handles:
  - `actions/stamp-passport/api/locations` — returns item list and config
  - `actions/stamp-passport/api/collect` — processes a stamp collection attempt
  - `actions/stamp-passport/api/resolve` — resolves a QR short code to a location
- `src/controllers/ContestProgressController.php` — handles:
  - `api/contest-progress` — cross-domain contest sync endpoint

These API contracts are **frozen** — JSON keys and HTTP status codes must not change without intentional versioning.

### Service Layer
- `src/services/Items.php` — manages passport location items and per-site content
- `src/services/ContestProgress.php` — manages contest participant tracking

### Data Layer
- `src/records/*.php` — Craft ActiveRecord models mapping to database tables
- `src/migrations/*.php` — database schema migrations run via Craft's migration system

### Frontend Layer
- `src/templates/_frontend/*.twig` — Twig templates rendered for the public-facing passport page
- `src/web/assets/frontend/css/passport.css` — WCAG 2.2 AA compliant mobile-first CSS
- `src/web/assets/frontend/js/passport.js` — vanilla JavaScript (IIFE, no framework) handling:
  - IndexedDB storage with localStorage fallback
  - QR-based stamp collection flow
  - Geofence validation via browser Geolocation API
  - Progress tracking and stamp grid rendering
  - GA4 analytics events
  - Freeform modal triggers (optional)
  - Cross-domain contest progress sync using UUID v4 `cid`
- Frontend config injected via `window.__PASSPORT_CONFIG__` from the Twig layout

### Twig Variable API
- `src/variables/StampPassportVariable.php` — exposes `craft.stampPassport` in Twig templates

### Multi-Site Support
- Items have per-site content (titles, descriptions, links) stored per Craft site handle
- Settings support per-site display text overrides; blank values fall back to neutral defaults
- Language switcher is optional and configurable

### Offline-First Design
- The frontend uses IndexedDB as the primary store with localStorage as a fallback
- Stamps collected offline are synced when connectivity is available

### Optional Freeform Integration
- Freeform is **never** a hard dependency — the plugin works fully without it
- When Freeform is installed, it can be used for reward/draw entry forms triggered at stamp thresholds

### Contest Progress Protocol
- Cross-domain sync uses a UUID v4 `cid` (contest participant ID)
- Payloads must include `schemaVersion` (currently `1`)
- A session-bound `contestWriteToken` provides anonymous write capability

### Legacy Prototype (`_legacy/`)
- Standalone static HTML + vanilla JS + PHP backend (no Craft CMS)
- Uses the same geofence and QR concepts, but with flat-file config (`config/items.json`, `config/settings.json`)
- Kept for historical reference; the Craft plugin is the active codebase

## External Dependencies

### Required
- **Craft CMS** `^4.0 || ^5.0` — the only hard Composer dependency
- **PHP** — 8.0+ for Craft 4, 8.2+ for Craft 5

### Optional Integrations
- **Solspace Freeform** — for reward/draw entry forms; must remain an optional integration with zero hard coupling
- **Google Analytics 4 (GA4)** — frontend analytics events via `ga4MeasurementId` setting; loaded only when configured

### Browser APIs Used by Frontend JS
- **IndexedDB** — primary offline stamp storage
- **localStorage** — fallback storage and language preference
- **Geolocation API** — geofence validation during check-in

### No External Package Dependencies
The plugin intentionally avoids third-party PHP packages beyond Craft CMS itself, keeping the installation footprint minimal.