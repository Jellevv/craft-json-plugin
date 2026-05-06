<?php
namespace jelle\craftjsonplugin\services\ai;

class OpenAiProvider implements AiInterface
{
    public function __construct(private string $apiKey, private string $model)
    {
    }

    public function chat(array $messages, array $options): AiResult
    {
        try {
            $client = \OpenAI::client($this->apiKey);

            $response = $client->chat()->create([
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => $options['temperature'] ?? 0.5,
                'max_tokens' => $options['max_tokens'] ?? 300,
            ]);

            $choice = $response->choices[0];

            return new AiResult(
                content: $choice->message->content,
                finishReason: $choice->finishReason ?? $choice->finish_reason ?? null
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
