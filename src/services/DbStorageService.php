<?php

namespace jelle\craftjsonplugin\services;

use Craft;
use yii\base\Component;
use yii\db\Query;

class DbStorageService extends Component
{
    public function upsertEntry(array $data): void
    {
        $entry = $data['entry'] ?? null;
        $fields = $data['fields'] ?? [];

        if (!$entry || !isset($entry['id'])) {
            Craft::error('Invalid entry data', 'json-plugin');
            return;
        }

        $update = ['fields' => json_encode($fields), 'updatedAt' => date('Y-m-d H:i:s')];

        if (!empty($entry['title']))
            $update['title'] = $entry['title'];
        if (!empty($entry['section']))
            $update['section'] = $entry['section'];
        if (!empty($entry['url']))
            $update['url'] = $entry['url'];

        Craft::$app->db->createCommand()->upsert(
            '{{%jsonplugin_entries}}',
            [
                'id' => $entry['id'],
                'title' => $entry['title'] ?? null,
                'section' => $entry['section'] ?? null,
                'url' => $entry['url'] ?? null,
                'fields' => json_encode($fields),
                'updatedAt' => date('Y-m-d H:i:s'),
            ],
            $update
        )->execute();
    }

    public function deleteEntry(int $entryId): void
    {
        Craft::$app->db->createCommand()
            ->delete('{{%jsonplugin_entries}}', ['id' => $entryId])
            ->execute();
    }

    public function getAllEntries(): array
    {
        $rows = (new Query())
            ->from('{{%jsonplugin_entries}}')
            ->all();

        return array_map(fn($row) => $this->rowToEntry($row), $rows);
    }

    public function getEntriesByIds(array $ids): array
    {
        if (empty($ids))
            return [];

        $rows = (new Query())
            ->from('{{%jsonplugin_entries}}')
            ->where(['id' => $ids])
            ->all();

        // Preserve the order of $ids
        $map = [];
        foreach ($rows as $row) {
            $map[$row['id']] = $this->rowToEntry($row);
        }

        return array_values(array_filter(
            array_map(fn($id) => $map[$id] ?? null, $ids)
        ));
    }

    private function rowToEntry(array $row): array
    {
        return [
            'entry' => [
                'id' => $row['id'],
                'title' => $row['title'],
                'section' => $row['section'],
                'url' => $row['url'],
            ],
            'fields' => json_decode($row['fields'] ?? '[]', true) ?: [],
        ];
    }

    public function saveEmbedding(int $entryId, array $chunks): void
    {
        Craft::$app->db->createCommand()
            ->delete('{{%jsonplugin_embeddings}}', ['entryId' => $entryId])
            ->execute();

        foreach ($chunks as $index => $vector) {
            Craft::$app->db->createCommand()->insert(
                '{{%jsonplugin_embeddings}}',
                [
                    'entryId' => $entryId,
                    'chunkIndex' => $index,
                    'embedding' => json_encode($vector),
                    'dateCreated' => date('Y-m-d H:i:s'),
                    'dateUpdated' => date('Y-m-d H:i:s'),
                    'uid' => \craft\helpers\StringHelper::UUID(),
                ]
            )->execute();
        }

        Craft::$app->getCache()->delete('jsonplugin_all_embeddings');
    }

    public function deleteEmbedding(int $entryId): void
    {
        Craft::$app->db->createCommand()
            ->delete('{{%jsonplugin_embeddings}}', ['entryId' => $entryId])
            ->execute();

        Craft::$app->getCache()->delete('jsonplugin_all_embeddings');
    }

    public function getAllEmbeddings(?array $includedSections = null): array
    {
        $query = (new Query())
            ->select(['e.entryId', 'e.chunkIndex', 'e.embedding'])
            ->from(['e' => '{{%jsonplugin_embeddings}}'])
            ->innerJoin(['n' => '{{%jsonplugin_entries}}'], 'n.id = e.entryId')
            ->orderBy(['e.entryId' => SORT_ASC, 'e.chunkIndex' => SORT_ASC]);

        if (!empty($includedSections)) {
            $query->where(['n.section' => $includedSections]);
        }

        $out = [];
        foreach ($query->all() as $row) {
            $out[$row['entryId']][$row['chunkIndex']] = json_decode($row['embedding'], true) ?: [];
        }

        return $out;
    }
}
