<?php

namespace csabourin\stamppassport\migrations;

use craft\db\Migration;

class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->_createItemsTable();
        $this->_createItemsContentTable();
        $this->_createContestProgressTable();
        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%stamppassport_contest_progress}}');
        $this->dropTableIfExists('{{%stamppassport_items_content}}');
        $this->dropTableIfExists('{{%stamppassport_items}}');
        return true;
    }

    private function _createItemsTable(): void
    {
        $this->createTable('{{%stamppassport_items}}', [
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

        $this->createIndex(null, '{{%stamppassport_items}}', 'shortCode', true);
        $this->createIndex(null, '{{%stamppassport_items}}', 'sortOrder');
        $this->createIndex(null, '{{%stamppassport_items}}', 'enabled');

        $this->addForeignKey(
            null,
            '{{%stamppassport_items}}',
            'imageId',
            '{{%assets}}',
            'id',
            'SET NULL'
        );
    }

    private function _createItemsContentTable(): void
    {
        $this->createTable('{{%stamppassport_items_content}}', [
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

        $this->createIndex(null, '{{%stamppassport_items_content}}', ['itemId', 'siteId'], true);

        $this->addForeignKey(
            null,
            '{{%stamppassport_items_content}}',
            'itemId',
            '{{%stamppassport_items}}',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            '{{%stamppassport_items_content}}',
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE'
        );
    }

    private function _createContestProgressTable(): void
    {
        $this->createTable('{{%stamppassport_contest_progress}}', [
            'contest_id' => $this->char(36)->notNull(),
            'payload_json' => $this->text()->notNull(),
            'payload_hash' => $this->char(64)->notNull(),
            'revision' => $this->integer()->notNull()->defaultValue(0),
            'updated_at' => $this->dateTime()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ]);

        $this->addPrimaryKey(
            'pk_stamppassport_contest_progress',
            '{{%stamppassport_contest_progress}}',
            'contest_id'
        );

        $this->createIndex(
            'idx_stamppassport_contest_progress_updated_at',
            '{{%stamppassport_contest_progress}}',
            'updated_at'
        );
    }
}
