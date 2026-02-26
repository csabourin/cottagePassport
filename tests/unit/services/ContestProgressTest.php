<?php

namespace csabourin\stamppassport\tests\unit\services;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ContestProgress service.
 *
 * These tests exercise validation logic and payload handling without
 * requiring a full Craft CMS bootstrap. Database-dependent tests
 * (upsert, conflict) are in the integration section below and
 * require a configured Craft test environment.
 */
class ContestProgressTest extends TestCase
{
    private \csabourin\stamppassport\services\ContestProgress $service;

    protected function setUp(): void
    {
        $this->service = new \csabourin\stamppassport\services\ContestProgress();
    }

    /* ─── CID Validation ─── */

    public function testValidUuidV4Accepted(): void
    {
        $this->assertTrue($this->service->isValidCid('550e8400-e29b-41d4-a716-446655440000'));
        $this->assertTrue($this->service->isValidCid('6ba7b810-9dad-41d0-80b4-00c04fd430c8'));
    }

    public function testInvalidCidFormats(): void
    {
        $this->assertFalse($this->service->isValidCid(''));
        $this->assertFalse($this->service->isValidCid('not-a-uuid'));
        $this->assertFalse($this->service->isValidCid('550e8400-e29b-31d4-a716-446655440000')); // v3, not v4
        $this->assertFalse($this->service->isValidCid('550e8400-e29b-41d4-c716-446655440000')); // bad variant
        $this->assertFalse($this->service->isValidCid('<script>alert(1)</script>'));
        $this->assertFalse($this->service->isValidCid(str_repeat('a', 100)));
    }

    /* ─── Payload Validation ─── */

    public function testValidPayloadAccepted(): void
    {
        $payload = $this->_validPayload();
        $this->assertNull($this->service->validatePayload($payload));
    }

    public function testMissingSchemaVersionRejected(): void
    {
        $payload = $this->_validPayload();
        unset($payload['schemaVersion']);
        $this->assertSame('missing_or_invalid_schema_version', $this->service->validatePayload($payload));
    }

    public function testUnsupportedSchemaVersionRejected(): void
    {
        $payload = $this->_validPayload();
        $payload['schemaVersion'] = 99;
        $this->assertSame('unsupported_schema_version', $this->service->validatePayload($payload));
    }

    public function testMissingContestVersionRejected(): void
    {
        $payload = $this->_validPayload();
        unset($payload['contestVersion']);
        $this->assertSame('missing_or_invalid_contest_version', $this->service->validatePayload($payload));
    }

    public function testMissingProgressRejected(): void
    {
        $payload = $this->_validPayload();
        unset($payload['progress']);
        $this->assertSame('missing_or_invalid_progress', $this->service->validatePayload($payload));
    }

    public function testMissingUpdatedAtRejected(): void
    {
        $payload = $this->_validPayload();
        unset($payload['updatedAt']);
        $this->assertSame('missing_or_invalid_updated_at', $this->service->validatePayload($payload));
    }

    public function testMissingStepsCompletedRejected(): void
    {
        $payload = $this->_validPayload();
        unset($payload['progress']['stepsCompleted']);
        $this->assertSame('missing_or_invalid_steps_completed', $this->service->validatePayload($payload));
    }

    public function testMissingScoreRejected(): void
    {
        $payload = $this->_validPayload();
        unset($payload['progress']['score']);
        $this->assertSame('missing_or_invalid_score', $this->service->validatePayload($payload));
    }

    /* ─── Hashing ─── */

    public function testHashIsDeterministic(): void
    {
        $json = '{"test":true}';
        $hash1 = $this->service->computeHash($json);
        $hash2 = $this->service->computeHash($json);
        $this->assertSame($hash1, $hash2);
        $this->assertSame(64, strlen($hash1));
    }

    public function testDifferentPayloadsProduceDifferentHashes(): void
    {
        $hash1 = $this->service->computeHash('{"a":1}');
        $hash2 = $this->service->computeHash('{"a":2}');
        $this->assertNotSame($hash1, $hash2);
    }

    /* ─── Payload Size ─── */

    public function testMaxPayloadSizeConstant(): void
    {
        $this->assertSame(32768, \csabourin\stamppassport\services\ContestProgress::MAX_PAYLOAD_SIZE);
    }

    /* ─── Helper ─── */

    private function _validPayload(): array
    {
        return [
            'schemaVersion' => 1,
            'contestVersion' => '2026.02',
            'progress' => [
                'stepsCompleted' => ['step1', 'step3'],
                'answers' => ['q1' => 'A'],
                'score' => 2,
                'badges' => [],
                'custom' => [],
            ],
            'updatedAt' => '2026-02-25T15:20:31.123Z',
        ];
    }
}
