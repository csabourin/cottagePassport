<?php

namespace csabourin\stamppassport\records;

use craft\db\ActiveRecord;

/**
 * One row per prize draw. Stores the seed and an ordered snapshot of the eligible
 * pool so the result can be re-verified deterministically.
 *
 * dateCreated / dateUpdated / uid are managed automatically by craft\db\ActiveRecord.
 *
 * @property int $id
 * @property string $formHandle
 * @property string $weightingMode
 * @property int $drawThreshold
 * @property string|null $dateFrom
 * @property string|null $dateTo
 * @property string $seed
 * @property int $eligibleCount
 * @property int $totalBallots
 * @property string|null $winnerCid
 * @property int|null $winnerSubmissionId
 * @property string|null $poolSnapshotJson
 * @property int|null $drawnByUserId
 * @property string $dateDrawn
 */
class DrawResultRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%stamppassport_draw_results}}';
    }
}
