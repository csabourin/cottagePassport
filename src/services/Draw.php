<?php

namespace csabourin\stamppassport\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use csabourin\stamppassport\Plugin;
use csabourin\stamppassport\records\DrawResultRecord;

/**
 * Weighted prize-draw service.
 *
 * Eligibility and weight come from the plugin's own contest-progress data
 * (re-derived server-side, never trusted from the client). The set of people who
 * actually *entered* the draw comes from Freeform submissions, linked back to a
 * progress record by the hidden `contestCid` field. The Freeform read is fully
 * guarded so a missing/incompatible Freeform degrades gracefully instead of
 * crashing.
 */
class Draw extends Component
{
    public const WEIGHTING_TOTAL = 'total';  // chances = total stamps
    public const WEIGHTING_BONUS = 'bonus';  // chances = stamps beyond the threshold (+1)

    /** Freeform Submission element class — referenced by name so it's optional. */
    private const FREEFORM_SUBMISSION_CLASS = 'Solspace\\Freeform\\Elements\\Submission';

    /** Freeform Hidden field handle that carries the participant CID. */
    public const CID_FIELD_HANDLE = 'contestCid';

    public function isWeightingMode(string $mode): bool
    {
        return in_array($mode, [self::WEIGHTING_TOTAL, self::WEIGHTING_BONUS], true);
    }

    /**
     * Build the draw pool: Freeform draw submissions joined to contest progress
     * by CID, de-duplicated, eligibility re-checked, and weighted.
     *
     * @return array{
     *   freeformAvailable: bool,
     *   formHandle: string,
     *   weightingMode: string,
     *   drawThreshold: int,
     *   submissionsTotal: int,
     *   eligible: array<int,array{cid:string,submissionId:int,score:int,weight:int}>,
     *   excluded: array<int,array{submissionId:int,cid:string,reason:string}>,
     *   excludedCounts: array<string,int>,
     *   totalBallots: int
     * }
     */
    public function buildPool(
        string $formHandle,
        int $drawThreshold,
        string $weightingMode = self::WEIGHTING_TOTAL,
        string $dateFrom = '',
        string $dateTo = ''
    ): array {
        if (!$this->isWeightingMode($weightingMode)) {
            $weightingMode = self::WEIGHTING_TOTAL;
        }
        $drawThreshold = max(1, $drawThreshold);

        $result = [
            'freeformAvailable' => true,
            'formHandle'        => $formHandle,
            'formName'          => '',
            'weightingMode'     => $weightingMode,
            'drawThreshold'     => $drawThreshold,
            'submissionsTotal'  => 0,
            'eligible'          => [],
            'excluded'          => [],
            'excludedCounts'    => [
                'no_cid'          => 0,
                'not_found'       => 0,
                'below_threshold' => 0,
                'duplicate'       => 0,
            ],
            'totalBallots'      => 0,
        ];

        if ($formHandle === '') {
            $result['freeformAvailable'] = false;
            return $result;
        }

        // Resolve the configured handle to a concrete Freeform form first. If it does
        // not resolve, bail out — never fall through to Freeform's "all forms" behaviour,
        // which would read OTHER forms' submissions (e.g. the sticker form).
        $form = $this->resolveFreeformForm($formHandle);
        if ($form === null) {
            $result['freeformAvailable'] = false;
            return $result;
        }
        $result['formName'] = $form['name'];

        $submissions = $this->fetchDrawSubmissions($form['id']);
        if ($submissions === null) {
            $result['freeformAvailable'] = false;
            return $result;
        }
        $result['submissionsTotal'] = count($submissions);

        // Stamp count per CID, recomputed from the stored payload (same basis as the
        // Stats dashboard) so the weight reflects what the participant actually has now.
        $scoreByCid = $this->buildScoreMap($dateFrom, $dateTo);

        $seenCids = [];
        foreach ($submissions as $sub) {
            $cid          = $sub['cid'];
            $submissionId = $sub['id'];

            if ($cid === '' || !Plugin::$plugin->contestProgress->isValidCid($cid)) {
                $result['excluded'][] = ['submissionId' => $submissionId, 'cid' => $cid, 'reason' => 'no_cid'];
                $result['excludedCounts']['no_cid']++;
                continue;
            }
            if (!array_key_exists($cid, $scoreByCid)) {
                $result['excluded'][] = ['submissionId' => $submissionId, 'cid' => $cid, 'reason' => 'not_found'];
                $result['excludedCounts']['not_found']++;
                continue;
            }
            $score = $scoreByCid[$cid];
            if ($score < $drawThreshold) {
                $result['excluded'][] = ['submissionId' => $submissionId, 'cid' => $cid, 'reason' => 'below_threshold'];
                $result['excludedCounts']['below_threshold']++;
                continue;
            }
            if (isset($seenCids[$cid])) {
                // Same participant submitted more than once — count one ballot-set only.
                $result['excluded'][] = ['submissionId' => $submissionId, 'cid' => $cid, 'reason' => 'duplicate'];
                $result['excludedCounts']['duplicate']++;
                continue;
            }
            $seenCids[$cid] = true;

            $weight = $weightingMode === self::WEIGHTING_BONUS
                ? max(1, $score - $drawThreshold + 1)
                : $score;

            $result['eligible'][] = [
                'cid'          => $cid,
                'submissionId' => $submissionId,
                'score'        => $score,
                'weight'       => $weight,
            ];
        }

        // Deterministic, stable order so a stored draw is reproducible from its seed.
        usort($result['eligible'], static fn($a, $b) => strcmp($a['cid'], $b['cid']));

        $result['totalBallots'] = (int)array_sum(array_column($result['eligible'], 'weight'));

        return $result;
    }

