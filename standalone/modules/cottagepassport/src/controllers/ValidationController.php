<?php

namespace modules\cottagepassport\src\controllers;

use Craft;
use craft\web\Controller;
use modules\cottagepassport\src\Module;
use yii\web\Response;

class ValidationController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    public function actionCheckin(): Response
    {
        $request = Craft::$app->request;
        $signedQr = (string)$request->getBodyParam('signedQr', '');
        $lat = (float)$request->getBodyParam('latitude', 0);
        $lng = (float)$request->getBodyParam('longitude', 0);

        $result = Module::validateSignedQr($signedQr, $lat, $lng);
        return $this->asJson($result);
    }
}
