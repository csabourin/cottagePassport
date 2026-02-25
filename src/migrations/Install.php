<?php

namespace csabourin\cottagepassport\migrations;

use craft\db\Migration;

class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->_createItemsTable();
        $this->_createItemsContentTable();
        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%cottagepassport_items_content}}');
        $this->dropTableIfExists('{{%cottagepassport_items}}');
        return true;
    }

    private function _createItemsTable(): void
    {
        $this->createTable('{{%cottagepassport_items}}', [
            'id' => $this->primaryKey(),
            'shortCode' => $this->string(12)->notNull(),
            'latitude' => $this->decimal(10, 7)->null(),
            'longitude' => $this->decimal(10, 7)->null(),
            'imageId' => $this->integer()->null(),
            'sortOrder' => $this->integer()->notNull()->defaultValue(0),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, '{{%cottagepassport_items}}', 'shortCode', true);
        $this->createIndex(null, '{{%cottagepassport_items}}', 'sortOrder');
        $this->createIndex(null, '{{%cottagepassport_items}}', 'enabled');

        $this->addForeignKey(
            null,
            '{{%cottagepassport_items}}',
            'imageId',
            '{{%assets}}',
            'id',
            'SET NULL'
        );
    }

    private function _createItemsContentTable(): void
    {
        $this->createTable('{{%cottagepassport_items_content}}', [
            'id' => $this->primaryKey(),
            'itemId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'title' => $this->string(255)->null(),
            'description' => $this->text()->null(),
            'linkUrl' => $this->string(500)->null(),
            'linkText' => $this->string(255)->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, '{{%cottagepassport_items_content}}', ['itemId', 'siteId'], true);

        $this->addForeignKey(
            null,
            '{{%cottagepassport_items_content}}',
            'itemId',
            '{{%cottagepassport_items}}',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            '{{%cottagepassport_items_content}}',
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE'
        );
    }
}
