<?php

namespace csabourin\stamppassport\records;

use craft\db\ActiveRecord;

/**
 * @property string $contest_id
 * @property string $payload_json
 * @property string $payload_hash
 * @property int $revision
 * @property string $dateUpdated
 * @property string $dateCreated
 * @property string $uid
 */
class ContestProgressRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%stamppassport_contest_progress}}';
    }

    public static function primaryKey(): array
    {
        return ['contest_id'];
    }

    public function behaviors(): array
    {
        // Disable Craft's TimestampBehavior — timestamps are managed manually
        // because the optimistic-concurrency update path uses a raw DB command
        // that bypasses ActiveRecord behaviours entirely.
        return [];
    }

    public function rules(): array
    {
        return [
            [['contest_id', 'payload_json', 'payload_hash'], 'required'],
            [['contest_id'], 'string', 'max' => 36],
            [['contest_id'], 'match', 'pattern' => '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i'],
            [['payload_hash'], 'string', 'max' => 64],
            [['revision'], 'integer', 'min' => 0],
            [['uid'], 'string', 'max' => 36],
        ];
    }
}
