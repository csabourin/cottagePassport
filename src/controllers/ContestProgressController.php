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

        $result = $service->getProgress($cid);

        if (!$result['ok']) {
            $this->response->setStatusCode(404);
            return $this->asJson(['ok' => false, 'error' => $result['error']]);
        }

        $record = $result['record'];

        return $this->asJson([
            'ok' => true,
            'cid' => $record->contest_id,
            'revision' => (int)$record->revision,
            'payload' => json_decode($record->payload_json, true),
            'serverUpdatedAt' => $record->updated_at,
        ]);
    }

    /**
     * POST api/contest-progress
     * Body: { cid, payload, clientRevision }
     */
    private function _handlePost(\craft\web\Request $request): Response
    {
        $contentType = $request->getContentType();
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

        $service = Plugin::$plugin->contestProgress;

        if (!$service->isValidCid($cid)) {
            $this->response->setStatusCode(400);
            return $this->asJson(['ok' => false, 'error' => 'invalid_cid']);
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
                ]);
            }

            if ($result['error'] === 'payload_too_large') {
                $this->response->setStatusCode(413);
            } else {
                $this->response->setStatusCode(400);
            }

            return $this->asJson(['ok' => false, 'error' => $result['error']]);
        }

        return $this->asJson([
            'ok' => true,
            'cid' => $cid,
            'revision' => $result['revision'],
            'serverUpdatedAt' => $result['serverUpdatedAt'],
        ]);
    }
}
