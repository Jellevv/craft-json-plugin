<?php

namespace jelle\craftjsonplugin\services;

use Craft;
use craft\helpers\App;
use craft\helpers\StringHelper;
use yii\base\Component;
use jelle\craftjsonplugin\services\ai\AiInterface;
use jelle\craftjsonplugin\services\ai\OpenAiProvider;
use jelle\craftjsonplugin\services\ai\GroqProvider;
use jelle\craftjsonplugin\services\ai\ClaudeProvider;
use jelle\craftjsonplugin\services\ai\GeminiProvider;

class ChatService extends Component
{
    private EmbeddingService $embeddings;

    public function init(): void
    {
        parent::init();
        $plugin = \jelle\craftjsonplugin\JsonPlugin::$plugin;
        $this->embeddings = $plugin->get('embeddings');
    }

    private function getSettings()
    {
        return \jelle\craftjsonplugin\JsonPlugin::$plugin->getSettings();
    }

    public function getAiResponse(string $question, string $sessionId, string $pageUrl = ''): string
    {
        $settings = $this->getSettings();
        $aiProvider = $this->getAiProvider();

        if (is_string($aiProvider)) {
            return $aiProvider;
        }

        $cache = Craft::$app->getCache();

        $provider = $settings->aiProvider ?? 'openai';

        $entriesKey = "chat_entries_{$sessionId}_{$provider}";
        $historyKey = "chat_history_{$sessionId}_{$provider}";
        $providerKey = "chat_provider_{$sessionId}";

        $topK = (int) ($settings->embeddingTopK ?? 3);

        $previousProvider = $cache->get($providerKey);

        if ($previousProvider !== null && $previousProvider !== $provider) {
            $cache->delete("chat_entries_{$sessionId}_{$previousProvider}");
            $cache->delete("chat_history_{$sessionId}_{$previousProvider}");
            $cache->delete("chat_turn_{$sessionId}_{$previousProvider}");

            Craft::info(
                "Provider switch: {$previousProvider} → {$provider} (cleared session cache)",
                'json-plugin'
            );
        }

        $cache->set($providerKey, $provider, 86400);

        $history = $cache->get($historyKey) ?: [];
        $history[] = ['role' => 'user', 'content' => $question];

        $hasEmbeddings = !empty(App::parseEnv($settings->openaiApiKey ?? ''));

        if ($hasEmbeddings) {
            $db = \jelle\craftjsonplugin\JsonPlugin::$plugin->get('db');

            $sessionEntries = $cache->get($entriesKey) ?: [];
            $turnKey = "chat_turn_{$sessionId}_{$provider}";
            $currentTurn = (int) ($cache->get($turnKey) ?: 0) + 1;
            $cache->set($turnKey, $currentTurn, 86400);

            $sessionMap = [];
            foreach ($sessionEntries as $i => $e) {
                $sessionMap[$e['entry']['id']] = $i;
            }

            $minScore = (float) ($settings->embeddingMinScore ?? 0.35);
            $evictFloor = (float) ($settings->embeddingEvictFloor ?? 0.25);
            $graceTurns = (int) ($settings->embeddingGraceTurns ?? 1);
            $ema = (float) ($settings->embeddingEma ?? 0.6);

            $rawScores = $this->embeddings->getTopEntryScores($question, $topK);
            $rawScores = array_combine(
                array_map('intval', array_keys($rawScores)),
                array_values($rawScores)
            );

            $embeddingQuery = $this->buildEmbeddingQuery($question, $history);
            $enrichedScores = ($embeddingQuery !== $question)
                ? $this->embeddings->getTopEntryScores($embeddingQuery, $topK)
                : $rawScores;
            $enrichedScores = array_combine(
                array_map('intval', array_keys($enrichedScores)),
                array_values($enrichedScores)
            );

            foreach ($sessionEntries as &$e) {
                $id = (int) $e['entry']['id'];
                $score = $enrichedScores[$id] ?? $rawScores[$id] ?? null;

                if ($score !== null) {
                    $e['_score'] = $ema * $score + (1 - $ema) * ($e['_score'] ?? $score);
                    $e['_lastSeen'] = $currentTurn;
                } else {
                    $missedTurns = $currentTurn - ($e['_lastSeen'] ?? 0);
                    if ($missedTurns <= $graceTurns) {
                        //$e['_lastSeen'] = $currentTurn;
                    } else {
                        $e['_score'] = (1 - $ema) * ($e['_score'] ?? 0);
                    }
                }
            }
            unset($e);

            $filtered = array_filter($rawScores, fn($s) => $s >= $minScore);
            $scoresForNew = !empty($filtered) ? $filtered : array_slice($rawScores, 0, 1, true);

            $sessionIds = array_map('intval', array_keys($sessionMap));
            $qualifiedIds = array_keys(array_filter($scoresForNew, fn($s) => $s >= $minScore));
            $newIds = array_diff($qualifiedIds, $sessionIds);
            $newEntries = $db->getEntriesByIds($newIds);

            foreach ($newEntries as $e) {
                $e['_score'] = $rawScores[(int) $e['entry']['id']] ?? $minScore;
                $e['_lastSeen'] = $currentTurn;
                $sessionEntries[] = $e;
            }

            $sessionEntries = array_values(
                array_filter($sessionEntries, fn($e) => ($e['_score'] ?? 0) >= $evictFloor)
            );

            $cache->set($entriesKey, $sessionEntries, 86400);

            $contextEntries = $sessionEntries;

            $this->logContext($sessionId, $question, $newEntries, $contextEntries, 'embeddings');

        } else {
            $db = \jelle\craftjsonplugin\JsonPlugin::$plugin->get('db');

            $contextEntries = $cache->get($entriesKey);

            if ($contextEntries === false) {
                $contextEntries = $db->getAllEntries();
                $cache->set($entriesKey, $contextEntries, 86400);
            }

            $newEntries = [];
            $this->logContext($sessionId, $question, $newEntries, $contextEntries, 'full-cache');
        }

        $context = '';

        foreach ($contextEntries as $e) {
            $context .= ($e['entry']['title'] ?? '') . "\n";
            $context .= json_encode($e['fields'] ?? []) . "\n\n";
        }

        $system = ($settings->systemPrompt ?? 'You are a helpful assistant.')
            . "\n\nContext:\n" . $context;

        if ($pageUrl) {
            $system .= "\nUser page: {$pageUrl}";
        }

        if ($settings->useFallbackMessage ?? false) {
            $system .= "\n\nFALLBACK REGEL: Gebruik deze boodschap ALLEEN als het onderwerp of de persoon volledig ontbreekt in de bovenstaande context: \"{$settings->fallbackMessage}\"\n"
                . "Gebruik de fallback NOOIT als het onderwerp of de persoon wél in de context staat — ook niet als de aanname van de gebruiker onjuist is. Corrigeer in dat geval vriendelijk.";
        }

        $maxHistory = (int) ($settings->maxHistoryTurns ?? 6); // 6 = 3 user+assistant pairs

        $answer = $aiProvider->chat(
            array_merge(
                [['role' => 'system', 'content' => $system]],
                array_slice($history, -$maxHistory)
            ),
            [
                'temperature' => (float) ($settings->temperature ?? 0.5),
                'max_tokens' => (int) ($settings->maxTokens ?? 300),
            ]
        );

        if (($settings->useFallbackMessage ?? false) && str_contains($answer, '[FALLBACK]')) {
            $answer = $settings->fallbackMessage;
        }

        $history[] = ['role' => 'assistant', 'content' => $answer];
        $cache->set($historyKey, $history, 86400);

        $this->logStat($sessionId, $settings, $answer);

        return $answer;
    }

