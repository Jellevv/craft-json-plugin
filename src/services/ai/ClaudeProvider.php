<?php
namespace jelle\craftjsonplugin\services\ai;

class ClaudeProvider implements AiInterface
{
    public function __construct(private string $apiKey, private string $model) {}

    public function chat(array $messages, array $options): string
    {
        $client = \OpenAI::factory()
            ->withBaseUri('api.anthropic.com/v1')
            ->withApiKey($this->apiKey)
            ->withHeader('anthropic-version', '2023-06-01')
            ->withHeader('x-api-key', $this->apiKey)
            ->make();

        $response = $client->chat()->create([
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.5,
            'max_tokens' => $options['max_tokens'] ?? 300,
        ]);

        return $response->choices[0]->message->content;
    }
}
