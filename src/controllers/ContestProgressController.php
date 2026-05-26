<?php

namespace csabourin\stamppassport\controllers;

use Craft;
use craft\web\Controller;
use csabourin\stamppassport\Plugin;
use yii\web\Response;

/**
 * Public JSON API for cross-domain contest progress sync.
 *
 * Routes:
 *   GET  api/contest-progress?cid=<uuid>
 *   POST api/contest-progress
 */
class ContestProgressController extends Controller
{
    private const WRITE_RATE_LIMIT_WINDOW_SECONDS = 60;
    private const WRITE_RATE_LIMIT_PER_CID_MAX = 40;
    private const WRITE_RATE_LIMIT_PER_IP_MAX = 120;

    protected array|bool|int $allowAnonymous = true;

    public function beforeAction($action): bool
    {
        $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    /**
     * Single endpoint that dispatches by HTTP method.
     * Registered via site URL rule: api/contest-progress → this action.
     */
    public function actionIndex(): Response
    {
        $request = Craft::$app->getRequest();

        if ($request->getIsPost()) {
            return $this->_handlePost($request);
        }

        return $this->_handleGet($request);
    }

    /**
     * GET api/contest-progress?cid=<uuid>
     */
    private function _handleGet(\craft\web\Request $request): Response
    {
        $cid = $request->getQueryParam('cid', '');

        if (!$cid) {
            $this->response->setStatusCode(400);
            return $this->asJson(['ok' => false, 'error' => 'missing_cid']);
        }

        $service = Plugin::$plugin->contestProgress;

        if (!$service->isValidCid($cid)) {
            $this->response->setStatusCode(400);
            return $this->asJson(['ok' => false, 'error' => 'invalid_cid']);
        }

        $writeToken = $service->issueWriteToken($cid);

        $result = $service->getProgress($cid);

        if (!$result['ok']) {
            $this->response->setStatusCode(404);
            return $this->asJson([
                'ok' => false,
                'error' => $result['error'],
                'writeToken' => $writeToken,
            ]);
        }

        $record = $result['record'];

        return $this->asJson([
            'ok' => true,
            'cid' => $record->contest_id,
            'revision' => (int)$record->revision,
            'payload' => json_decode($record->payload_json, true),
            'serverUpdatedAt' => $record->dateUpdated,
            'writeToken' => $writeToken,
        ]);
    }

    /**
     * POST api/contest-progress
     * Body: { cid, payload, clientRevision, writeToken }
     */
    private function _handlePost(\craft\web\Request $request): Response
    {
        $contentType = (string)$request->getContentType();
        if (stripos($contentType, 'application/json') === false) {
            $this->response->setStatusCode(415);
            return $this->asJson(['ok' => false, 'error' => 'content_type_must_be_json']);
        }

        $body = json_decode($request->getRawBody(), true);

        if (!is_array($body)) {
            $this->response->setStatusCode(400);
            return $this->asJson(['ok' => false, 'error' => 'invalid_json']);
        }

        $cid = $body['cid'] ?? '';
        $payload = $body['payload'] ?? null;
        $clientRevision = $body['clientRevision'] ?? null;
        $writeToken = $body['writeToken'] ?? '';

        if (!$cid || !is_string($cid)) {
            $this->response->setStatusCode(400);
            return $this->asJson(['ok' => false, 'error' => 'missing_cid']);
        }

        if (!is_array($payload)) {
            $this->response->setStatusCode(400);
            return $this->asJson(['ok' => false, 'error' => 'missing_payload']);
        }

        if (!is_int($clientRevision) && !is_numeric($clientRevision)) {
            $this->response->setStatusCode(400);
            return $this->asJson(['ok' => false, 'error' => 'missing_client_revision']);
        }

        if (!is_string($writeToken) || $writeToken === '') {
            $this->response->setStatusCode(401);
            return $this->asJson(['ok' => false, 'error' => 'missing_write_token']);
        }

        $service = Plugin::$plugin->contestProgress;

        if (!$service->isValidCid($cid)) {
            $this->response->setStatusCode(400);
            return $this->asJson(['ok' => false, 'error' => 'invalid_cid']);
        }

        if (!$this->_enforceWriteRateLimit($cid)) {
            return $this->asJson(['ok' => false, 'error' => 'rate_limited']);
        }

        $writeTokenResult = $service->validateWriteToken($cid, $writeToken);
        if (!$writeTokenResult['ok']) {
            $this->response->setStatusCode(401);
            return $this->asJson([
                'ok' => false,
                'error' => $writeTokenResult['error'],
                'writeToken' => $service->issueWriteToken($cid),
            ]);
        }

        $result = $service->upsertProgress($cid, $payload, (int)$clientRevision);

        if (!$result['ok']) {
            if ($result['error'] === 'conflict') {
                $this->response->setStatusCode(409);
                return $this->asJson([
                    'ok' => false,
                    'error' => 'conflict',
                    'serverRevision' => $result['serverRevision'],
                    'serverPayload' => $result['serverPayload'],
                    'serverUpdatedAt' => $result['serverUpdatedAt'],
                    'writeToken' => $service->issueWriteToken($cid),
                ]);
            }

            if ($result['error'] === 'payload_too_large') {
                $this->response->setStatusCode(413);
            } else {
                $this->response->setStatusCode(400);
            }

            return $this->asJson([
                'ok' => false,
                'error' => $result['error'],
                'writeToken' => $service->issueWriteToken($cid),
            ]);
        }

        return $this->asJson([
            'ok' => true,
            'cid' => $cid,
            'revision' => $result['revision'],
            'serverUpdatedAt' => $result['serverUpdatedAt'],
            'writeToken' => $service->issueWriteToken($cid),
        ]);
    }

    private function _enforceWriteRateLimit(string $cid): bool
    {
        $request = Craft::$app->getRequest();
        $ip = $request->getUserIP() ?? 'unknown';
        $bucket = (int)floor(time() / self::WRITE_RATE_LIMIT_WINDOW_SECONDS);

        $cache = Craft::$app->getCache();
        $ttl = self::WRITE_RATE_LIMIT_WINDOW_SECONDS + 5;

        $perCidKey = 'stamp-passport:contest-progress:write-rate:cid:' . sha1($ip . '|' . $cid . '|' . $bucket);
        $perIpKey  = 'stamp-passport:contest-progress:write-rate:ip:'  . sha1($ip . '|' . $bucket);

        // add() is atomic — it only writes when the key is absent, so the first
        // request in the window always lands at exactly 1. Subsequent increments
        // use get+set which is not atomic, but that is only a concern under very
        // high concurrency and results in under-counting (lenient), not bypass.
        $perCidCount = $cache->add($perCidKey, 1, $ttl) ? 1 : ((int)$cache->get($perCidKey) + 1);
        if ($perCidCount > 1) {
            $cache->set($perCidKey, $perCidCount, $ttl);
        }

        $perIpCount = $cache->add($perIpKey, 1, $ttl) ? 1 : ((int)$cache->get($perIpKey) + 1);
        if ($perIpCount > 1) {
            $cache->set($perIpKey, $perIpCount, $ttl);
        }

        if ($perCidCount > self::WRITE_RATE_LIMIT_PER_CID_MAX || $perIpCount > self::WRITE_RATE_LIMIT_PER_IP_MAX) {
            $this->response->setStatusCode(429);
            $this->response->getHeaders()->set('Retry-After', (string)self::WRITE_RATE_LIMIT_WINDOW_SECONDS);
            return false;
        }

        return true;
    }
}
