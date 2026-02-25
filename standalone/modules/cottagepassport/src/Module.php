<?php

namespace modules\cottagepassport\src;

use Craft;
use yii\base\Module as BaseModule;

class Module extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/cottagepassport', __DIR__);
        parent::init();
    }

    public static function validateSignedQr(string $signedQr, float $latitude, float $longitude): array
    {
        $parts = explode('.', $signedQr);
        if (count($parts) !== 2) {
            return ['ok' => false, 'reason' => 'Malformed QR signature format.'];
        }

        [$payloadB64, $signature] = $parts;
        $payloadJson = base64_decode(strtr($payloadB64, '-_', '+/'), true);
        if (!$payloadJson) {
            return ['ok' => false, 'reason' => 'QR payload decode failed.'];
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload) || empty($payload['uuid']) || !preg_match('/^[a-f0-9]{8}$/i', $payload['uuid'])) {
            return ['ok' => false, 'reason' => 'QR payload is missing a valid UUID.'];
        }

        $secret = getenv('COTTAGE_QR_SECRET') ?: '';
        if ($secret === '') {
            return ['ok' => false, 'reason' => 'Server secret is not configured.'];
        }

        $expected = hash_hmac('sha256', $payloadB64, $secret);
        if (!hash_equals($expected, $signature)) {
            return ['ok' => false, 'reason' => 'Signature mismatch.'];
        }

        $allowlist = require Craft::getAlias('@root/config/valid-qr-uuids.php');
        if (!in_array(strtolower($payload['uuid']), array_map('strtolower', $allowlist), true)) {
            return ['ok' => false, 'reason' => 'UUID is not allowlisted.'];
        }

        if (!isset($payload['lat'], $payload['lng'])) {
            return ['ok' => false, 'reason' => 'QR payload missing location anchor.'];
        }

        $distanceMeters = self::haversineMeters((float)$latitude, (float)$longitude, (float)$payload['lat'], (float)$payload['lng']);
        if ($distanceMeters > 500) {
            return ['ok' => false, 'reason' => 'Outside 500m geofence.', 'distanceMeters' => round($distanceMeters, 2)];
        }

        return ['ok' => true, 'uuid' => $payload['uuid'], 'distanceMeters' => round($distanceMeters, 2)];
    }

    private static function haversineMeters(float $latA, float $lngA, float $latB, float $lngB): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($latB - $latA);
        $dLng = deg2rad($lngB - $lngA);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($latA)) * cos(deg2rad($latB)) * sin($dLng / 2) ** 2;
        return 2 * $earthRadius * asin(sqrt($a));
    }
}
