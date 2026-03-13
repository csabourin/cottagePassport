<?php

namespace csabourin\stamppassport\migrations;

use craft\db\Migration;

class m260313_000000_add_entry_relations extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->columnExists('{{%stamppassport_items_content}}', 'linkEntryId') === false) {
            $this->addColumn('{{%stamppassport_items_content}}', 'linkEntryId', $this->integer()->null()->after('linkUrl'));
            $this->createIndex(null, '{{%stamppassport_items_content}}', 'linkEntryId');
            $this->addForeignKey(
                null,
                '{{%stamppassport_items_content}}',
                'linkEntryId',
                '{{%entries}}',
                'id',
                'SET NULL'
            );
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%stamppassport_items_content}}', 'linkEntryId')) {
            $this->dropForeignKeyIfExists('{{%stamppassport_items_content}}', 'linkEntryId');
            $this->dropIndexIfExists('{{%stamppassport_items_content}}', 'linkEntryId');
            $this->dropColumn('{{%stamppassport_items_content}}', 'linkEntryId');
        }

        return true;
    }

    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        $schema = $this->db->getSchema()->getTableSchema($table, true);
        if (!$schema) {
            return;
        }
        foreach ($schema->foreignKeys as $name => $fk) {
            if (($fk[0] ?? null) === $column) {
                $this->dropForeignKey($name, $table);
            }
        }
    }

    private function dropIndexIfExists(string $table, string $column): void
    {
        $schema = $this->db->getSchema()->getTableSchema($table, true);
        if (!$schema) {
            return;
        }
        foreach ($schema->indexes as $name => $index) {
            if (($index['columns'] ?? []) === [$column]) {
                $this->dropIndex($name, $table);
            }
        }
    }
}