    /**
     * Run a weighted draw and persist an auditable result row.
     *
     * @return array{ok:bool, error?:string, resultId?:int, winner?:array, eligibleCount?:int, totalBallots?:int}
     */
    public function drawWinner(
        string $formHandle,
        int $drawThreshold,
        string $weightingMode = self::WEIGHTING_TOTAL,
        string $dateFrom = '',
        string $dateTo = '',
        ?int $userId = null
    ): array {
        $pool = $this->buildPool($formHandle, $drawThreshold, $weightingMode, $dateFrom, $dateTo);

        if (!$pool['freeformAvailable']) {
            return ['ok' => false, 'error' => 'freeform_unavailable'];
        }
        if (empty($pool['eligible'])) {
            return ['ok' => false, 'error' => 'no_eligible_entries'];
        }

        $seed   = (string)random_int(1, PHP_INT_MAX);
        $winner = $this->weightedPick($pool['eligible'], $seed);
        if ($winner === null) {
            return ['ok' => false, 'error' => 'no_eligible_entries'];
        }

        // Snapshot only what verification needs: the ordered (cid, weight) pairs.
        $snapshot = array_map(
            static fn($e) => ['cid' => $e['cid'], 'weight' => $e['weight']],
            $pool['eligible']
        );
        $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_SLASHES);
        if ($snapshotJson === false) {
            $snapshotJson = null;
        }

        $record = new DrawResultRecord();
        $record->formHandle         = $formHandle;
        $record->weightingMode      = $pool['weightingMode'];
        $record->drawThreshold      = $pool['drawThreshold'];
        $record->dateFrom           = $dateFrom !== '' ? $dateFrom : null;
        $record->dateTo             = $dateTo !== '' ? $dateTo : null;
        $record->seed               = $seed;
        $record->eligibleCount      = count($pool['eligible']);
        $record->totalBallots       = (int)$pool['totalBallots'];
        $record->winnerCid          = $winner['cid'];
        $record->winnerSubmissionId = $winner['submissionId'];
        $record->poolSnapshotJson   = $snapshotJson;
        $record->drawnByUserId      = $userId;
        $record->dateDrawn          = gmdate('Y-m-d H:i:s');

        if (!$record->save()) {
            Craft::error('Stamp Passport: failed to save draw result: ' . print_r($record->getErrors(), true), __METHOD__);
            return ['ok' => false, 'error' => 'save_failed'];
        }

