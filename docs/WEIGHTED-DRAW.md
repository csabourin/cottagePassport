# Weighted Draw — "More scans, more chances"

Goal: a participant enters the draw **once** (at the draw threshold), and their
number of chances grows automatically as they scan more locations. The winner is
picked with **weighted** random selection where `weight = number of stamps`.

This works because every scan already syncs the participant's stamp count
(`progress.score`) into the `stamppassport_contest_progress` table, keyed by their
`cid`. The draw reads that count **at draw time**, so chances keep rising even
after the entry is submitted.

---

## Piece 1 — Freeform setup (content editor, in the CP) ✅ action required

Each draw submission must carry the participant's `cid` so it can be linked to
their progress record. Add a hidden field to the **draw** form:

1. Craft CP → **Freeform → Forms** → open the form used as the draw form
   (the handle configured under Stamp Passport → Settings → *Draw Form*).
2. Add a field of type **Hidden**.
3. Set its **handle** to exactly `contestCid` (case-sensitive). Leave the default
   value blank — the frontend fills it in.
4. Save the form.

> Repeat for the **sticker** form too if you ever want to weight or audit sticker
> claims the same way. Not required for the draw to work.

If the hidden field is missing, nothing breaks — submissions simply won't carry a
`cid`, and the weighted-draw tool will treat them as ineligible (see piece 3).

---

## Piece 2 — Frontend CID stamping ✅ implemented

`src/web/assets/frontend/js/passport.js` now writes the participant's `cid` into
any `input[name="contestCid"]` inside the draw/sticker form containers:

- `populateContestCidFields()` runs once on init (after the CID is known) and
  again each time a form modal opens (`showModal`), so the value is current even
  if Freeform re-renders.
- Single-submission behavior is unchanged: the draw form is still submitted once;
  the *weighting*, not repeated submissions, provides the extra chances.

No API contracts or the contest-sync protocol were touched.

---

## Piece 3 — Admin weighted-draw tool ✅ implemented (integrity hardening still pending)

A CP screen that selects a winner from eligible draw entries, weighted by stamp
count, with an auditable, reproducible result. This is item 3.1 in `todo.md`.

**Shipped:**
- Migration + `stamppassport_draw_results` table (audit log: seed, pool snapshot, winner)
  — `src/migrations/m260617_000000_create_draw_results_table.php`, also in `Install`.
- `Draw` service — `src/services/Draw.php`: pool builder (Freeform-guarded), seeded
  weighted selection, result persistence, and `verify()` (re-runs from the stored seed).
- CP controller actions `actionDraw` / `actionDrawRun` + a **Draw** subnav item and route.
- CP view `src/templates/draw/index.twig`: pool summary, date + weighting filters,
  draw button (confirmation-gated), winner reveal with a link to the Freeform
  submission, excluded-entries breakdown, and a re-verifiable draw history.
- Weighting modes: `total` (default) and `bonus` (stamps beyond threshold), chosen per draw.
- Degrades gracefully when Freeform is absent or the draw form is unconfigured.

**Still pending (do before a real-prize draw):** the integrity hardening in §3.6 below —
the contest-sync endpoint still trusts self-reported stamps, and weighting raises the
incentive to fabricate them.

---

### Original plan (for reference)

### 3.1 Constraints & principles

- **Freeform stays optional** (hard rule). The tool must be hidden / degrade
  gracefully when Freeform is not installed or no draw form handle is set. Access
  the Freeform plugin only behind `Craft::$app->plugins->isPluginEnabled('freeform')`
  and guard every call.
- **Never trust the client.** Re-derive eligibility and weight server-side from
  `stamppassport_contest_progress`, not from anything posted by the browser.
- **Reproducible.** A draw should be repeatable from a stored seed so a result can
  be re-verified after the fact.

### 3.2 Data flow

```
Freeform draw submissions ──(contestCid)──► contest_progress rows ──► weight = score
        │                                          │
        └── name / email (for contacting)          └── re-checked: score >= drawThreshold
```

1. Load draw-form submissions via Freeform's submissions API.
2. For each submission, read the `contestCid` field.
3. Join to `ContestProgressRecord` by `contest_id = contestCid`.
4. **De-duplicate by `cid`** (one ballot-set per participant, even if they
   submitted on two devices) — keep the most recent submission for contact info.
5. **Re-verify eligibility**: include only entries whose progress `score >= drawThreshold`.
   Drop entries with no `cid`, no matching progress, or below threshold (show them
   in an "excluded" list with the reason, for transparency).
6. Compute weight per eligible entry.

### 3.3 Weighting

- **Default: `weight = score`** (total stamps). 5 stamps → 5 ballots, 13 → 13.
  Simplest to explain to participants.
- Alternative (config toggle): `weight = score - drawThreshold + 1` (threshold is
  the ante; extra scans are the bonus). Make it a setting so it's not hard-coded.
- Selection: cumulative-weight pick using a **seeded** PRNG. Store the seed.

### 3.4 Backend

- New action(s) in `src/controllers/CpController.php`:
  - `actionDraw()` → renders the tool (eligible pool, counts, excluded list).
  - `actionDrawRun()` (POST, CSRF-protected, permission-gated) → performs the
    weighted pick, persists the result, returns the winner.
- Optionally a thin service method, e.g. `ContestProgress::buildDrawPool(...)`, to
  keep the controller thin (matches the service-first design).
- New table `stamppassport_draw_results` (via migration, per hard rules):
  `id, formHandle, dateDrawn, seed, eligibleCount, winnerCid, winnerSubmissionId,
  poolSnapshotJson, drawnByUserId, uid`. The snapshot makes the draw auditable and
  re-verifiable.

### 3.5 CP UI

- Add a **Draw** tab/section near Stats.
- Show: eligible entrant count, total ballots, weighting mode in effect, a
  date-range filter (reuse the Stats range), and an **excluded entries** panel
  with reasons.
- "Draw a winner" button → confirmation → reveals winner (name/email from the
  submission) and logs the result.
- History list of past draws (from `stamppassport_draw_results`), each
  re-verifiable from its stored seed + snapshot.

### 3.6 Integrity dependency (important)

Weighting raises the incentive to fabricate stamps. Today the contest-sync
endpoint accepts any `stepsCompleted` array without geofence proof (the integrity
gap noted earlier). Before running a real prize draw, close this — e.g. have
`api/collect` mint a short-lived signed "earned" token per `shortCode` that the
sync write must include, or record geofence-validated collections server-side and
weight from those rather than from the self-reported payload. Track as a blocker
for piece 3 if prizes have real value.

### 3.7 Testing

- Unit: weighted selection distribution (seeded → deterministic), de-dup by cid,
  eligibility re-check at/below threshold, both weighting modes.
- Integration: Freeform-absent path (tool hidden, no fatals); submissions with
  missing/blank/unknown `contestCid`; cid present but no progress row.
- Manual: full run on staging with a handful of seeded entries.

### 3.8 Suggested sequence

1. Migration + `stamppassport_draw_results` table.
2. Service method to build the eligible, de-duplicated, weighted pool (Freeform-guarded).
3. CP read-only view (pool + excluded list) to validate the data before any draw.
4. `actionDrawRun()` + result persistence + winner reveal.
5. Draw history + re-verify-from-seed.
6. (Blocker if prizes are real) integrity hardening per 3.6.
