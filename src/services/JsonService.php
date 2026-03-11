<?php

namespace jelle\craftjsonplugin\services;

use Craft;
use craft\elements\Entry;
use craft\elements\Asset;
use yii\base\Component;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\App;
use OpenAI;

class JsonService extends Component
{
    private function getSettings()
    {
        return \jelle\craftjsonplugin\JsonPlugin::getInstance()->getSettings();
    }

    private function getStoragePath(): string
    {
        $path = Craft::getAlias('@storage/json_plugin/json_data.json');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $path;
    }

    private function getJsonData(): array
    {
        $path = $this->getStoragePath();
        if (!file_exists($path)) {
            return ['entries' => []];
        }
        return json_decode(file_get_contents($path), true) ?: ['entries' => []];
    }

    private function saveJsonData(array $data): bool
    {
        return (bool) file_put_contents($this->getStoragePath(), json_encode($data, JSON_PRETTY_PRINT));
    }

    public function pushSingleEntry(int $elementId): bool
    {
        try {
            $element = Craft::$app->getElements()->getElementById($elementId);
            if (!$element instanceof Entry)
                return false;

            $data = $this->getJsonData();
            $newEntry = $this->prepareEntryData($element);

            $found = false;
            foreach ($data['entries'] as &$entry) {
                if ($entry['id'] == $newEntry['id']) {
                    $entry = $newEntry;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $data['entries'][] = $newEntry;
            }

            \Craft::$app->getCache()->flush();
            return $this->saveJsonData($data);
        } catch (\Exception $e) {
            Craft::error("Fout bij opslaan entry {$elementId}: " . $e->getMessage(), 'json-plugin');
            return false;
        }
    }

    private function prepareEntryData(Entry $element): array
    {
        $settings = $this->getSettings();
        $includedFields = $settings->includedFields ?? [];

        $data = [
            'id' => $element->id,
            'title' => $element->title ?? 'Naamloos',
            'url' => $element->url,
            'sectie' => $element->section->handle ?? 'onbekend',
        ];

        foreach ($includedFields as $handle) {
            try {
                $field = Craft::$app->getFields()->getFieldByHandle($handle);
                if (!$field)
                    continue;

                $objectValue = $element->getFieldValue($handle);

                $data[$handle] = match (true) {
                    $field instanceof \craft\fields\Assets => $this->normalizeElements($objectValue, true),
                    $field instanceof \craft\fields\Entries,
                    $field instanceof \craft\fields\Categories => $this->normalizeElements($objectValue, false),
                    $field instanceof \craft\fields\Matrix => $this->normalizeContentBuilder($objectValue),
                    $field instanceof \craft\fields\Lightswitch => (bool) $objectValue,
                    str_contains(get_class($field), 'Money') => $this->normalizeMoney($objectValue),
                    default => $this->normalizeValue($objectValue),
                };
            } catch (\Exception $e) {
                continue;
            }
        }

        return $data;
    }

    private function normalizeElements($query, bool $isAsset): array
    {
        if (!$query instanceof ElementQueryInterface)
            return [];

        $elements = $query->all();
        return array_map(function ($el) use ($isAsset) {
            $base = [
                'id' => $el->id,
                'title' => $el->title,
                'url' => ($el instanceof Asset) ? $el->getUrl() : $el->url,
            ];

            if (!$isAsset && $el instanceof Entry) {
                foreach ($el->getFieldLayout()->getCustomFields() as $f) {
                    $base[$f->handle] = $this->normalizeValue($el->getFieldValue($f->handle));
                }
            }
            return $base;
        }, $elements);
    }

    private function normalizeMoney($value): string
    {
        if (!$value)
            return "0.00";

        $amountValue = 0;

        if (is_object($value)) {
            if (method_exists($value, 'getAmount')) {
                $amountValue = (float) $value->getAmount();
            } elseif (isset($value->amount)) {
                $amountValue = (float) $value->amount;
            }
        } else {
            $amountValue = (float) $value;
        }

        if ($amountValue > 100 && floor($amountValue) == $amountValue) {
            $amountValue = $amountValue / 100;
        }

        return number_format($amountValue, 2, '.', '');
    }

    private function normalizeContentBuilder($blocks): array
    {
        $output = [];
        $entries = ($blocks instanceof ElementQueryInterface) ? $blocks->all() : ($blocks ?? []);

        foreach ($entries as $block) {
            $blockData = ['type' => $block->getType()->handle, 'fields' => []];
            foreach ($block->getFieldLayout()->getCustomFields() as $subField) {
                $val = $block->getFieldValue($subField->handle);
                $blockData['fields'][$subField->handle] = $this->normalizeValue($val);
            }
            $output[] = $blockData;
        }
        return $output;
    }

    private function normalizeValue($value)
    {
        if ($value === null)
            return null;

        if ($value instanceof ElementQueryInterface) {
            $isAsset = str_contains(get_class($value), 'Asset');
            return $this->normalizeElements($value, $isAsset);
        }

        if ($value instanceof \DateTimeInterface)
            return $value->format(DATE_ATOM);

        if ($value instanceof \craft\fields\data\MultiOptionsFieldData) {
            return array_map(fn($o) => (string) $o, $value->getOptions());
        }

        if (is_object($value)) {
            if (str_contains(get_class($value), 'Money')) {
                return $this->normalizeMoney($value);
            }
            if (str_contains(get_class($value), 'Address'))
                return (string) $value;
            return method_exists($value, '__toString') ? strip_tags((string) $value) : "[Object]";
        }

        if (is_array($value))
            return array_map([$this, 'normalizeValue'], $value);

        if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE)
                return $decoded;
        }