        return [
            'ok'            => true,
            'resultId'      => (int)$record->id,
            'winner'        => $winner,
            'eligibleCount' => $record->eligibleCount,
            'totalBallots'  => $record->totalBallots,
        ];
    }

    /**
     * Run a multi-prize weighted draw: pick up to $prizeCount distinct winners in
     * one operation and persist an auditable result row for each.
     *
     * No participant (contestCid) can win more than once: anyone who already won a
     * previously recorded draw is excluded from the pool, and each pick removes its
     * winner before the next. Draws fewer than $prizeCount when the eligible pool
     * runs out. Each saved row keeps its own seed + snapshot so it stays
     * independently re-verifiable.
     *
     * @return array{ok:bool, error?:string, results?:array<int,array{resultId:int,winner:array}>, resultIds?:int[], drawnCount?:int, requestedCount?:int, eligibleCount?:int}
     */
    public function drawWinners(
        string $formHandle,
        int $drawThreshold,
        int $prizeCount,
        string $weightingMode = self::WEIGHTING_TOTAL,
        string $dateFrom = '',
        string $dateTo = '',
        ?int $userId = null
    ): array {
        $prizeCount = max(1, $prizeCount);

        $pool = $this->buildPool($formHandle, $drawThreshold, $weightingMode, $dateFrom, $dateTo);

        if (!$pool['freeformAvailable']) {
            return ['ok' => false, 'error' => 'freeform_unavailable'];
        }

        // Exclude anyone who has already won a recorded draw so nobody wins twice.
        $alreadyWon = $this->previousWinnerCids();
        $remaining = array_values(array_filter(
            $pool['eligible'],
            static fn($e) => !isset($alreadyWon[$e['cid']])
        ));

        if ($remaining === []) {
            return ['ok' => false, 'error' => 'no_eligible_entries'];
        }

        $results   = [];
        $resultIds = [];

        for ($i = 0; $i < $prizeCount && $remaining !== []; $i++) {
            $seed   = (string)random_int(1, PHP_INT_MAX);
            $winner = $this->weightedPick($remaining, $seed);
            if ($winner === null) {
                break;
            }

            // Snapshot the pool used for *this* pick (the still-remaining entrants).
            $snapshot = array_map(
                static fn($e) => ['cid' => $e['cid'], 'weight' => $e['weight']],
                $remaining
            );
            $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_SLASHES);
            if ($snapshotJson === false) {
                $snapshotJson = null;
            }

            $record = new DrawResultRecord();
            $record->formHandle         = $formHandle;
            $record->weightingMode      = $pool['weightingMode'];
            $record->drawThreshold      = $pool['drawThreshold'];
            $record->dateFrom           = $dateFrom !== '' ? $dateFrom : null;
            $record->dateTo             = $dateTo !== '' ? $dateTo : null;
            $record->seed               = $seed;
            $record->eligibleCount      = count($remaining);
            $record->totalBallots       = (int)array_sum(array_column($remaining, 'weight'));
            $record->winnerCid          = $winner['cid'];
            $record->winnerSubmissionId = $winner['submissionId'];
            $record->poolSnapshotJson   = $snapshotJson;
            $record->drawnByUserId      = $userId;
            $record->dateDrawn          = gmdate('Y-m-d H:i:s');

            if (!$record->save()) {
                Craft::error('Stamp Passport: failed to save draw result: ' . print_r($record->getErrors(), true), __METHOD__);
                // If nothing saved yet, surface the error; otherwise keep the winners we have.
                if ($resultIds === []) {
                    return ['ok' => false, 'error' => 'save_failed'];
                }
                break;
            }

            $results[]   = ['resultId' => (int)$record->id, 'winner' => $winner];
            $resultIds[] = (int)$record->id;

            // Remove this winner so they cannot be picked again in this run.
            $winnerCid = $winner['cid'];
            $remaining = array_values(array_filter(
                $remaining,
                static fn($e) => $e['cid'] !== $winnerCid
            ));
        }

        return [
            'ok'             => true,
            'results'        => $results,
            'resultIds'      => $resultIds,
            'drawnCount'     => count($resultIds),
            'requestedCount' => $prizeCount,
            'eligibleCount'  => count($pool['eligible']),
        ];
    }

    /**
     * CIDs that have already won any recorded draw, as a set keyed by cid.
     *
     * @return array<string,true>
     */
    private function previousWinnerCids(): array
    {
        $set = [];
        foreach (DrawResultRecord::find()->select(['winnerCid'])->column() as $cid) {
            $cid = (string)$cid;
            if ($cid !== '') {
                $set[$cid] = true;
            }
        }
        return $set;
    }

    /**
     * Re-run the selection from a stored result's seed + snapshot and report whether
     * it reproduces the recorded winner (auditability).
     *
     * @return array{ok:bool, error?:string, matches?:bool, recomputedCid?:?string}
     */
    public function verify(DrawResultRecord $record): array
    {
        $snapshot = json_decode((string)$record->poolSnapshotJson, true);
        if (!is_array($snapshot) || $snapshot === []) {
            return ['ok' => false, 'error' => 'no_snapshot'];
        }

        $entries = [];
        foreach ($snapshot as $row) {
            if (!is_array($row)) {
                continue;
            }
            $entries[] = [
                'cid'          => (string)($row['cid'] ?? ''),
                'weight'       => (int)($row['weight'] ?? 0),
                'submissionId' => 0,
            ];
        }

        $winner = $this->weightedPick($entries, (string)$record->seed);

        return [
            'ok'            => true,
            'matches'       => $winner !== null && $winner['cid'] === $record->winnerCid,
            'recomputedCid' => $winner['cid'] ?? null,
        ];
    }

    /**
     * @return DrawResultRecord[]
     */
    public function recentResults(int $limit = 20): array
    {
        return DrawResultRecord::find()
            ->orderBy(['dateDrawn' => SORT_DESC, 'id' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    public function getResultById(int $id): ?DrawResultRecord
    {
        return DrawResultRecord::findOne($id);
    }

    /**
     * Stamp count per CID, recomputed from the stored payload.
     *
     * @return array<string,int>
     */
    private function buildScoreMap(string $dateFrom, string $dateTo): array
    {
        $query = (new Query())
            ->from('{{%stamppassport_contest_progress}}')
            ->select(['contest_id', 'payload_json']);

        if ($dateFrom !== '') {
            $query->andWhere(['>=', 'dateUpdated', $dateFrom . ' 00:00:00']);
        }
        if ($dateTo !== '') {
            $query->andWhere(['<=', 'dateUpdated', $dateTo . ' 23:59:59']);
        }

        $map = [];
        foreach ($query->all() as $row) {
            $cid = (string)$row['contest_id'];
            $payload = json_decode((string)$row['payload_json'], true);
            $steps = $payload['progress']['stepsCompleted'] ?? [];
            if (!is_array($steps)) {
                $steps = [];
            }
            $map[$cid] = count($steps);
        }

        return $map;
    }

    /**
     * Resolve a Freeform form handle to its id and name, directly from the
     * freeform_forms table (version-tolerant, matches how the settings dropdown is built).
     *
     * Returns null when Freeform is absent or the handle matches no form — in which
     * case callers must NOT query submissions, to avoid reading every form's data.
     *
     * @return array{id:int,name:string}|null
     */
    private function resolveFreeformForm(string $handle): ?array
    {
        if ($handle === '' || Craft::$app->getPlugins()->getPlugin('freeform') === null) {
            return null;
        }

        try {
            $row = (new Query())
                ->select(['id', 'name'])
                ->from('{{%freeform_forms}}')
                ->where(['handle' => $handle])
                ->one();

            if (!$row || empty($row['id'])) {
                Craft::warning(
                    "Stamp Passport: draw form handle '{$handle}' did not resolve to a Freeform form.",
                    __METHOD__
                );
                return null;
            }

            return ['id' => (int)$row['id'], 'name' => (string)($row['name'] ?? $handle)];
        } catch (\Throwable $e) {
            Craft::warning('Stamp Passport: could not resolve Freeform form: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    /**
     * Read submissions for a specific Freeform form id via the element API, returning
     * each submission's id and its `contestCid` value.
     *
     * Returns null (not an empty array) when the read fails, so callers can distinguish
     * "no entrants" from "can't read entrants".
     *
     * @return array<int,array{id:int,cid:string}>|null
     */
    private function fetchDrawSubmissions(int $formId): ?array
    {
        $class = self::FREEFORM_SUBMISSION_CLASS;

        if (!class_exists($class)) {
            return null;
        }

        try {
            /** @var \craft\elements\db\ElementQueryInterface $query */
            $query = $class::find()
                ->formId($formId)
                ->isSpam(0)
                ->limit(null);

            $out = [];
            foreach ($query->all() as $submission) {
                // Defense in depth: never accept a submission belonging to another form.
                if ((int)$submission->formId !== $formId) {
                    continue;
                }

                $cidValue = '';
                try {
                    $field = $submission->getFieldCollection()->get(self::CID_FIELD_HANDLE);
                    if ($field !== null) {
                        $val = $field->getValue();
                        if (is_scalar($val)) {
                            $cidValue = trim((string)$val);
                        }
                    }
                } catch (\Throwable $e) {
                    // Field absent on this submission (e.g. added later) — treat as no CID.
                }

                $out[] = ['id' => (int)$submission->id, 'cid' => $cidValue];
            }

            return $out;
        } catch (\Throwable $e) {
            Craft::warning(
                'Stamp Passport: unable to read Freeform draw submissions: ' . $e->getMessage(),
                __METHOD__
            );
            return null;
        }
    }

    /**
     * Deterministic weighted random selection. The same ($entries order, $seed)
     * always yields the same winner, which is what makes a stored draw verifiable.
     *
     * @param array<int,array{cid:string,weight:int,submissionId?:int}> $entries
     * @return array|null
     */
    private function weightedPick(array $entries, string $seed): ?array
    {
        if ($entries === []) {
            return null;
        }

        $total = 0;
        foreach ($entries as $e) {
            $total += max(0, (int)$e['weight']);
        }
        if ($total <= 0) {
            return $entries[0];
        }

        // Seed Mersenne Twister deterministically, then restore non-determinism for
        // the rest of the request so nothing else inherits this fixed sequence.
        mt_srand((int)$seed, MT_RAND_MT19937);
        $r = mt_rand(1, $total);
        mt_srand();

        $cumulative = 0;
        foreach ($entries as $e) {
            $cumulative += max(0, (int)$e['weight']);
            if ($r <= $cumulative) {
                return $e;
            }
        }

        return $entries[array_key_last($entries)];
    }
}
