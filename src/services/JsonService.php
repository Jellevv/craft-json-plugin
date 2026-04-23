<?php

namespace jelle\craftjsonplugin\services;

use Craft;
use craft\elements\Entry;
use craft\elements\db\ElementQueryInterface;
use jelle\craftjsonplugin\services\EmbeddingService;
use jelle\craftjsonplugin\services\NormalizationService;
use jelle\craftjsonplugin\services\StorageService;
use jelle\craftjsonplugin\services\ai\AiInterface;
use jelle\craftjsonplugin\services\ai\OpenAiProvider;
use jelle\craftjsonplugin\services\ai\GroqProvider;
use jelle\craftjsonplugin\services\ai\ClaudeProvider;
use jelle\craftjsonplugin\services\ai\GeminiProvider;
use craft\helpers\App;
use yii\base\Component;

class JsonService extends Component
{
    private StorageService $storageService;
    private NormalizationService $normalizationService;
    private EmbeddingService $embeddingService;

    public function init(): void
    {
        parent::init();
        $this->storageService = new StorageService();
        $this->normalizationService = new NormalizationService();
        $this->embeddingService = new EmbeddingService();
    }

    // =========================
    // Settings & storage
    // =========================

    private function getSettings()
    {
        return \jelle\craftjsonplugin\JsonPlugin::getInstance()->getSettings();
    }

    // =========================
    // Push entries + embeddings
    // =========================

    public function pushSingleEntry(int $elementId): bool
    {
        try {
            $element = Craft::$app->getElements()->getElementById($elementId);
            if (!$element instanceof Entry)
                return false;

            $data = $this->storageService->getJsonData();
            $newEntry = $this->prepareEntryData($element);

            $found = false;

            foreach ($data['entries'] as &$entry) {
                if ($entry['id'] == $newEntry['id']) {
                    $entry = $newEntry;
                    $found = true;
                    break;
                }
            }
            unset($entry);

            if (!$found) {
                $data['entries'][] = $newEntry;
            }

            $this->storageService->saveJsonData($data);

            $this->ensureEmbedding($newEntry);

            return true;

        } catch (\Throwable $e) {
            Craft::error("Error pushSingleEntry {$elementId}: {$e->getMessage()}", 'json-plugin');
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

                $value = $element->getFieldValue($handle);

                $data[$handle] = match (true) {
                    $field instanceof \craft\fields\Assets =>
                    $this->normalizationService->normalizeElements($value, true),

                    $field instanceof \craft\fields\Entries,
                    $field instanceof \craft\fields\Categories =>
                    $this->normalizationService->normalizeElements($value, false),

                    $field instanceof \craft\fields\Matrix =>
                    $this->normalizationService->normalizeContentBuilder($value),

                    $field instanceof \craft\fields\Lightswitch =>
                    (bool) $value,

                    str_contains(get_class($field), 'Money') =>
                    $this->normalizationService->normalizeMoney($value),

                    default =>
                    $this->normalizationService->normalizeValue($value),
                };

            } catch (\Throwable) {
                continue;
            }
        }

