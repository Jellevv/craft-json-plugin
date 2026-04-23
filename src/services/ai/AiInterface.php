<?php
namespace jelle\craftjsonplugin\services\ai;

interface AiInterface
{
    public function chat(array $messages, array $options): AiResult;
}
