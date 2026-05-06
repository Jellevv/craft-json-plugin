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

        if (!$this->db->tableExists('{{%jsonplugin_entries}}')) {
            $this->createTable('{{%jsonplugin_entries}}', [
                'id'        => $this->integer()->notNull(),
                'title'     => $this->string()->null(),
                'section'   => $this->string()->null(),
                'url'       => $this->string()->null(),
                'fields'    => $this->longText()->notNull()->defaultValue('{}'),
                'updatedAt' => $this->dateTime()->null(),
                'PRIMARY KEY([[id]])',
            ]);
        }

        if (!$this->db->tableExists('{{%jsonplugin_embeddings}}')) {
            $this->createTable('{{%jsonplugin_embeddings}}', [
                'id'          => $this->primaryKey(),
                'entryId'     => $this->integer()->notNull(),
                'chunkIndex'  => $this->integer()->notNull()->defaultValue(0),
                'embedding'   => $this->longText()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid'         => $this->uid(),
            ]);

            $this->createIndex(
                'idx_jsonplugin_embeddings_entry_chunk',
                '{{%jsonplugin_embeddings}}',
                ['entryId', 'chunkIndex'],
                true
            );
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%jsonplugin_embeddings}}');
        $this->dropTableIfExists('{{%jsonplugin_entries}}');
        $this->dropTableIfExists('{{%jsonplugin_stats}}');
        return true;
    }
}
