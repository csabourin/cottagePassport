<?php

namespace csabourin\stamppassport\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * @property int $id
 * @property int $itemId
 * @property int $siteId
 * @property string|null $title
 * @property string|null $description
 * @property string|null $linkUrl
 * @property string|null $linkText
 * @property ItemRecord $item
 */
class ItemContentRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%stamppassport_items_content}}';
    }

    public function getItem(): ActiveQueryInterface
    {
        return $this->hasOne(ItemRecord::class, ['id' => 'itemId']);
    }

    public function rules(): array
    {
        return [
            [['itemId', 'siteId'], 'required'],
            [['itemId', 'siteId'], 'integer'],
            [['title', 'linkText'], 'string', 'max' => 255],
            [['linkUrl'], 'string', 'max' => 500],
            [['description'], 'string'],
            [['itemId', 'siteId'], 'unique', 'targetAttribute' => ['itemId', 'siteId']],
        ];
    }
}
