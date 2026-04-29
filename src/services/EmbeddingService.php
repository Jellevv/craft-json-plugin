<?php

namespace jelle\craftjsonplugin\services;

use Craft;
use yii\base\Component;
use OpenAI;
use jelle\craftjsonplugin\JsonPlugin;

class EmbeddingService extends Component
{
    private DbStorageService $db;

    public function init(): void
    {
        parent::init();
        $this->db = JsonPlugin::$plugin->get('db');
    }

    private function getSettings()
    {
        return JsonPlugin::$plugin->getSettings();
    }

    public function generateEmbedding(string $text): array
    {
        $settings = $this->getSettings();
        $apiKey = \craft\helpers\App::parseEnv($settings->openaiApiKey);

        if (!$apiKey) return [];

        $cache    = Craft::$app->getCache();
        $cacheKey = 'jsonplugin_query_emb_' . md5($text);
        $cached   = $cache->get($cacheKey);

        if ($cached !== false) return $cached;

        try {
            $client   = OpenAI::client($apiKey);
            $response = $client->embeddings()->create([
                'model' => $settings->openaiEmbeddingModel ?? 'text-embedding-3-small',
                'input' => $text,
            ]);

            $embedding = $response->embeddings[0]->embedding ?? [];

            if ($embedding) {
                $cache->set($cacheKey, $embedding, 3600);
            }

            return $embedding;

        } catch (\Throwable $e) {
            Craft::error('Embedding error: ' . $e->getMessage(), 'json-plugin');
            return [];
        }
    }

    public function generateAndSaveEmbeddings(array $entry): void
    {
        $lines = [];

        foreach (($entry['entry'] ?? []) as $k => $v) {
            if (in_array($k, ['id', 'url'])) continue;
            $lines[] = "entry.$k: " . $this->flatten($v);
        }

        foreach (($entry['fields'] ?? []) as $k => $v) {
            $lines[] = "field.$k: " . $this->flatten($v);
        }

        if (!$lines) return;

        $embedding = $this->generateEmbedding(implode("\n", $lines));

        if ($embedding) {

    $this->db->saveEmbedding(
        $entry['entry']['id'],
        $embedding
    );

    Craft::$app->getCache()
        ->delete('jsonplugin_all_embeddings');
}

    }
    /**
     * Returns [ entryId => cosineScore ] for the top $top results.
     */
    public function getTopEntryScores(string $query, int $top = 5): array
    {
        $queryEmbedding = $this->generateEmbedding($query);
        if (!$queryEmbedding) return [];

        $settings         = $this->getSettings();
        $includedSections = $settings->includedSections ?? null;

        // Cache key includes sections so a config change gets a fresh pool
        $cacheKey   = 'jsonplugin_all_embeddings_' . md5(json_encode($includedSections));
        $cache      = Craft::$app->getCache();
        $embeddings = $cache->get($cacheKey);

        if ($embeddings === false) {
            $embeddings = $this->db->getAllEmbeddings($includedSections ?: null);
            $cache->set($cacheKey, $embeddings, 3600);
        }

        if (empty($embeddings)) return [];

        $scores = [];
        foreach ($embeddings as $id => $vector) {
            $score = $this->cosine($queryEmbedding, $vector);
            if ($score > 0) {
                $scores[$id] = $score;
            }
        }

        if (empty($scores)) return [];

        arsort($scores);

        return array_slice($scores, 0, $top, true);
    }

    private function flatten($value): string
    {
        if (is_array($value)) return json_encode($value);
        return trim(strip_tags((string) $value));
    }

    private function cosine(array $a, array $b): float
    {
        $dot = $na = $nb = 0;

        foreach ($a as $i => $v) {
            $dot += $v * ($b[$i] ?? 0);
            $na  += $v * $v;
            $nb  += ($b[$i] ?? 0) * ($b[$i] ?? 0);
        }

        return ($na && $nb) ? $dot / (sqrt($na) * sqrt($nb)) : 0;
    }
}
