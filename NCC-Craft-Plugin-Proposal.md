# Proposal: Craft CMS Stamp Passport Plugin

Prepared: April 1, 2026  
Prepared for: National Capital Commission (NCC)  
Prepared as: Boutique agency cost estimate / proposal-style submission

## 1. Executive Summary

This document provides an estimated agency-style price for the design, development, testing, and deployment of the Craft CMS "Stamp Passport" plugin contained in this repository.

Based on the observed scope, a boutique digital agency would reasonably price this engagement at:

- Estimated fixed-fee project price: `CA$72,000`
- Expected range: `CA$60,000 to CA$85,000`
- Estimated duration: `6 to 8 weeks`

This estimate assumes a polished, client-ready delivery rather than a bare technical prototype. It includes project management, QA, responsive frontend implementation, Craft CMS control panel work, custom plugin architecture, public API endpoints, geofence check-in logic, contest progress sync, and bilingual / multisite-ready implementation.

## 2. Scope Observed in the Plugin

The current plugin scope is materially larger than a simple Craft add-on. Based on the codebase, the solution includes:

- Custom Craft plugin structure with installation and migrations
- Custom control panel section for items, settings, QR generation, display text, rules, and dashboard views
- Item management with image support, ordering, per-site content, and linked entry support
- Frontend passport experience with custom UI, progress tracking, and modal flows
- QR code resolution and public JSON API endpoints
- Optional geofence validation for check-ins
- Contest progress synchronization endpoint with write tokens, conflict handling, and rate limiting
- Multisite and bilingual content support
- Optional Freeform integration
- Analytics and reporting surfaces
- Unit and browser-oriented test coverage

Representative implementation files include:

- [src/controllers/CpController.php](/home/csabouri/cottagePassport/src/controllers/CpController.php)
- [src/controllers/ApiController.php](/home/csabouri/cottagePassport/src/controllers/ApiController.php)
- [src/controllers/ContestProgressController.php](/home/csabouri/cottagePassport/src/controllers/ContestProgressController.php)
- [src/services/Items.php](/home/csabouri/cottagePassport/src/services/Items.php)
- [src/services/ContestProgress.php](/home/csabouri/cottagePassport/src/services/ContestProgress.php)
- [src/web/assets/frontend/js/passport.js](/home/csabouri/cottagePassport/src/web/assets/frontend/js/passport.js)
- [src/web/assets/frontend/css/passport.css](/home/csabouri/cottagePassport/src/web/assets/frontend/css/passport.css)

## 3. Pricing Basis

This proposal assumes a boutique Canadian agency delivery model with:

- A blended billing rate of `CA$160/hour`
- Approximately `450 billable hours`
- A 1 to 2 month delivery window

The blended rate reflects a mix of senior development, frontend implementation, QA, and light project management rather than a single-role contractor rate.

## 4. Detailed Cost Breakdown

| Workstream | Estimated Hours | Rate | Subtotal |
|---|---:|---:|---:|
| Discovery, requirements confirmation, technical design | 28 | CA$160/hr | CA$4,480 |
| Plugin architecture, data model, records, migrations | 56 | CA$160/hr | CA$8,960 |
| Craft CP section build: items, settings, rules, QR tools, dashboard | 64 | CA$160/hr | CA$10,240 |
| Frontend passport UI, responsive theming, templates, interactions | 88 | CA$160/hr | CA$14,080 |
| QR resolver, public API endpoints, check-in flow, geofence logic | 64 | CA$160/hr | CA$10,240 |
| Contest progress sync, write token handling, conflict resolution, rate limiting | 56 | CA$160/hr | CA$8,960 |
| Multisite, bilingual setup, content overrides, Freeform integration, analytics hooks | 36 | CA$160/hr | CA$5,760 |
| QA, bug fixing, accessibility/device checks, UAT support, release hardening | 44 | CA$160/hr | CA$7,040 |
| Project management, status reporting, client coordination | 14 | CA$160/hr | CA$2,240 |
| **Total** | **450** |  | **CA$72,000** |

## 5. Suggested Commercial Framing

If this were being submitted formally by an agency, the pricing could be presented as:

- Fixed-fee delivery: `CA$72,000`
- Plus applicable taxes
- Payment terms:
  - `30%` on project kickoff
  - `40%` at functional completion / UAT start
  - `30%` at final delivery

An agency may also include a contingency or procurement buffer and round the submission to `CA$74,500` or `CA$75,000` depending on procurement format.

## 6. Timeline

A realistic delivery schedule for this scope would be:

| Phase | Duration |
|---|---|
| Discovery and technical confirmation | 3 to 5 business days |
| Core plugin and CP implementation | 2 to 3 weeks |
| Frontend interactions, API integration, and contest sync | 2 to 3 weeks |
| QA, revisions, bilingual review, launch readiness | 1 to 2 weeks |

Total schedule:

- `6 to 8 weeks`

## 7. Team Assumption

Typical boutique agency staffing for this estimate:

- 1 senior full-stack developer / technical lead
- 1 frontend-capable developer or designer-developer hybrid
- 1 QA / PM function at fractional allocation

This is priced as a small agency engagement, not an enterprise implementation with a large PM, design, and stakeholder overhead layer.

## 8. Assumptions

This estimate assumes:

- NCC provides branding assets, copy, contest rules, and bilingual content inputs
- Craft CMS environment and hosting are already available
- No custom native mobile app is required
- No third-party enterprise integrations are required beyond items already evident in the codebase
- Freeform, if used, is already licensed and available
- The project is delivered as a plugin implementation and frontend experience within an existing Craft environment
- Procurement, legal review, and post-launch support are outside the fixed-fee estimate unless specifically added

## 9. Exclusions

The following would typically be priced separately if requested:

- Extensive discovery workshops or stakeholder facilitation
- Full visual design phase or brand development
- Translation services
- Enterprise security review documentation
- Formal accessibility audit by a specialist third party
- Hosting, DevOps, uptime monitoring, or managed support retainers
- Significant post-launch feature expansion

## 10. Pricing Commentary

If the work were delivered as a quick internal build by an individual contractor, the price could land lower. If it were procured through a larger digital agency with heavier process overhead, the same scope could easily exceed `CA$85,000 to CA$100,000`.

For a boutique agency building this for the NCC, `CA$72,000` is a credible and defensible proposal number. It is high enough to cover proper delivery and low enough to remain believable for a 1 to 2 month engagement.

## 11. Recommended Submission Number

If a single proposal amount is needed:

**Recommended bid: `CA$72,000` plus applicable taxes**
