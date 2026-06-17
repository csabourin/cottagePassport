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
        $this->_createDrawResultsTable();
        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%stamppassport_draw_results}}');
        $this->dropTableIfExists('{{%stamppassport_contest_progress}}');
        $this->dropTableIfExists('{{%stamppassport_items_content}}');
        $this->dropTableIfExists('{{%stamppassport_items}}');
        return true;
    }

    private function _createItemsTable(): void
    {
        if ($this->db->tableExists('{{%stamppassport_items}}')) {
            return;
        }

        $this->createTable('{{%stamppassport_items}}', [
            'id' => $this->primaryKey(),
            'shortCode' => $this->string(12)->notNull(),
            'latitude' => $this->decimal(10, 7)->null(),
            'longitude' => $this->decimal(10, 7)->null(),
            'imageId' => $this->integer()->null(),
            'qrCenterImageAssetId' => $this->integer()->null(),
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

        $this->addForeignKey(
            null,
            '{{%stamppassport_items}}',
            'qrCenterImageAssetId',
            '{{%assets}}',
            'id',
            'SET NULL'
        );
    }

    private function _createItemsContentTable(): void
    {
        if ($this->db->tableExists('{{%stamppassport_items_content}}')) {
            return;
        }

        $this->createTable('{{%stamppassport_items_content}}', [
            'id' => $this->primaryKey(),
            'itemId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'title' => $this->string(255)->null(),
            'description' => $this->text()->null(),
            'linkUrl' => $this->string(500)->null(),
            'linkEntryId' => $this->integer()->null(),
            'linkText' => $this->string(255)->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, '{{%stamppassport_items_content}}', ['itemId', 'siteId'], true);
        $this->createIndex(null, '{{%stamppassport_items_content}}', 'linkEntryId');

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

        $this->addForeignKey(
            null,
            '{{%stamppassport_items_content}}',
            'linkEntryId',
            '{{%entries}}',
            'id',
            'SET NULL'
        );
    }

    private function _createContestProgressTable(): void
    {
        if ($this->db->tableExists('{{%stamppassport_contest_progress}}')) {
            return;
        }

        $this->createTable('{{%stamppassport_contest_progress}}', [
            'contest_id' => $this->char(36)->notNull(),
            'payload_json' => $this->text()->notNull(),
            'payload_hash' => $this->char(64)->notNull(),
            'revision' => $this->integer()->notNull()->defaultValue(1),
            'dateUpdated' => $this->dateTime()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'uid' => $this->uid()->notNull(),
        ]);

        $this->addPrimaryKey(
            'pk_stamppassport_contest_progress',
            '{{%stamppassport_contest_progress}}',
            'contest_id'
        );

        $this->createIndex(
            'idx_stamppassport_contest_progress_dateUpdated',
            '{{%stamppassport_contest_progress}}',
            'dateUpdated'
        );
    }

    private function _createDrawResultsTable(): void
    {
        $table = '{{%stamppassport_draw_results}}';

        if ($this->db->tableExists($table)) {
            return;
        }

        $this->createTable($table, [
            'id' => $this->primaryKey(),
            'formHandle' => $this->string(100)->notNull(),
            'weightingMode' => $this->string(20)->notNull()->defaultValue('total'),
            'drawThreshold' => $this->integer()->notNull()->defaultValue(0),
            'dateFrom' => $this->string(20)->null(),
            'dateTo' => $this->string(20)->null(),
            'seed' => $this->string(32)->notNull(),
            'eligibleCount' => $this->integer()->notNull()->defaultValue(0),
            'totalBallots' => $this->integer()->notNull()->defaultValue(0),
            'winnerCid' => $this->char(36)->null(),
            'winnerSubmissionId' => $this->integer()->null(),
            'poolSnapshotJson' => $this->longText()->null(),
            'drawnByUserId' => $this->integer()->null(),
            'dateDrawn' => $this->dateTime()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, $table, 'dateDrawn');
        $this->createIndex(null, $table, 'formHandle');

        $this->addForeignKey(null, $table, 'drawnByUserId', '{{%users}}', 'id', 'SET NULL');
    }
}
