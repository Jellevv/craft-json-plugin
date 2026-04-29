<?php

namespace jelle\craftjsonplugin\migrations;

use craft\db\Migration;

class m260422_000000_add_fields_column extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%jsonplugin_entries}}', 'fields')) {
            $this->addColumn(
                '{{%jsonplugin_entries}}',
                'fields',
                $this->longText()->notNull()->defaultValue('{}')->after('url')
            );
        }

        // Also ensure 'updatedAt' exists (used in upsertEntry)
        if (!$this->db->columnExists('{{%jsonplugin_entries}}', 'updatedAt')) {
            $this->addColumn(
                '{{%jsonplugin_entries}}',
                'updatedAt',
                $this->dateTime()->null()->after('fields')
            );
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%jsonplugin_entries}}', 'fields')) {
            $this->dropColumn('{{%jsonplugin_entries}}', 'fields');
        }

        return true;
    }
}
