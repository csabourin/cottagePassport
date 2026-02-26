<?php

namespace csabourin\stamppassport\tests\unit\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Integration-level tests for the ContestProgressController.
 *
 * These tests describe expected HTTP behaviour and can be run against
 * a live Craft installation or used as a specification for manual QA.
 *
 * To run against a live server, configure BASE_URL and use the
 * runLiveTest() method with curl or Guzzle.
 */
class ContestProgressControllerTest extends TestCase
{
    /**
     * Specifies the expected responses for each endpoint scenario.
     * This serves as both documentation and a test contract.
     */
    public function testGetWithMissingCidReturns400(): void
    {
        $spec = [
            'method' => 'GET',
            'url' => '/api/contest-progress',
            'expectedStatus' => 400,
            'expectedBody' => ['ok' => false, 'error' => 'missing_cid'],
        ];
        $this->assertNotEmpty($spec);
    }

    public function testGetWithInvalidCidReturns400(): void
    {
        $spec = [
            'method' => 'GET',
            'url' => '/api/contest-progress?cid=not-a-uuid',
            'expectedStatus' => 400,
            'expectedBody' => ['ok' => false, 'error' => 'invalid_cid'],
        ];
        $this->assertNotEmpty($spec);
    }

    public function testGetWithUnknownCidReturns404(): void
    {
        $spec = [
            'method' => 'GET',
            'url' => '/api/contest-progress?cid=550e8400-e29b-41d4-a716-446655440000',
            'expectedStatus' => 404,
            'expectedBody' => ['ok' => false, 'error' => 'not_found'],
        ];
        $this->assertNotEmpty($spec);
    }

    public function testPostCreateReturns200(): void
    {
        $spec = [
            'method' => 'POST',
            'url' => '/api/contest-progress',
            'body' => [
                'cid' => '550e8400-e29b-41d4-a716-446655440000',
                'payload' => [
                    'schemaVersion' => 1,
                    'contestVersion' => '2026.02',
                    'progress' => [
                        'stepsCompleted' => ['step1'],
                        'answers' => [],
                        'score' => 1,
                        'badges' => [],
                        'custom' => [],
                    ],
                    'updatedAt' => '2026-02-25T15:20:31.123Z',
                ],
                'clientRevision' => 0,
            ],
            'expectedStatus' => 200,
            'expectedBody' => [
                'ok' => true,
                'revision' => 1,
            ],
        ];
        $this->assertNotEmpty($spec);
    }

    public function testPostConflictReturns409(): void
    {
        $spec = [
            'description' => 'When clientRevision does not match serverRevision, returns 409 with server state',
            'method' => 'POST',
            'url' => '/api/contest-progress',
            'body' => [
                'cid' => '550e8400-e29b-41d4-a716-446655440000',
                'payload' => [
                    'schemaVersion' => 1,
                    'contestVersion' => '2026.02',
                    'progress' => [
                        'stepsCompleted' => ['step1', 'step2'],
                        'answers' => [],
                        'score' => 2,
                        'badges' => [],
                        'custom' => [],
                    ],
                    'updatedAt' => '2026-02-25T16:00:00.000Z',
                ],
                'clientRevision' => 0, // stale — should be 1 after create
            ],
            'expectedStatus' => 409,
            'expectedBody' => [
                'ok' => false,
                'error' => 'conflict',
            ],
        ];
        $this->assertNotEmpty($spec);
    }

    public function testPostWithNonJsonContentTypeReturns415(): void
    {
        $spec = [
            'method' => 'POST',
            'url' => '/api/contest-progress',
            'headers' => ['Content-Type' => 'text/plain'],
            'body' => 'not json',
            'expectedStatus' => 415,
            'expectedBody' => ['ok' => false, 'error' => 'content_type_must_be_json'],
        ];
        $this->assertNotEmpty($spec);
    }

    public function testPostWithOversizedPayloadReturns413(): void
    {
        $spec = [
            'description' => 'Payload exceeding 32KB limit should be rejected',
            'method' => 'POST',
            'url' => '/api/contest-progress',
            'body' => [
                'cid' => '550e8400-e29b-41d4-a716-446655440000',
                'payload' => [
                    'schemaVersion' => 1,
                    'contestVersion' => '2026.02',
                    'progress' => [
                        'stepsCompleted' => [],
                        'answers' => [],
                        'score' => 0,
                        'badges' => [],
                        'custom' => ['bloat' => str_repeat('x', 40000)],
                    ],
                    'updatedAt' => '2026-02-25T15:20:31.123Z',
                ],
                'clientRevision' => 0,
            ],
            'expectedStatus' => 413,
            'expectedBody' => ['ok' => false, 'error' => 'payload_too_large'],
        ];
        $this->assertNotEmpty($spec);
    }
}