    private function buildEmbeddingQuery(string $question, array $history): string
    {
        $context = [];
        $found = 0;
        for ($i = count($history) - 1; $i >= 0 && $found < 2; $i--) {
            if ($history[$i]['role'] === 'assistant') {
                array_unshift($context, $history[$i]['content']);
                $found++;
            }
        }

        if (empty($context)) {
            return $question;
        }

        $contextStr = implode("\n", array_map(fn($c) => mb_substr($c, 0, 150), $context));
        return $contextStr . "\n" . $question;
    }

    private function logContext(
        string $sessionId,
        string $question,
        array $newEntries,
        array $allEntries,
        string $mode
    ): void {
        $newTitles = array_map(
            fn($e) => ($e['entry']['title'] ?? 'untitled') . ' (id:' . ($e['entry']['id'] ?? '?') . ')',
            $newEntries
        );

        $allTitles = array_map(
            fn($e) => ($e['entry']['title'] ?? 'untitled')
            . ' (id:' . ($e['entry']['id'] ?? '?') . ', score:' . ($e['_score'] ?? '–') . ')',
            $allEntries
        );

        Craft::info(
            sprintf(
                '[Chat] Session: %s | Mode: %s | Question: "%s"' . "\n" .
                '  → Fetched this question (%d): %s' . "\n" .
                '  → Total in context (%d): %s',
                $sessionId,
                $mode,
                mb_substr($question, 0, 80),
                count($newEntries),
                empty($newTitles) ? 'none (all already in session)' : implode(', ', $newTitles),
                count($allEntries),
                implode(', ', $allTitles)
            ),
            'json-plugin'
        );
    }

