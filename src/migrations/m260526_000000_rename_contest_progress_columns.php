<?php

namespace csabourin\stamppassport\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\helpers\StringHelper;

/**
 * Renames updated_at/created_at to dateUpdated/dateCreated and adds uid
 * on the stamppassport_contest_progress table to match Craft conventions.
 */
class m260526_000000_rename_contest_progress_columns extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%stamppassport_contest_progress}}';

        if (!$this->db->tableExists($table)) {
            return true;
        }

        $schema = $this->db->getTableSchema($table);

        // Rename updated_at → dateUpdated
        if ($schema->getColumn('updated_at') && !$schema->getColumn('dateUpdated')) {
            // Drop the old index — wrap in try/catch because TableSchema has no
            // index-existence check and the index name may differ across installs.
            try {
                $this->dropIndex('idx_stamppassport_contest_progress_updated_at', $table);
            } catch (\Throwable $e) {
                // Index didn't exist or had a different name — safe to continue.
            }

            $this->renameColumn($table, 'updated_at', 'dateUpdated');

            try {
                $this->createIndex(
                    'idx_stamppassport_contest_progress_dateUpdated',
                    $table,
                    'dateUpdated'
                );
            } catch (\Throwable $e) {
                // Index already exists under this name — safe to continue.
            }
        }

        // Rename created_at → dateCreated
        if ($schema->getColumn('created_at') && !$schema->getColumn('dateCreated')) {
            $this->renameColumn($table, 'created_at', 'dateCreated');
        }

        // Add uid column if missing
        if (!$schema->getColumn('uid')) {
            $this->addColumn($table, 'uid', $this->uid()->notNull()->defaultValue(''));

            // Populate uid for all existing rows
            $rows = (new Query())->from($table)->select(['contest_id'])->all($this->db);
            foreach ($rows as $row) {
                $this->update($table, ['uid' => StringHelper::UUID()], ['contest_id' => $row['contest_id']], [], false);
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        $table = '{{%stamppassport_contest_progress}}';

        if (!$this->db->tableExists($table)) {
            return true;
        }

        $schema = $this->db->getTableSchema($table);

        if ($schema->getColumn('dateUpdated') && !$schema->getColumn('updated_at')) {
            try {
                $this->dropIndex('idx_stamppassport_contest_progress_dateUpdated', $table);
            } catch (\Throwable $e) {
                // Index didn't exist — safe to continue.
            }

            $this->renameColumn($table, 'dateUpdated', 'updated_at');

            try {
                $this->createIndex('idx_stamppassport_contest_progress_updated_at', $table, 'updated_at');
            } catch (\Throwable $e) {
                // Index already exists — safe to continue.
            }
        }

        if ($schema->getColumn('dateCreated') && !$schema->getColumn('created_at')) {
            $this->renameColumn($table, 'dateCreated', 'created_at');
        }

        if ($schema->getColumn('uid')) {
            $this->dropColumn($table, 'uid');
        }

        return true;
    }
}
