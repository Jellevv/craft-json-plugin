<?php

namespace jelle\craftjsonplugin\services;

use Craft;
use craft\elements\Entry;
use yii\base\Component;
use jelle\craftjsonplugin\jobs\GenerateEmbeddingJob;

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

    public function pushSingleEntry(int $elementId, bool $fromQueue = false): bool
    {
        $element = Craft::$app->getElements()->getElementById($elementId);

        if (!$element instanceof Entry) {
            return false;
        }

        $entry = $this->prepareEntryData($element);
        $this->db->upsertEntry($entry);

        $this->clearPluginCache();

        $settings = \jelle\craftjsonplugin\JsonPlugin::getInstance()->getSettings();
        $hasEmbeddings = !empty(\craft\helpers\App::parseEnv($settings->openaiApiKey ?? ''));

        if ($hasEmbeddings) {
            if ($fromQueue) {
                $this->embeddings->generateAndSaveEmbeddings($entry);
            } else {
                Craft::$app->getQueue()->push(
                    new GenerateEmbeddingJob(['entryId' => $elementId])
                );
            }
        }

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
        $queue = Craft::$app->getQueue();

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

                $queue->push(new GenerateEmbeddingJob(['entryId' => $entry->id]));

                $syncedIds[] = $entry->id;
                $synced++;
            }

            $offset += $batchSize;
        }

        $this->deleteStaleEntries($syncedIds);

        $this->clearPluginCache();

        return ['success' => true, 'synced' => $synced];
    }

    private function clearPluginCache(): void
    {
        $cache = Craft::$app->getCache();
        $settings = \jelle\craftjsonplugin\JsonPlugin::getInstance()->getSettings();

        $cache->delete('jsonplugin_all_embeddings_' . md5(json_encode($settings->includedSections ?? null)));
        $cache->delete('jsonplugin_all_embeddings');

        foreach (['openai', 'groq', 'claude', 'gemini'] as $provider) {
            $cache->delete("chat_entries_all_{$provider}");
        }
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
