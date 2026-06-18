<?php

namespace csabourin\stamppassport\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use csabourin\stamppassport\Plugin;

/**
 * Sticker availability service.
 *
 * Stickers are a finite prize: once `maxStickers` request forms have been
 * submitted, the sticker modal shows a custom sold-out message instead of the
 * form. The submission count comes from Freeform and is fully guarded so a
 * missing or incompatible Freeform degrades to "not sold out" (fail-open)
 * rather than crashing or wrongly blocking participants.
 */
class Stickers extends Component
{
    /** Freeform Submission element class — referenced by name so it stays optional. */
    private const FREEFORM_SUBMISSION_CLASS = 'Solspace\\Freeform\\Elements\\Submission';

    /**
     * Number of (non-spam) sticker request submissions recorded for the
     * configured form.
     *
     * Returns null when the count cannot be determined (Freeform absent, handle
     * unresolved, or read failure) so callers can distinguish "zero claimed"
     * from "unknown".
     */
    public function getClaimedCount(): ?int
    {
        $handle = trim((string)(Plugin::$plugin->getSettings()->freeformStickerFormHandle ?? ''));
        if ($handle === '') {
            return null;
        }

        $formId = $this->resolveFormId($handle);
        if ($formId === null) {
            return null;
        }

        $class = self::FREEFORM_SUBMISSION_CLASS;
        if (!class_exists($class)) {
            return null;
        }

        try {
            return (int)$class::find()
                ->formId($formId)
                ->isSpam(0)
                ->count();
        } catch (\Throwable $e) {
            Craft::warning(
                'Stamp Passport: unable to count Freeform sticker submissions: ' . $e->getMessage(),
                __METHOD__
            );
            return null;
        }
    }

    /**
     * Whether all available stickers have been claimed.
     *
     * Fail-open: when the max is non-positive or the claimed count is unknown,
     * returns false so the request form stays available.
     */
    public function isSoldOut(): bool
    {
        $max = (int)(Plugin::$plugin->getSettings()->maxStickers ?? 0);
        if ($max <= 0) {
            return false;
        }

        $claimed = $this->getClaimedCount();
        if ($claimed === null) {
            return false;
        }

        return $claimed >= $max;
    }

    /**
     * Resolve a Freeform form handle to its numeric id via the freeform_forms
     * table (version-tolerant; mirrors how the settings dropdown is built).
     */
    private function resolveFormId(string $handle): ?int
    {
        if (Craft::$app->getPlugins()->getPlugin('freeform') === null) {
            return null;
        }

        try {
            $row = (new Query())
                ->select(['id'])
                ->from('{{%freeform_forms}}')
                ->where(['handle' => $handle])
                ->one();

            return ($row && !empty($row['id'])) ? (int)$row['id'] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
