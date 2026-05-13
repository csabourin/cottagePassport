<?php

namespace csabourin\stamppassport\migrations;

use craft\db\Migration;

class m260513_000000_add_item_qr_center_image_asset extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->columnExists('{{%stamppassport_items}}', 'qrCenterImageAssetId') === false) {
            $this->addColumn('{{%stamppassport_items}}', 'qrCenterImageAssetId', $this->integer()->null()->after('imageId'));
            $this->addForeignKey(
                null,
                '{{%stamppassport_items}}',
                'qrCenterImageAssetId',
                '{{%assets}}',
                'id',
                'SET NULL'
            );
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%stamppassport_items}}', 'qrCenterImageAssetId')) {
            $this->dropFkByColumn('{{%stamppassport_items}}', 'qrCenterImageAssetId');
            $this->dropColumn('{{%stamppassport_items}}', 'qrCenterImageAssetId');
        }

        return true;
    }

    private function dropFkByColumn(string $table, string $column): void
    {
        $schema = $this->db->getSchema()->getTableSchema($table, true);
        if (!$schema) {
            return;
        }

        foreach ($schema->foreignKeys as $name => $fk) {
            if (array_key_exists($column, $fk)) {
                $this->dropForeignKey($name, $table);
            }
        }
    }
}
