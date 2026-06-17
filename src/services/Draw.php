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

        $submissions = $this->fetchDrawSubmissions($formHandle);
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
     * Read draw-form submissions via Freeform's element API, returning each
     * submission's id and its `contestCid` value.
     *
     * Returns null (not an empty array) when Freeform is unavailable or the read
     * fails, so callers can distinguish "no entrants" from "can't read entrants".
     *
     * @return array<int,array{id:int,cid:string}>|null
     */
    private function fetchDrawSubmissions(string $formHandle): ?array
    {
        $class = self::FREEFORM_SUBMISSION_CLASS;

        if (Craft::$app->getPlugins()->getPlugin('freeform') === null || !class_exists($class)) {
            return null;
        }

        try {
            /** @var \craft\elements\db\ElementQueryInterface $query */
            $query = $class::find()
                ->form($formHandle)
                ->isSpam(0)
                ->limit(null);

            $out = [];
            foreach ($query->all() as $submission) {
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
