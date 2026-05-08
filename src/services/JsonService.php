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

        // Sync Commerce products if installed
        if (class_exists(\craft\commerce\elements\Product::class)) {
            $productOffset = 0;

            while (true) {
                $products = \craft\commerce\elements\Product::find()
                    ->limit($batchSize)
                    ->offset($productOffset)
                    ->all();

                if (empty($products))
                    break;

                foreach ($products as $product) {
                    $data = $this->prepareProductData($product);
                    $this->db->upsertEntry($data);
                    $queue->push(new GenerateEmbeddingJob(['entryId' => $product->id]));
                    $syncedIds[] = $product->id;
                    $synced++;
                }

                $productOffset += $batchSize;
            }
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
        $settings = \jelle\craftjsonplugin\JsonPlugin::getInstance()->getSettings();
        $includedFields = $settings->includedFields ?? [];

        if ($fieldLayout) {
            foreach ($fieldLayout->getCustomFields() as $field) {
                $handle = $field->handle;

                if (!empty($includedFields) && !in_array($handle, $includedFields)) {
                    continue;
                }

                try {
                    $value = $entry->getFieldValue($handle);
                    $fields[$handle] = $this->normalize->normalizeValue($value);
                } catch (\Throwable $e) {
                    Craft::error("Field {$handle}: " . $e->getMessage(), 'json-plugin');
                }
            }
        }
        $addresses = \craft\elements\Address::find()
            ->ownerId($entry->id)
            ->all();

        foreach ($addresses as $address) {
            $fields[$address->fieldId ? \Craft::$app->fields->getFieldById($address->fieldId)?->handle ?? 'address' : 'address'] = array_filter([
                'address1' => $address->addressLine1 ?? null,
                'address2' => $address->addressLine2 ?? null,
                'address3' => $address->addressLine3 ?? null,
                'city' => $address->locality ?? null,
                'zip' => $address->postalCode ?? null,
                'state' => $address->administrativeArea ?? null,
                'country' => $address->countryCode ?? null,
            ]);
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

    public function pushSingleProduct(int $productId, bool $fromQueue = false): bool
    {
        if (!class_exists(\craft\commerce\elements\Product::class)) {
            return false;
        }

        $product = \craft\commerce\elements\Product::find()
            ->id($productId)
            ->one();

        if (!$product) {
            return false;
        }

        $entry = $this->prepareProductData($product);
        $this->db->upsertEntry($entry);
        $this->clearPluginCache();

        $settings = \jelle\craftjsonplugin\JsonPlugin::getInstance()->getSettings();
        $hasEmbeddings = !empty(\craft\helpers\App::parseEnv($settings->openaiApiKey ?? ''));

        if ($hasEmbeddings) {
            if ($fromQueue) {
                $this->embeddings->generateAndSaveEmbeddings($entry);
            } else {
                Craft::$app->getQueue()->push(
                    new GenerateEmbeddingJob(['entryId' => $productId])
                );
            }
        }

        return true;
    }

    private function prepareProductData(\craft\commerce\elements\Product $product): array
    {
        $variants = $product->getVariants();

        $prices = [];
        $totalStock = 0;
        $hasStock = false;
        $optionGroups = [];

        foreach ($variants as $variant) {
            $prices[] = (float) $variant->price;

            if ($variant->hasUnlimitedStock) {
                $hasStock = true;
                $totalStock = null;
            } elseif ($totalStock !== null) {
                $totalStock += $variant->stock;
                if ($variant->stock > 0) {
                    $hasStock = true;
                }
            }

            foreach ($variant->getAttributes() as $handle => $value) {
                if (in_array($handle, ['id', 'productId', 'sku', 'stock', 'price', 'weight', 'width', 'height', 'length', 'sortOrder', 'deletedWithProduct', 'hasUnlimitedStock', 'minQty', 'maxQty'])) {
                    continue;
                }
                if ($value !== null && $value !== '') {
                    $optionGroups[$handle][] = $value;
                }
            }
        }

        foreach ($optionGroups as $name => $values) {
            $optionGroups[$name] = array_unique($values);
        }

        $minPrice = !empty($prices) ? min($prices) : null;
        $maxPrice = !empty($prices) ? max($prices) : null;

        $priceRange = $minPrice !== null
            ? ($minPrice === $maxPrice
                ? '€' . number_format($minPrice, 2, '.', '')
                : '€' . number_format($minPrice, 2, '.', '') . ' - €' . number_format($maxPrice, 2, '.', ''))
            : null;

        $fields = [];
        $fieldLayout = $product->getFieldLayout();
        if ($fieldLayout) {
            foreach ($fieldLayout->getCustomFields() as $field) {
                $handle = $field->handle;
                try {
                    $value = $product->getFieldValue($handle);
                    $fields[$handle] = $this->normalize->normalizeValue($value);
                } catch (\Throwable $e) {
                    Craft::error("Commerce field {$handle}: " . $e->getMessage(), 'json-plugin');
                }
            }
        }

        if ($priceRange !== null)
            $fields['price_range'] = $priceRange;
        if (!empty($optionGroups))
            $fields['available_options'] = $optionGroups;
        $fields['in_stock'] = $hasStock;
        if ($totalStock !== null)
            $fields['total_stock'] = $totalStock;

        return [
            'entry' => [
                'id' => $product->id,
                'title' => $product->title,
                'section' => 'commerce_' . ($product->getType()->handle ?? 'product'),
                'url' => $product->url,
            ],
            'fields' => $fields,
        ];
    }
}
