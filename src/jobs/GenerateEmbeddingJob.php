<?php

namespace jelle\craftjsonplugin\jobs;

use craft\queue\BaseJob;
use jelle\craftjsonplugin\JsonPlugin;

class GenerateEmbeddingJob extends BaseJob
{
    public int $entryId;

    public function execute($queue): void
    {
        $db = JsonPlugin::$plugin->get('db');
        $embeddings = JsonPlugin::$plugin->get('embeddings');

        $entries = $db->getEntriesByIds([$this->entryId]);

        if (empty($entries)) {
            return;
        }

        $embeddings->generateAndSaveEmbeddings($entries[0]);
    }

    protected function defaultDescription(): string
    {
        return "Generate embedding for entry {$this->entryId}";
    }
}
