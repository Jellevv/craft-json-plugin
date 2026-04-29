<?php

namespace jelle\craftjsonplugin\services;

use Craft;
use craft\elements\Entry;
use yii\base\Component;

class JsonService extends Component
{
    private DbStorageService $db;
    private EmbeddingService $embeddings;
    private NormalizationService $normalize;

    public function init(): void
    {
        parent::init();
        $plugin = \jelle\craftjsonplugin\JsonPlugin::$plugin;
        $this->db = $plugin->get('db');
        $this->embeddings = $plugin->get('embeddings');
        $this->normalize = new NormalizationService();
    }

    public function pushSingleEntry(int $elementId): bool
    {
        $element = Craft::$app->getElements()->getElementById($elementId);

        if (!$element instanceof Entry) {
            return false;
        }

        $entry = $this->prepareEntryData($element);
        $this->db->upsertEntry($entry);
        $this->embeddings->generateAndSaveEmbeddings($entry);

        return true;
    }

    public function deleteEntry(int $elementId): void
    {
        $this->db->deleteEntry($elementId);
        $this->db->deleteEmbedding($elementId);
    }

    public function syncAllContent(): array
    {
        $settings = \jelle\craftjsonplugin\JsonPlugin::getInstance()->getSettings();
        $batchSize = 100;
        $offset = 0;
        $synced = 0;
        $syncedIds = [];

        while (true) {
            $entries = Entry::find()
                ->section($settings->includedSections)
                ->limit($batchSize)
                ->offset($offset)
                ->all();

            if (empty($entries))
                break;

            foreach ($entries as $entry) {
                $data = $this->prepareEntryData($entry);
                $this->db->upsertEntry($data);
                $this->embeddings->generateAndSaveEmbeddings($data);
                $syncedIds[] = $entry->id;
                $synced++;
            }

            $offset += $batchSize;
        }

        $this->deleteStaleEntries($syncedIds);

        $cache = Craft::$app->getCache();
        $cache->delete('jsonplugin_all_embeddings');
        // Flush all cached chat context so the next question re-reads from the
        // freshly synced DB — prevents stale/untitled entries surviving in cache.
        $cache->flush();

        return ['success' => true, 'synced' => $synced];
    }

    private function deleteStaleEntries(array $activeIds): void
    {
        if (empty($activeIds)) {
            return;
        }

        $allStoredIds = (new \yii\db\Query())
            ->select('id')
            ->from('{{%jsonplugin_entries}}')
            ->column();

        $staleIds = array_diff($allStoredIds, $activeIds);

        foreach ($staleIds as $staleId) {
            $this->db->deleteEntry((int) $staleId);
            $this->db->deleteEmbedding((int) $staleId);
        }

        if ($staleIds) {
            Craft::info('Deleted ' . count($staleIds) . ' stale entries: ' . implode(', ', $staleIds), 'json-plugin');
        }
    }

    private function prepareEntryData(Entry $entry): array
    {
        $fields = [];
        $fieldLayout = $entry->getFieldLayout();

        if ($fieldLayout) {
            foreach ($fieldLayout->getCustomFields() as $field) {
                $handle = $field->handle;
                try {
                    $value = $entry->getFieldValue($handle);
                    $fields[$handle] = $this->normalize->normalizeValue($value);
                } catch (\Throwable $e) {
                    Craft::error("Field {$handle}: " . $e->getMessage(), 'json-plugin');
                }
            }
        }

        return [
            'entry' => [
                'id' => $entry->id,
                'title' => $entry->title,
                'section' => $entry->section->handle ?? null,
                'url' => $entry->url,
            ],
            'fields' => $fields,
        ];
    }
}
