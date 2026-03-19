<?php
namespace jelle\craftjsonplugin\migrations;

use craft\db\Migration;

class Install extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%jsonplugin_stats}}')) {
            $this->createTable('{{%jsonplugin_stats}}', [
                'id'          => $this->primaryKey(),
                'sessionId'   => $this->string()->notNull(),
                'isFallback'  => $this->boolean()->notNull()->defaultValue(false),
                'dateAsked'   => $this->dateTime()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid'         => $this->uid(),
            ]);
        }
        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%jsonplugin_stats}}');
        return true;
    }
}
