<?php

namespace jelle\craftjsonplugin\services;

use Craft;
use craft\helpers\App;
use OpenAI;
use yii\base\Component;

class EmbeddingService extends Component
{
    private StorageService $storageService;

    public function init(): void
    {
        parent::init();
        $this->storageService = new StorageService();
    }

    private function getSettings()
    {
        return \jelle\craftjsonplugin\JsonPlugin::getInstance()->getSettings();
    }

    public function generateEmbedding(string $text): array
    {
        $cache = Craft::$app->getCache();

        $cacheKey =
            'query_embedding_' .
            md5($text);

        $cached =
            $cache->get($cacheKey);

        if ($cached) {
            return $cached;
        }

        $settings = $this->getSettings();
        $apiKey =
            App::parseEnv($settings->openaiApiKey);

        if (!$apiKey) {
            return [];
        }

        try {

            $client = OpenAI::client($apiKey);

            $response =
                $client->embeddings()->create([
                    'model' =>
                        $settings->openaiEmbeddingModel
                        ?? 'text-embedding-3-small',
                    'input' => $text,
                ]);

            $embedding =
                $response->embeddings[0]->embedding ?? [];

            if (is_array($embedding)) {

                $cache->set(
                    $cacheKey,
                    $embedding,
                    86400
                );

                return $embedding;
            }

            return [];

        } catch (\Throwable $e) {

            Craft::error(
                "Embedding error: " .
                $e->getMessage(),
                'json-plugin'
            );

            return [];
        }
    }

    public function generateAndSaveEmbeddings(array $entryData): void
    {
        $settings = $this->getSettings();
        $apiKey = App::parseEnv($settings->openaiApiKey);

        if (!$apiKey) {
            return;
        }

        $lines = [];
        foreach ($entryData as $key => $value) {

            if (in_array($key, ['id', 'url'])) {
                continue;
            }

            if (is_array($value)) {
                $text = json_encode($value);
            } else {
                $text = (string) $value;
            }

            $text = trim(strip_tags($text));

            if ($text !== '' && $text !== 'null') {

                // Include FIELD NAME (critical!)
                $lines[] =
                    $key . ": " . $text;
            }
        }

        if (empty($lines)) {
            Craft::warning("No embeddable text found for entry {$entryData['id']}, skipping.", 'json-plugin');
            return;
        }

        try {
            $client = OpenAI::client($apiKey);
            $response = $client->embeddings()->create([
                'model' => $settings->openaiEmbeddingModel ?? 'text-embedding-3-small',
                'input' => implode("\n", $lines),
            ]);

            $flatEmbedding = $response->embeddings[0]->embedding ?? [];
            if (empty($flatEmbedding)) {
                Craft::warning("Empty embedding returned for entry {$entryData['id']}.", 'json-plugin');
                return;
            }

            $embeddings = $this->storageService->getStoredEmbeddings();
            $embeddings[$entryData['id']] = $flatEmbedding;
            $this->storageService->saveStoredEmbeddings($embeddings);

            Craft::info("Saved embedding for entry {$entryData['id']}", 'json-plugin');
        } catch (\Throwable $e) {
            Craft::error("Error generating embedding for entry {$entryData['id']}: " . $e->getMessage(), 'json-plugin');
        }
    }

    public function getTopEntriesByEmbedding(
        string $query,
        array $entries,
        int $top = 5,
        float $threshold = 0.0,
        array $excludeIds = []
    ): array {
        if (empty($entries)) {
            return [];
        }

        // Remove excluded entries first
        $entries = array_filter($entries, fn($e) => !in_array($e['id'], $excludeIds));
        if (empty($entries)) {
            return [];
        }

        $queryEmbedding = $this->generateEmbedding($query);
        if (empty($queryEmbedding)) {
            Craft::warning("Could not generate query embedding — using local keyword fallback.", 'json-plugin');
            $keywordFalling = $this->getTopEntriesByKeyword($query, $entries, $top);
            return !empty($keywordFalling) ? $keywordFalling : array_slice($entries, 0, $top);
        }

        $storedEmbeddings = $this->storageService->getStoredEmbeddings();
        if (empty($storedEmbeddings)) {
            Craft::warning("No stored embeddings found — using local keyword fallback.", 'json-plugin');
            $keywordFalling = $this->getTopEntriesByKeyword($query, $entries, $top);
            return !empty($keywordFalling) ? $keywordFalling : array_slice($entries, 0, $top);
        }

        $entriesById = array_column($entries, null, 'id');
        $similarities = [];

        foreach ($entries as $entry) {
            $id = $entry['id'];
            if (!isset($storedEmbeddings[$id]) || !is_array($storedEmbeddings[$id])) {
                continue;
            }

            $score = $this->cosineSimilarity($queryEmbedding, $storedEmbeddings[$id]);
            if ($score >= $threshold) {
                $similarities[$id] = $score;
            }
        }

        if (empty($similarities)) {
            Craft::warning("No entries passed the similarity threshold ({$threshold}) — falling back to unranked slice.", 'json-plugin');
            return array_slice($entries, 0, $top);
        }

        arsort($similarities);

        // Relative cutoff logic (keep top relevant entries)
        if (count($similarities) > 1) {
            $similarityScores = array_values($similarities);
            $topScore = $similarityScores[0];
            $secondScore = $similarityScores[1];
            $topId = array_key_first($similarities);

            if ($topScore >= 0.7) {
                $relativeCutoff = max($threshold, $topScore * 0.85);
            } elseif ($topScore >= 0.55) {
                $relativeCutoff = max($threshold, $topScore * 0.80);
            } elseif ($topScore >= 0.45) {
                $relativeCutoff = max($threshold, $topScore * 0.75);
            } else {
                $relativeCutoff = $threshold;
            }

            $similarities = array_filter($similarities, fn($score) => $score >= $relativeCutoff);

            if ($topScore >= 0.6 && $secondScore <= $topScore * 0.85) {
                $similarities = [$topId => $topScore];
            }
        }

        $topIds = array_slice(array_keys($similarities), 0, $top);

        Craft::info(
            sprintf(
                "Top %d entry IDs by similarity (threshold: %s): %s",
                count($topIds),
                $threshold,
                implode(', ', array_map(fn($id) => "{$id}(" . round($similarities[$id], 3) . ")", $topIds))
            ),
            'json-plugin'
        );

        return array_values(array_filter(array_map(fn($id) => $entriesById[$id] ?? null, $topIds)));
    }


    public function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            Craft::warning("Embedding dimension mismatch: " . count($a) . " vs " . count($b), 'json-plugin');
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $val) {
            $dot += $val * $b[$i];
            $normA += $val * $val;
            $normB += $b[$i] * $b[$i];
        }

        $denom = sqrt($normA) * sqrt($normB);
        return $denom > 0 ? $dot / $denom : 0.0;
    }
}
