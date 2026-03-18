<?php
namespace jelle\craftjsonplugin\services\ai;

class GroqProvider implements AiInterface
{
    public function __construct(private string $apiKey, private string $model) {}

    public function chat(array $messages, array $options): string
    {
        $client = \OpenAI::factory()
            ->withBaseUri('api.groq.com/openai/v1')
            ->withApiKey($this->apiKey)
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
