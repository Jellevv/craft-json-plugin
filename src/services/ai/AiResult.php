<?php

namespace jelle\craftjsonplugin\services\ai;

class AiResult
{
    public function __construct(
        public string $content,
        public bool $success = true,
        public ?string $error = null
    ) {}
}

