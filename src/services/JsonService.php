<?php

namespace jelle\craftjsonplugin\services;

use Craft;
use craft\elements\Entry;
use craft\elements\Asset;
use yii\base\Component;
use craft\elements\db\ElementQueryInterface;
use jelle\craftjsonplugin\services\ai\AiInterface;
use jelle\craftjsonplugin\services\ai\OpenAiProvider;
use jelle\craftjsonplugin\services\ai\GroqProvider;
use jelle\craftjsonplugin\services\ai\ClaudeProvider;
use jelle\craftjsonplugin\services\ai\GeminiProvider;
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
            mkdir($dir, 0755, true);
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

            $cache = \Craft::$app->getCache();
            $keys = $cache->offsetGet('chatbot_session_keys') ?: [];
            foreach ($keys as $key) {
                $cache->delete($key);
            }
            $cache->delete('chatbot_session_keys');

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

        $cache = \Craft::$app->getCache();
        $keys = $cache->offsetGet('chatbot_session_keys') ?: [];
        foreach ($keys as $key) {
            $cache->delete($key);
        }
        $cache->delete('chatbot_session_keys');
    }

    public function getAiResponse(string $vraag, string $sessionId, string $pageUrl = ''): string
    {
        $settings = $this->getSettings();

        $aiProvider = $this->getAiProvider();
        if (is_string($aiProvider)) {
            return $aiProvider;
        }

        $cacheKey = "chatbot_history_" . $sessionId;
        $history = \Craft::$app->getCache()->get($cacheKey) ?: [];

        if (empty($history)) {
            $keys = \Craft::$app->getCache()->get('chatbot_session_keys') ?: [];
            $keys[] = $cacheKey;
            \Craft::$app->getCache()->set('chatbot_session_keys', $keys, 86400);

            $entries = array_map(function ($entry) {
                unset($entry['_id']);
                return $entry;
            }, $this->getJsonData()['entries']);
            $context = json_encode($entries, JSON_PRETTY_PRINT);

            $fallbackInstructie = $settings->useFallbackMessage
                ? "BELANGRIJK: Als de vraag niet beantwoord kan worden op basis van de beschikbare data, moet je ALTIJD en ALLEEN deze exacte zin antwoorden, zonder enige aanpassing of toevoeging: \"" . $settings->fallbackMessage . "\""
                : "";

            $systemContent = $settings->systemPrompt . "\n\n" . $fallbackInstructie . "\n\nContext Data: " . $context;

            if ($settings->useFallbackMessage) {
                $systemContent .= "\n\nHERINNERING: Gebruik voor onbekende vragen ALTIJD exact: \"" . $settings->fallbackMessage . "\"";
            }

            $history[] = [
                'role' => 'system',
                'content' => $systemContent
            ];
        }

        $vraagMetContext = $pageUrl
            ? "De gebruiker bevindt zich op deze pagina: {$pageUrl}\n\nVraag: {$vraag}"
            : $vraag;

        $history[] = ['role' => 'user', 'content' => $vraagMetContext];
        if (count($history) > 7) {
            $history = array_merge([$history[0]], array_slice($history, -6));
        }

        $answer = $aiProvider->chat($history, [
            'temperature' => (float) ($settings->temperature ?? 0.5),
            'max_tokens' => (int) ($settings->maxTokens ?? 300),
        ]);

        $isFallback = $settings->useFallbackMessage && $answer === $settings->fallbackMessage;
        \Craft::$app->getDb()->createCommand()->insert('{{%jsonplugin_stats}}', [
            'sessionId' => $sessionId,
            'isFallback' => $isFallback,
            'dateAsked' => (new \DateTime())->format('Y-m-d H:i:s'),
            'dateCreated' => (new \DateTime())->format('Y-m-d H:i:s'),
            'dateUpdated' => (new \DateTime())->format('Y-m-d H:i:s'),
            'uid' => \craft\helpers\StringHelper::UUID(),
        ])->execute();

        $history[] = ['role' => 'assistant', 'content' => $answer];
        \Craft::$app->getCache()->set($cacheKey, $history, 86400);

        return $answer;
    }

    private function getAiProvider(): AiInterface|string
    {
        $settings = $this->getSettings();
        $provider = $settings->aiProvider ?? 'openai';

        return match ($provider) {
            'groq' => $this->makeGroqProvider($settings),
            'claude' => $this->makeClaudeProvider($settings),
            'gemini' => $this->makeGeminiProvider($settings),
            default => $this->makeOpenAiProvider($settings),
        };
    }

    private function makeOpenAiProvider($settings): AiInterface|string
    {
        $apiKey = \craft\helpers\App::parseEnv($settings->openaiApiKey);
        if (!$apiKey) {
            return "Fout: Geen OpenAI API Key geconfigureerd in de plugin instellingen.";
        }
        return new OpenAiProvider(
            $apiKey,
            $settings->openaiModel ?: 'gpt-4o-mini'
        );
    }

    private function makeGroqProvider($settings): AiInterface|string
    {
        $apiKey = \craft\helpers\App::parseEnv($settings->groqApiKey);
        if (!$apiKey) {
            return "Fout: Geen Groq API Key geconfigureerd in de plugin instellingen.";
        }
        return new GroqProvider(
            $apiKey,
            $settings->groqModel ?: 'llama-3.3-70b-versatile'
        );
    }

    private function makeClaudeProvider($settings): AiInterface|string
    {
        $apiKey = \craft\helpers\App::parseEnv($settings->claudeApiKey);
        if (!$apiKey) {
            return "Fout: Geen Claude API Key geconfigureerd in de plugin instellingen.";
        }
        return new ClaudeProvider(
            $apiKey,
            $settings->claudeModel ?: 'claude-sonnet-4-6'
        );
    }

    private function makeGeminiProvider($settings): AiInterface|string
    {
        $apiKey = \craft\helpers\App::parseEnv($settings->geminiApiKey);
        if (!$apiKey) {
            return "Fout: Geen Gemini API Key geconfigureerd in de plugin instellingen.";
        }
        return new GeminiProvider(
            $apiKey,
            $settings->geminiModel ?: 'gemini-2.0-flash'
        );
    }
}
