<?php

namespace csabourin\cottagepassport\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * @property int $id
 * @property string $shortCode
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int|null $imageId
 * @property int $sortOrder
 * @property bool $enabled
 * @property ItemContentRecord[] $contents
 */
class ItemRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%cottagepassport_items}}';
    }

    public function getContents(): ActiveQueryInterface
    {
        return $this->hasMany(ItemContentRecord::class, ['itemId' => 'id']);
    }

    public function rules(): array
    {
        return [
            [['shortCode'], 'required'],
            [['shortCode'], 'string', 'max' => 12],
            [['shortCode'], 'unique'],
            [['latitude'], 'number', 'min' => -90, 'max' => 90],
            [['longitude'], 'number', 'min' => -180, 'max' => 180],
            [['imageId', 'sortOrder'], 'integer'],
            [['enabled'], 'boolean'],
        ];
    }
}