    private function logStat(string $sessionId, $settings, string $answer): void
    {
        $isFallback = ($settings->useFallbackMessage ?? false)
            && $answer === ($settings->fallbackMessage ?? '');

        $nowUtc = new \DateTime('now', new \DateTimeZone('UTC'));

        try {
            Craft::$app->getDb()->createCommand()->insert('{{%jsonplugin_stats}}', [
                'sessionId' => $sessionId,
                'isFallback' => (int) $isFallback,
                'dateAsked' => $nowUtc->format('Y-m-d H:i:s'),
                'dateCreated' => $nowUtc->format('Y-m-d H:i:s'),
                'dateUpdated' => $nowUtc->format('Y-m-d H:i:s'),
                'uid' => StringHelper::UUID(),
            ])->execute();
        } catch (\Throwable $e) {
            Craft::error('Error logging stat: ' . $e->getMessage(), 'json-plugin');
        }
    }

    private function getAiProvider(): AiInterface|string
    {
        $settings = $this->getSettings();

        return match ($settings->aiProvider ?? 'openai') {
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
            return 'Fout: Geen OpenAI API Key geconfigureerd.';
        return new OpenAiProvider($apiKey, $settings->openaiModel ?: 'gpt-4o-mini');
    }

    private function makeGroqProvider($settings): AiInterface|string
    {
        $apiKey = App::parseEnv($settings->groqApiKey);
        if (!$apiKey)
            return 'Fout: Geen Groq API Key geconfigureerd.';
        return new GroqProvider($apiKey, $settings->groqModel ?: 'llama-3.3-70b-versatile');
    }

    private function makeClaudeProvider($settings): AiInterface|string
    {
        $apiKey = App::parseEnv($settings->claudeApiKey);
        if (!$apiKey)
            return 'Fout: Geen Claude API Key geconfigureerd.';
        return new ClaudeProvider($apiKey, $settings->claudeModel ?: 'claude-sonnet-4-5');
    }

    private function makeGeminiProvider($settings): AiInterface|string
    {
        $apiKey = App::parseEnv($settings->geminiApiKey);
        if (!$apiKey)
            return 'Fout: Geen Gemini API Key geconfigureerd.';
        return new GeminiProvider($apiKey, $settings->geminiModel ?: 'gemini-2.0-flash');
    }
}
