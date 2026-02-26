<?php

namespace csabourin\stamppassport\migrations;

use craft\db\Migration;

/**
 * Creates the stamppassport_contest_progress table for existing installs.
 * Fresh installs get this table via Install::safeUp().
 */
class m260226_000000_create_contest_progress_table extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%stamppassport_contest_progress}}')) {
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

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%stamppassport_contest_progress}}');
        return true;
    }
}