        return is_numeric($value) ? $value : strip_tags((string) $value);
    }

    public function syncAllContent(): array
    {
        $settings = $this->getSettings();
        $count = 0;
        try {
            $this->saveJsonData(['entries' => []]);

            if (!empty($settings->includedSections)) {
                $entries = Entry::find()->section($settings->includedSections)->all();
                foreach ($entries as $e) {
                    if ($this->pushSingleEntry($e->id))
                        $count++;
                }
            }

            \Craft::$app->getCache()->flush();

            return ['success' => true, 'count' => $count];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteEntry(int $elementId): void
    {
        $data = $this->getJsonData();
        $data['entries'] = array_filter($data['entries'], fn($e) => $e['id'] != $elementId);
        $this->saveJsonData($data);
    }

    public function getAiResponse(string $vraag, string $sessionId, string $pageUrl = ''): string
    {
        $settings = $this->getSettings();

        $apiKey = \craft\helpers\App::parseEnv($settings->openaiApiKey);

        if (!$apiKey) {
            return "Fout: Geen OpenAI API Key geconfigureerd in de plugin instellingen.";
        }

        $client = \OpenAI::client($apiKey);

        $cacheKey = "chatbot_history_" . $sessionId;
        $history = \Craft::$app->getCache()->get($cacheKey) ?: [];

        if (empty($history)) {
            $context = json_encode($this->getJsonData()['entries'], JSON_PRETTY_PRINT);
            $history[] = [
                'role' => 'system',
                'content' => $settings->systemPrompt . "\n\nContext Data: " . $context
            ];
        }

        $vraagMetContext = $pageUrl
            ? "De gebruiker bevindt zich op deze pagina: {$pageUrl}\n\nVraag: {$vraag}"
            : $vraag;

        $history[] = ['role' => 'user', 'content' => $vraagMetContext];
        if (count($history) > 7) {
            $history = array_merge([$history[0]], array_slice($history, -6));
        }

        $response = $client->chat()->create([
            'model' => $settings->openaiModel ?: 'gpt-4o-mini',
            'messages' => $history,
            'temperature' => (float) ($settings->temperature ?? 0.5),
        ]);

        $answer = $response->choices[0]->message->content;
        $history[] = ['role' => 'assistant', 'content' => $answer];

        \Craft::$app->getCache()->set($cacheKey, $history, 86400);

        return $answer;
    }
}
