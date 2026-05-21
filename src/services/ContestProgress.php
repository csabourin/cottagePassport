<?php

namespace csabourin\stamppassport\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use csabourin\stamppassport\records\ContestProgressRecord;

class ContestProgress extends Component
{
    /** Maximum payload size in bytes */
    public const MAX_PAYLOAD_SIZE = 32768; // 32 KB
    /** Anonymous write token lifetime in seconds */
    public const WRITE_TOKEN_TTL = 600; // 10 minutes
    private const WRITE_TOKEN_PURPOSE = 'stamp-passport.contest-progress.write-token';
    private const MAX_STEPS_COMPLETED = 500;
    private const MAX_STEP_LENGTH = 64;

    /**
     * Validate that a string is a valid UUID v4.
     */
    public function isValidCid(string $cid): bool
    {
        return (bool)preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $cid
        );
    }

    /**
     * Validate a payload array against the expected schema.
     *
     * @return string|null Error message or null if valid
     */
    public function validatePayload(array $payload): ?string
    {
        if (!isset($payload['schemaVersion']) || !is_int($payload['schemaVersion'])) {
            return 'missing_or_invalid_schema_version';
        }

        if ($payload['schemaVersion'] !== 1) {
            return 'unsupported_schema_version';
        }

        if (!isset($payload['contestVersion']) || !is_string($payload['contestVersion'])) {
            return 'missing_or_invalid_contest_version';
        }

        if (!isset($payload['progress']) || !is_array($payload['progress'])) {
            return 'missing_or_invalid_progress';
        }

        if (!isset($payload['updatedAt']) || !is_string($payload['updatedAt'])) {
            return 'missing_or_invalid_updated_at';
        }
        // Must be an ISO 8601 datetime (YYYY-MM-DDTHH:MM:SS prefix)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $payload['updatedAt'])) {
            return 'missing_or_invalid_updated_at';
        }

        $progress = $payload['progress'];

        if (!isset($progress['stepsCompleted']) || !is_array($progress['stepsCompleted'])) {
            return 'missing_or_invalid_steps_completed';
        }

        if (count($progress['stepsCompleted']) > self::MAX_STEPS_COMPLETED) {
            return 'too_many_steps_completed';
        }

        foreach ($progress['stepsCompleted'] as $step) {
            if (!is_string($step) || $step === '') {
                return 'invalid_step_in_steps_completed';
            }
            if (strlen($step) > self::MAX_STEP_LENGTH) {
                return 'step_too_long';
            }
        }

        if (!isset($progress['score']) || !is_int($progress['score'])) {
            return 'missing_or_invalid_score';
        }

        $stepCount = count($progress['stepsCompleted']);
        if ($progress['score'] < 0 || $progress['score'] > $stepCount) {
            return 'invalid_score';
        }

        return null;
    }

    /**
     * Compute SHA-256 hash of a JSON string.
     */
    public function computeHash(string $json): string
    {
        return hash('sha256', $json);
    }

    /**
     * Mint an anonymous session-bound write token for a specific CID.
     *
     * The token is signed with Craft's security key and includes:
     * - CID
     * - current anonymous session ID
     * - expiration timestamp
     */
    public function issueWriteToken(string $cid): string
    {
        if (!$this->isValidCid($cid)) {
            throw new \InvalidArgumentException('Invalid CID for write token issuance.');
        }

        $session = Craft::$app->getSession();
        if (!$session->getIsActive()) {
            $session->open();
        }

        $payload = [
            'cid' => $cid,
            'sid' => (string)$session->getId(),
            'exp' => time() + self::WRITE_TOKEN_TTL,
        ];

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            throw new \RuntimeException('Failed to encode write token payload.');
        }

        $signed = Craft::$app->getSecurity()->hashData($payloadJson, self::WRITE_TOKEN_PURPOSE);
        return $this->_b64urlEncode($signed);
    }

    /**
     * Validate an anonymous write token for a CID.
     *
     * @return array{ok: bool, error?: string}
     */
    public function validateWriteToken(string $cid, string $token): array
    {
        if (!$this->isValidCid($cid)) {
            return ['ok' => false, 'error' => 'invalid_cid'];
        }

        if ($token === '') {
            return ['ok' => false, 'error' => 'missing_write_token'];
        }

        $signed = $this->_b64urlDecode($token);
        if ($signed === null) {
            return ['ok' => false, 'error' => 'invalid_write_token'];
        }

        $payloadJson = Craft::$app->getSecurity()->validateData($signed, self::WRITE_TOKEN_PURPOSE);
        if ($payloadJson === false) {
            return ['ok' => false, 'error' => 'invalid_write_token'];
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload) || !isset($payload['cid'], $payload['sid'], $payload['exp'])) {
            return ['ok' => false, 'error' => 'invalid_write_token'];
        }

        if (!is_string($payload['cid']) || !is_string($payload['sid']) || !is_numeric($payload['exp'])) {
            return ['ok' => false, 'error' => 'invalid_write_token'];
        }

        if (!hash_equals($cid, $payload['cid'])) {
            return ['ok' => false, 'error' => 'invalid_write_token'];
        }

        $session = Craft::$app->getSession();
        if (!$session->getIsActive()) {
            $session->open();
        }

        $currentSessionId = (string)$session->getId();
        if ($currentSessionId === '' || !hash_equals($currentSessionId, $payload['sid'])) {
            return ['ok' => false, 'error' => 'invalid_write_token'];
        }

        if ((int)$payload['exp'] < time()) {
            return ['ok' => false, 'error' => 'expired_write_token'];
        }

        return ['ok' => true];
    }

    /**
     * Fetch a contest progress record by CID.
     *
     * @return array{ok: bool, record?: ContestProgressRecord, error?: string}
     */
    public function getProgress(string $cid): array
    {
        if (!$this->isValidCid($cid)) {
            return ['ok' => false, 'error' => 'invalid_cid'];
        }

        $record = ContestProgressRecord::findOne(['contest_id' => $cid]);

        if (!$record) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        return ['ok' => true, 'record' => $record];
    }

    /**
     * Upsert contest progress with optimistic concurrency control.
     *
     * @return array{ok: bool, revision?: int, serverUpdatedAt?: string, error?: string, serverRevision?: int, serverPayload?: array}
     */
    public function upsertProgress(string $cid, array $payload, int $clientRevision): array
    {
        if (!$this->isValidCid($cid)) {
            return ['ok' => false, 'error' => 'invalid_cid'];
        }

        $validationError = $this->validatePayload($payload);
        if ($validationError) {
            return ['ok' => false, 'error' => $validationError];
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($payloadJson === false) {
            return ['ok' => false, 'error' => 'payload_encoding_failed'];
        }

        if (strlen($payloadJson) > self::MAX_PAYLOAD_SIZE) {
            return ['ok' => false, 'error' => 'payload_too_large'];
        }

        $payloadHash = $this->computeHash($payloadJson);
        $now = gmdate('Y-m-d H:i:s');

        $record = ContestProgressRecord::findOne(['contest_id' => $cid]);

        if (!$record) {
            // clientRevision must be 0 when creating a new record.
            if ($clientRevision !== 0) {
                return [
                    'ok' => false,
                    'error' => 'conflict',
                    'serverRevision' => 0,
                    'serverPayload' => null,
                    'serverUpdatedAt' => null,
                ];
            }

            // Create new record
            $record = new ContestProgressRecord();
            $record->contest_id = $cid;
            $record->payload_json = $payloadJson;
            $record->payload_hash = $payloadHash;
            $record->revision = 1;
            $record->updated_at = $now;
            $record->created_at = $now;

            if (!$record->save(false)) {
                return ['ok' => false, 'error' => 'save_failed'];
            }

            return [
                'ok' => true,
                'revision' => 1,
                'serverUpdatedAt' => $now,
            ];
        }

        // Existing record — check revision for optimistic concurrency
        if ($record->revision !== $clientRevision) {
            return [
                'ok' => false,
                'error' => 'conflict',
                'serverRevision' => (int)$record->revision,
                'serverPayload' => json_decode($record->payload_json, true),
                'serverUpdatedAt' => $record->updated_at,
            ];
        }

        // Same hash means no real change
        if ($record->payload_hash === $payloadHash) {
            return [
                'ok' => true,
                'revision' => (int)$record->revision,
                'serverUpdatedAt' => $record->updated_at,
            ];
        }

        // Update atomically with revision check
        $newRevision = $record->revision + 1;
        $rowsAffected = Craft::$app->getDb()->createCommand()
            ->update(
                '{{%stamppassport_contest_progress}}',
                [
                    'payload_json' => $payloadJson,
                    'payload_hash' => $payloadHash,
                    'revision' => $newRevision,
                    'updated_at' => $now,
                ],
                [
                    'contest_id' => $cid,
                    'revision' => $clientRevision,
                ]
            )
            ->execute();

        if ($rowsAffected === 0) {
            // Race condition — another write happened between our read and write
            $record->refresh();
            return [
                'ok' => false,
                'error' => 'conflict',
                'serverRevision' => (int)$record->revision,
                'serverPayload' => json_decode($record->payload_json, true),
                'serverUpdatedAt' => $record->updated_at,
            ];
        }

        return [
            'ok' => true,
            'revision' => $newRevision,
            'serverUpdatedAt' => $now,
        ];
    }

    /**
     * Aggregate contest progress stats for the dashboard.
     *
     * Only `payload_json` and `updated_at` are fetched; other columns are not
     * needed. For very large installs (tens of thousands of rows) consider adding
     * a denormalised `step_count` column so totals can be SUM()ed in SQL.
     *
     * @param string $dateFrom  Optional start date, format Y-m-d
     * @param string $dateTo    Optional end date, format Y-m-d
     * @param int    $drawThreshold    Stamps required for draw eligibility
     * @param int    $totalItemsCount  Total enabled items (for sticker eligibility)
     * @return array{
     *   totalVisitors: int,
     *   totalScans: int,
     *   qualifyDraw: int,
     *   qualifyStickers: int,
     *   weekdayCounts: array,
     *   last7Days: array,
     *   last30Days: array,
     *   locationCounts: array,
     *   locationWeekdays: array,
     *   locationLast7: array,
     *   locationLast30: array,
     *   endDate: \DateTime,
     *   last7Start: string,
     *   last30Start: string,
     * }
     */
    public function getStats(
        string $dateFrom,
        string $dateTo,
        int $drawThreshold,
        int $totalItemsCount
    ): array {
        // totalVisitors is a pure SQL count — no PHP memory needed for that number.
        $countQuery = (new Query())->from('{{%stamppassport_contest_progress}}');
        if ($dateFrom !== '') {
            $countQuery->andWhere(['>=', 'updated_at', $dateFrom . ' 00:00:00']);
        }
        if ($dateTo !== '') {
            $countQuery->andWhere(['<=', 'updated_at', $dateTo . ' 23:59:59']);
        }
        $totalVisitors = (int)$countQuery->count();

        // Fetch only the two columns we actually process.
        $rows = (clone $countQuery)
            ->select(['payload_json', 'updated_at'])
            ->all();

        $endDate     = $dateTo !== '' ? new \DateTime($dateTo) : new \DateTime('today');
        $last7Start  = (clone $endDate)->modify('-6 days')->format('Y-m-d');
        $last30Start = (clone $endDate)->modify('-29 days')->format('Y-m-d');

        $last7Days  = [];
        $last30Days = [];
        $d = new \DateTime($last7Start);
        while ($d->format('Y-m-d') <= $endDate->format('Y-m-d')) {
            $last7Days[$d->format('Y-m-d')] = 0;
            $d->modify('+1 day');
        }
        $d = new \DateTime($last30Start);
        while ($d->format('Y-m-d') <= $endDate->format('Y-m-d')) {
            $last30Days[$d->format('Y-m-d')] = 0;
            $d->modify('+1 day');
        }

        $weekdayKeys   = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $weekdayCounts = array_fill_keys($weekdayKeys, 0);

        $locationCounts   = [];
        $locationWeekdays = [];
        $locationLast7    = [];
        $locationLast30   = [];

        $totalScans      = 0;
        $qualifyDraw     = 0;
        $qualifyStickers = 0;

        foreach ($rows as $row) {
            $payload = json_decode((string)$row['payload_json'], true);
            $steps   = $payload['progress']['stepsCompleted'] ?? [];
            if (!is_array($steps)) {
                $steps = [];
            }

            $stepCount    = count($steps);
            $totalScans  += $stepCount;

            if ($stepCount >= $drawThreshold) {
                $qualifyDraw++;
            }
            if ($totalItemsCount > 0 && $stepCount >= $totalItemsCount) {
                $qualifyStickers++;
            }

            $ts      = strtotime((string)$row['updated_at']);
            $date    = date('Y-m-d', $ts);
            $weekday = date('l', $ts);

            if (isset($weekdayCounts[$weekday])) {
                $weekdayCounts[$weekday]++;
            }
            if (isset($last7Days[$date])) {
                $last7Days[$date]++;
            }
            if (isset($last30Days[$date])) {
                $last30Days[$date]++;
            }

            foreach ($steps as $step) {
                $code = (string)$step;
                if ($code === '') {
                    continue;
                }
                $locationCounts[$code] = ($locationCounts[$code] ?? 0) + 1;
                $locationWeekdays[$code][$weekday] = ($locationWeekdays[$code][$weekday] ?? 0) + 1;
                if (isset($last7Days[$date])) {
                    $locationLast7[$code][$date] = ($locationLast7[$code][$date] ?? 0) + 1;
                }
                if (isset($last30Days[$date])) {
                    $locationLast30[$code][$date] = ($locationLast30[$code][$date] ?? 0) + 1;
                }
            }
        }

        arsort($locationCounts);

        return [
            'totalVisitors'    => $totalVisitors,
            'totalScans'       => $totalScans,
            'qualifyDraw'      => $qualifyDraw,
            'qualifyStickers'  => $qualifyStickers,
            'weekdayCounts'    => $weekdayCounts,
            'last7Days'        => $last7Days,
            'last30Days'       => $last30Days,
            'locationCounts'   => $locationCounts,
            'locationWeekdays' => $locationWeekdays,
            'locationLast7'    => $locationLast7,
            'locationLast30'   => $locationLast30,
            'endDate'          => $endDate,
            'last7Start'       => $last7Start,
            'last30Start'      => $last30Start,
        ];
    }

    private function _b64urlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function _b64urlDecode(string $value): ?string
    {
        $remainder = strlen($value) % 4;
        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }
}
