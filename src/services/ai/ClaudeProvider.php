<?php
namespace jelle\craftjsonplugin\services\ai;

class ClaudeProvider implements AiInterface
{
    public function __construct(private string $apiKey, private string $model)
    {
    }

    public function chat(array $messages, array $options): AiResult
    {
        try {
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

            $rawReason = $response->choices[0]->finishReason ?? null;
            return new AiResult(
                content: $response->choices[0]->message->content,
                finishReason: $rawReason === 'max_tokens' ? 'length' : $rawReason,

            );

        } catch (\Throwable $e) {
            return new AiResult(
                content: '',
                success: false,
                error: $e->getMessage()
            );
        }
    }
}
