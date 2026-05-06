<?php

namespace jelle\craftjsonplugin\migrations;

use craft\db\Migration;

class m260430_000000_add_chunked_embeddings extends Migration
{
    public function safeUp(): bool
    {
        $this->dropPrimaryKey('entryId', '{{%jsonplugin_embeddings}}');

        $this->addColumn(
            '{{%jsonplugin_embeddings}}',
            'id',
            $this->primaryKey()->first()
        );

        $this->addColumn(
            '{{%jsonplugin_embeddings}}',
            'chunkIndex',
            $this->integer()->notNull()->defaultValue(0)->after('entryId')
        );

        $this->createIndex(
            'idx_jsonplugin_embeddings_entry_chunk',
            '{{%jsonplugin_embeddings}}',
            ['entryId', 'chunkIndex'],
            true
        );

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropIndex('idx_jsonplugin_embeddings_entry_chunk', '{{%jsonplugin_embeddings}}');
        $this->dropColumn('{{%jsonplugin_embeddings}}', 'chunkIndex');
        $this->dropColumn('{{%jsonplugin_embeddings}}', 'id');
        $this->addPrimaryKey('entryId', '{{%jsonplugin_embeddings}}', 'entryId');

        return true;
    }
}
