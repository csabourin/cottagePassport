<?php

namespace csabourin\stamppassport\services;

use Craft;
use craft\base\Component;
use csabourin\stamppassport\records\ContestProgressRecord;

class ContestProgress extends Component
{
    /** Maximum payload size in bytes */
    public const MAX_PAYLOAD_SIZE = 32768; // 32 KB

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

        $progress = $payload['progress'];

        if (!isset($progress['stepsCompleted']) || !is_array($progress['stepsCompleted'])) {
            return 'missing_or_invalid_steps_completed';
        }

        if (!isset($progress['score']) || !is_int($progress['score'])) {
            return 'missing_or_invalid_score';
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

        if (strlen($payloadJson) > self::MAX_PAYLOAD_SIZE) {
            return ['ok' => false, 'error' => 'payload_too_large'];
        }

        $payloadHash = $this->computeHash($payloadJson);
        $now = gmdate('Y-m-d H:i:s');

        $record = ContestProgressRecord::findOne(['contest_id' => $cid]);

        if (!$record) {
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
}
