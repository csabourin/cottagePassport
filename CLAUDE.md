# CLAUDE.md

This file documents the implementation rules and design choices for the Stamp Passport Craft CMS plugin.

## Purpose

Stamp Passport is a self-contained Craft CMS 4 plugin for QR-based location check-ins, progress tracking, and optional contest sync across domains/languages.

Primary goals:
- Reliable QR check-in experience on mobile.
- Multi-site content support (per-site labels and item content).
- Offline-first behavior with eventual sync.
- Minimal external dependencies.

## Architecture

- Plugin entry: `src/Plugin.php`
- CP controllers: `src/controllers/CpController.php`
- Public JSON APIs:
  - `src/controllers/ApiController.php`
  - `src/controllers/ContestProgressController.php`
- Core services:
  - `src/services/Items.php`
  - `src/services/ContestProgress.php`
- Data layer:
  - `src/records/*.php`
  - `src/migrations/*.php`
- Frontend templates/assets:
  - `src/templates/_frontend/*.twig`
  - `src/web/assets/frontend/*`
- Twig variable API:
  - `craft.stampPassport` via `src/variables/StampPassportVariable.php`

## Hard Rules (Do Not Break)

1. Keep Freeform optional.
- Never introduce a hard package dependency on Freeform.
- Plugin must still function when Freeform is not installed.

2. Preserve API contracts unless versioned deliberately.
- `actions/stamp-passport/api/locations`
- `actions/stamp-passport/api/collect`
- `actions/stamp-passport/api/resolve`
- `api/contest-progress`
- Existing JSON keys/status codes are part of contract and consumed by frontend JS.

3. Preserve contest progress protocol semantics.
- `cid` must be UUID v4.
- Payload must include:
  - `schemaVersion` (currently `1`)
  - `contestVersion` (string)
  - `progress.stepsCompleted` (array)
  - `progress.score` (int)
  - `updatedAt` (string)
- Max payload size is `32768` bytes.
- Writes use optimistic concurrency by `revision`.
- Revision mismatch returns conflict data (`409`).

4. Keep geofence behavior backward compatible.
- If geofence is disabled, allow check-in.
- If item has no coordinates, allow check-in.
- If geofence is enabled and coordinates exist, enforce radius check.

5. Respect text fallback order.
- Per-site admin override (`uiText[siteHandle][key]`)
- Language default (`TEXT_DEFAULTS` by first 2 chars of site language)
- Generic default (`TEXT_DEFAULTS['default']`)

6. Preserve offline-first storage strategy.
- IndexedDB is primary store.
- localStorage fallback is required.
- Do not remove localStorage fallback paths unless replaced with equivalent resilience.

7. Keep table naming and ownership consistent.
- `stamppassport_items`
- `stamppassport_items_content`
- `stamppassport_contest_progress`
- New schema changes must ship as migrations; do not rely on manual SQL.

## Design Choices and Rationale

1. Service-first business logic
- Controllers stay thin; validation and state handling live in services.
- This keeps CP/API handlers simple and easier to test.

2. Split item core data from per-site content
- `ItemRecord` stores shared fields (shortCode, geo, image, sort, enabled).
- `ItemContentRecord` stores localized fields keyed by `(itemId, siteId)`.
- Supports multilingual/multisite content without duplicating item identity.

3. Contest sync uses revisioned upsert
- Server uses `revision` + atomic update to prevent lost updates.
- Client merges local/server payloads on conflict and retries.
- Provides deterministic eventual consistency across domains.

4. Frontend config is server-injected once
- Twig layout injects `window.__PASSPORT_CONFIG__`.
- JS remains static and environment-agnostic, driven by config + API.

5. Route prefix is settings-driven
- Frontend path comes from `settings.routePrefix` (default `passport`).
- Avoid hardcoding route assumptions in templates/scripts.

6. Progressive enhancement for language continuity
- `cid` is carried through language switch links.
- Explicit user language choice is stored and respected.
- Auto-redirect only applies when there is no explicit choice.

## Change Guidelines

1. When changing data schema:
- Update/append migration(s) in `src/migrations/`.
- Keep `Install` for fresh installs and add incremental migrations for upgrades.

2. When changing API responses:
- Update frontend JS expectations in `src/web/assets/frontend/js/passport.js`.
- Update tests/spec docs under `tests/`.
- If behavior is breaking, introduce versioning or compatibility path.

3. When changing contest rules:
- Bump `settings.contestVersion` default and keep migration/upgrade impact in mind.
- Keep old payloads readable when feasible.

4. When changing copy/localization keys:
- Keep `Settings::TEXT_KEYS`, `TEXT_LABELS`, and `TEXT_DEFAULTS` aligned.
- Ensure CP forms and frontend config include new keys.

5. When changing CP item flows:
- Preserve multisite editing behavior and site-aware redirects.
- Keep sorting and deletion behavior compatible with `Craft.AdminTable`.

## Security and Validation Expectations

- Public API endpoints are anonymous and CSRF-disabled by design; compensate with strict input validation.
- Reject malformed IDs/payloads with explicit error codes.
- Never trust client revision/state without server-side checks.
- Keep payload size limits to protect DB/storage.

## Out of Scope

- `_legacy/` is historical reference code and not part of active runtime design.
- Do not base new architecture decisions on `_legacy` unless explicitly migrating behavior.
