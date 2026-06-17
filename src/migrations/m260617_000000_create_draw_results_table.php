<?php

namespace csabourin\stamppassport\migrations;

use craft\db\Migration;

/**
 * Creates the stamppassport_draw_results table, which records each weighted
 * prize draw (seed, eligible pool snapshot, winner) so a draw is auditable and
 * reproducible after the fact.
 */
class m260617_000000_create_draw_results_table extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%stamppassport_draw_results}}';

        if ($this->db->tableExists($table)) {
            return true;
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

        // If the user who ran the draw is later deleted, keep the audit row but null the reference.
        $this->addForeignKey(null, $table, 'drawnByUserId', '{{%users}}', 'id', 'SET NULL');

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%stamppassport_draw_results}}');
        return true;
    }
}
