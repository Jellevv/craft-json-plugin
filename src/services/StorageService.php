<?php

namespace jelle\craftjsonplugin\services;

use Craft;
use yii\base\Component;

class StorageService extends Component
{
    public function getStoragePath(): string
    {
        $path = Craft::getAlias('@storage/json_plugin/json_data.json');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $path;
    }

    public function getEmbeddingsPath(): string
    {
        $path = Craft::getAlias('@storage/json_plugin/json_embeddings.json');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $path;
    }

    public function getJsonData(): array
    {
        $path = $this->getStoragePath();
        if (!file_exists($path)) {
            return ['entries' => []];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            Craft::warning("Unable to read JSON storage file: {$path}", 'json-plugin');
            return ['entries' => []];
        }

        $decoded = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            Craft::warning("Invalid JSON in storage file: {$path} (" . json_last_error_msg() . ")", 'json-plugin');
            return ['entries' => []];
        }

        return $decoded;
    }

    public function saveJsonData(array $data): bool
    {
        $json = json_encode($data, JSON_PRETTY_PRINT);
        if ($json === false) {
            Craft::error('Failed to encode JSON storage data: ' . json_last_error_msg(), 'json-plugin');
            return false;
        }

        $result = @file_put_contents($this->getStoragePath(), $json);
        if ($result === false) {
            Craft::error('Failed to write JSON storage file.', 'json-plugin');
            return false;
        }

        return true;
    }

    public function getStoredEmbeddings(): array
    {
        $path = $this->getEmbeddingsPath();
        if (!file_exists($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            Craft::warning("Unable to read embedding storage file: {$path}", 'json-plugin');
            return [];
        }

        $decoded = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            Craft::warning("Invalid JSON in embedding storage file: {$path} (" . json_last_error_msg() . ")", 'json-plugin');
            return [];
        }

        return $decoded;
    }

    public function saveStoredEmbeddings(array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT);
        if ($json === false) {
            Craft::error('Failed to encode embeddings data: ' . json_last_error_msg(), 'json-plugin');
            return;
        }

        $result = @file_put_contents($this->getEmbeddingsPath(), $json);
        if ($result === false) {
            Craft::error('Failed to write embedding storage file.', 'json-plugin');
        }
    }
}