        return $data;
    }

    public function syncAllContent(): array
    {
        try {
            $settings = $this->getSettings();

            $data = $this->storageService->getJsonData();
            $existingEntries = $data['entries'] ?? [];

            $existingMap = [];
            foreach ($existingEntries as $entry) {
                $existingMap[$entry['id']] = $entry;
            }

            $embeddings = $this->storageService->getStoredEmbeddings(); // 🔥 FIX

            $newEntries = [];
            $processedIds = [];

            $created = 0;
            $updated = 0;

            $entries = Entry::find()
                ->section($settings->includedSections)
                ->all();

            foreach ($entries as $e) {

                $newEntry = $this->prepareEntryData($e);
                $id = $newEntry['id'];

                $processedIds[] = $id;

                $old = $existingMap[$id] ?? null;

                $compareOld = $old;
                $compareNew = $newEntry;

                if ($compareOld) {
                    unset($compareOld['id']);
                }
                unset($compareNew['id']);

                $isNew = !$old;
                $isChanged = $old && $compareOld !== $compareNew;
                $missingEmbedding = !isset($embeddings[$id]);

                $needsEmbedding = $this->hasOpenAiKey() && ($isNew || $isChanged || $missingEmbedding);

                if ($isNew) {
                    $created++;
                } elseif ($isChanged) {
                    $updated++;
                }

                if ($needsEmbedding) {
                    $this->embeddingService->generateAndSaveEmbeddings($newEntry);
                }

                $newEntries[] = $newEntry;
            }

            $deletedIds = array_diff(array_keys($existingMap), $processedIds);

            if ($deletedIds) {
                foreach ($deletedIds as $id) {
                    unset($embeddings[$id]);
                }
                $this->storageService->saveStoredEmbeddings($embeddings);
            }

            // SAVE JSON
            $this->storageService->saveJsonData([
                'entries' => $newEntries
            ]);

            $this->clearChatbotCache();

            Craft::info(
                "Sync — created: {$created}, updated: {$updated}, deleted: " . count($deletedIds),
                'json-plugin'
            );

            return [
                'success' => true,
                'created' => $created,
                'updated' => $updated,
                'deleted' => count($deletedIds),
            ];

        } catch (\Throwable $e) {
            Craft::error("Sync error: {$e->getMessage()}", 'json-plugin');

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function deleteEntry(int $elementId): void
    {
        $data = $this->storageService->getJsonData();
        $data['entries'] = array_values(
            array_filter($data['entries'], fn($e) => $e['id'] != $elementId)
        );
        $this->storageService->saveJsonData($data);

        // Also remove its embedding
        $embeddings = $this->storageService->getStoredEmbeddings();
        if (isset($embeddings[$elementId])) {
            unset($embeddings[$elementId]);
            $this->storageService->saveStoredEmbeddings($embeddings);
        }

        $this->clearChatbotCache();
    }

    private function ensureEmbedding(array $entry): void
    {
        if (!$this->hasOpenAiKey()) {
            return;
        }

        $embeddings = $this->storageService->getStoredEmbeddings();
        $id = $entry['id'];

        if (!isset($embeddings[$id])) {
            $this->embeddingService->generateAndSaveEmbeddings($entry);
        }
    }

    private function hasOpenAiKey(): bool
    {
        $settings = $this->getSettings();
        return !empty(App::parseEnv($settings->openaiApiKey));
    }

    private function clearChatbotCache(): void
    {
        $cache = Craft::$app->getCache();
        $keys = $cache->get('chatbot_session_keys') ?: [];

        foreach ($keys as $key) {
            $cache->delete($key);
        }

        $cache->delete('chatbot_session_keys');
    }

    public function getAiResponse(
        string $question,
        string $sessionId,
        string $pageUrl = '',
        string $currentSectionHandle = ''
    ): string {
        $settings = $this->getSettings();
        $aiProvider = $this->getAiProvider();

        if (is_string($aiProvider)) {
            return $aiProvider;
        }

        $cache = Craft::$app->getCache();
        $sessionKey = "chatbot_session_entries_{$sessionId}";
        $sessionHistoryKey = "chatbot_session_history_{$sessionId}";

        $history = $cache->get($sessionHistoryKey) ?: [];
        $history[] = ['role' => 'user', 'content' => $question];

        $cachedEntries = $cache->get($sessionKey) ?: [];
        $cachedContextText = $this->buildContextForLlm($cachedEntries);

        $cachedEntries = $cache->get($sessionKey) ?: [];
        $cachedContextText = $this->buildContextForLlm($cachedEntries);

        $contextScore = 0.0;

        if (!empty($cachedEntries)) {
            $contextScore = $this->estimateContextCoverage(
                $question,
                $cachedEntries,
                $aiProvider
            );
        }

        if ($contextScore > 0.80) {

            $systemContent = $settings->systemPrompt
                . "\n\nContext:\n" . $cachedContextText
                . "\n\n" . ($pageUrl ? "User is on page: {$pageUrl}\n\n" : '');

            $answer = $aiProvider->chat(
                array_merge(
                    [['role' => 'system', 'content' => $systemContent]],
                    $history
                ),
                [
                    'temperature' => (float) ($settings->temperature ?? 0.5),
                    'max_tokens' => (int) ($settings->maxTokens ?? 300),
                ]
            );

            $history[] = ['role' => 'assistant', 'content' => $answer];
            $cache->set($sessionHistoryKey, $history, 86400);

            return $answer;
        }

        $allEntries = $this->storageService->getJsonData()['entries'] ?? [];
        if ($currentSectionHandle) {
            $allEntries = array_values(
                array_filter($allEntries, fn($e) => ($e['sectie'] ?? '') === $currentSectionHandle)
            );
        }

        $cachedEntries = $cache->get($sessionKey) ?: [];
        $cachedIds = array_column($cachedEntries, 'id');
        $remainingEntries = $allEntries;

        $newEntries = [];
        if (!empty(App::parseEnv($settings->openaiApiKey))) {
            $newEntries = $this->embeddingService->getTopEntriesByEmbedding(
                $question,
                $remainingEntries,
                (int) ($settings->embeddingTopK ?? 5),
                (float) ($settings->embeddingThreshold ?? 0.15),
                $cachedIds
            );
        } else {

            Craft::warning(
                "No embeddings available — returning ALL remaining entries.",
                'json-plugin'
            );

            $newEntries = $remainingEntries;
        }

        $merged = $this->mergeContextEntries(
            $cachedEntries,
            $newEntries,
            $question
        );

        $seen = [];
        $unique = [];
        foreach ($merged as $entry) {
            if (!isset($seen[$entry['id']])) {
                $seen[$entry['id']] = true;
                $unique[] = $entry;
            }
        }

        $cache->set($sessionKey, $unique, 86400);

        $contextText = $this->buildContextForLlm($unique);
        $pageHint = $pageUrl ? "User is on page: {$pageUrl}\n\n" : '';
        $systemContent = $settings->systemPrompt
            . "\n\n" . ($settings->useFallbackMessage
            ? "Only reply with \"{$settings->fallbackMessage}\" if the answer is clearly not present in the context.\n\n"
            : '')
            . "Context:\n" . $contextText . "\n\n" . $pageHint;

        $answer = $aiProvider->chat(
            array_merge([['role' => 'system', 'content' => $systemContent]], $history),
            [
                'temperature' => (float) ($settings->temperature ?? 0.5),
                'max_tokens' => (int) ($settings->maxTokens ?? 300),
            ]
        );

        $this->logStat($sessionId, $settings, $answer);
        if (!empty($newEntries)) {
            $this->logLlmContext($newEntries, $question);
        }

        $history[] = ['role' => 'assistant', 'content' => $answer];
        $cache->set($sessionHistoryKey, $history, 86400);
        return $answer;
    }

    private function mergeContextEntries(
        array $cached,
        array $new,
        string $question
    ): array {

        $all = array_merge($cached, $new);

        // dedupe
        $seen = [];
        $unique = [];

        foreach ($all as $entry) {
            if (!isset($entry['id']))
                continue;

            if (isset($seen[$entry['id']])) {
                continue;
            }

            $seen[$entry['id']] = true;
            $unique[] = $entry;
        }

        return $unique;
    }

    private function estimateContextCoverage(
        string $question,
        array $entries,
        AiInterface $aiProvider
    ): float {
        if (empty($entries)) {
            return 0.0;
        }

        $sample = array_slice($entries, 0, 200);
        $contextText = $this->buildContextForLlm($sample);

        $prompt = "You are a strict evaluator.\n"
            . "Rate how well the context can answer the question.\n"
            . "Return ONLY a number between 0 and 1.\n\n"
            . "0 = not enough info\n"
            . "1 = fully answerable\n\n"
            . "Context:\n{$contextText}\n\n"
            . "Question:\n{$question}\n";

        $result = $aiProvider->chat(
            [
                ['role' => 'user', 'content' => $prompt]
            ],
            [
                'temperature' => 0,
                'max_tokens' => 5
            ]
        );

        // extract float safely
        if (preg_match('/0(\.\d+)?|1(\.0+)?/', $result, $m)) {
            return (float) $m[0];
        }

        return 0.0;
    }

    private function canAnswerFromContext(
        string $question,
        string $contextText,
        AiInterface $aiProvider,
        array $history,
    ): bool {
        if (empty($contextText)) {
            return false;
        }

        $transcript = '';
        foreach (array_slice($history, -6) as $msg) {
            $role = $msg['role'] === 'user' ? 'User' : 'Assistant';
            $transcript .= "{$role}: {$msg['content']}\n";
        }

        $prompt = "You decide whether the context below already contains enough information "
            . "to answer the user's latest question.\n"
            . "Use the conversation history to resolve pronouns like 'it', 'its', 'that', 'this, his, her'.\n\n"
            . "Context:\n{$contextText}\n\n"
            . "Conversation so far:\n{$transcript}\n"
            . "Latest question: {$question}\n\n";

        $result = $aiProvider->chat(
            [['role' => 'user', 'content' => $prompt]],
            ['temperature' => 0, 'max_tokens' => 3]
        );

        return stripos($result, 'yes') !== false;
    }

    private function logStat(string $sessionId, $settings, string $answer): void
    {
        $isFallback = ($settings->useFallbackMessage ?? false) && $answer === $settings->fallbackMessage;
        $nowUtc = new \DateTime('now', new \DateTimeZone('UTC'));

        try {
            Craft::$app->getDb()->createCommand()->insert('{{%jsonplugin_stats}}', [
                'sessionId' => $sessionId,
                'isFallback' => $isFallback,
                'dateAsked' => $nowUtc->format('Y-m-d H:i:s'),
                'dateCreated' => $nowUtc->format('Y-m-d H:i:s'),
                'dateUpdated' => $nowUtc->format('Y-m-d H:i:s'),
                'uid' => \craft\helpers\StringHelper::UUID(),
            ])->execute();
        } catch (\Throwable $e) {
            Craft::error("Error logging stat: " . $e->getMessage(), 'json-plugin');
        }
    }

    // =========================
    // AI providers
    // =========================

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
        $apiKey = App::parseEnv($settings->openaiApiKey);
        if (!$apiKey)
            return 'Fout: Geen OpenAI API Key geconfigureerd in de plugin instellingen.';
        return new OpenAiProvider($apiKey, $settings->openaiModel ?: 'gpt-4o-mini');
    }
    private function makeGroqProvider($settings): AiInterface|string
    {
        $apiKey = App::parseEnv($settings->groqApiKey);
        if (!$apiKey)
            return 'Fout: Geen Groq API Key geconfigureerd in de plugin instellingen.';
        return new GroqProvider($apiKey, $settings->groqModel ?: 'llama-3.3-70b-versatile');
    }
    private function makeClaudeProvider($settings): AiInterface|string
    {
        $apiKey = App::parseEnv($settings->claudeApiKey);
        if (!$apiKey)
            return 'Fout: Geen Claude API Key geconfigureerd in de plugin instellingen.';
        return new ClaudeProvider($apiKey, $settings->claudeModel ?: 'claude-sonnet-4-6');
    }
    private function makeGeminiProvider($settings): AiInterface|string
    {
        $apiKey = App::parseEnv($settings->geminiApiKey);
        if (!$apiKey)
            return 'Fout: Geen Gemini API Key geconfigureerd in de plugin instellingen.';
        return new GeminiProvider($apiKey, $settings->geminiModel ?: 'gemini-2.0-flash');
    }

    // =========================
    // Context builder
    // =========================

    private function buildContextForLlm(array $entries): string
    {
        $lines = [];
        foreach ($entries as $entry) {
            $entryLines = [];
            if (!empty($entry['title'])) {
                $entryLines[] = "name: " . $entry['title'];
                $entryLines[] = "title: " . $entry['title'];
            }
            if (!empty($entry['sectie'])) {
                $entryLines[] = "section: " . $entry['sectie'];

                if ($entry['sectie'] === 'team') {
                    $entryLines[] = "entity_type: person";
                }
            }
            foreach ($entry as $key => $value) {
                if (in_array($key, ['id', 'title', 'url', 'sectie']))
                    continue;
                if (is_array($value)) {
                    if (isset($value[0]['type']))
                        continue;
                    $value = json_encode($value);
                }
                if ($value !== '' && $value !== null) {
                    if ($key === 'role') {
                        $entryLines[] = "job_title: " . $value;
                    } else {
                        $entryLines[] = $key . ": " . $value;
                    }
                }
            }
            if (!empty($entryLines)) {
                $lines[] = implode("\n", $entryLines);
            }
        }
        return implode("\n\n", $lines);
    }



    // =========================
    // Debug logging
    // =========================

    private function logLlmContext(array $entries, string $question): void
    {
        Craft::info("=== LLM DEBUG LOG ===", 'json-plugin');
        Craft::info("Question: {$question}", 'json-plugin');
        Craft::info("Entries count: " . count($entries), 'json-plugin');

        foreach ($entries as $entry) {
            $id = $entry['id'] ?? 'unknown';
            $section = $entry['sectie'] ?? 'unknown';
            $fields = array_diff(array_keys($entry), ['id', 'sectie', 'title', 'url']);

            Craft::info("Entry ID {$id} | Section: {$section} | Fields: " . implode(', ', $fields), 'json-plugin');

            foreach ($fields as $f) {
                $valuePreview = is_array($entry[$f]) ? json_encode($entry[$f]) : (string) $entry[$f];
                if (strlen($valuePreview) > 100) {
                    $valuePreview = substr($valuePreview, 0, 100) . '...';
                }
                Craft::info("    {$f}: {$valuePreview}", 'json-plugin');
            }
        }

        Craft::info("=== END LLM DEBUG LOG ===", 'json-plugin');
    }
}
