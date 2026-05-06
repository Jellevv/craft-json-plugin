<?php
namespace jelle\craftjsonplugin\services\ai;

class GeminiProvider implements AiInterface
{
    public function __construct(private string $apiKey, private string $model)
    {
    }

    public function chat(array $messages, array $options): AiResult
    {
        try {
            $systemInstruction = null;
            $contents = [];

            foreach ($messages as $message) {
                if ($message['role'] === 'system') {
                    $systemInstruction = $message['content'];
                } else {
                    $contents[] = [
                        'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                        'parts' => [['text' => $message['content']]]
                    ];
                }
            }

            $body = [
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => $options['temperature'] ?? 0.5,
                    'maxOutputTokens' => $options['max_tokens'] ?? 300,
                ]
            ];

            if ($systemInstruction) {
                $body['systemInstruction'] = [
                    'parts' => [['text' => $systemInstruction]]
                ];
            }

            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode !== 200) {
                \Craft::error('Gemini error response: ' . $response, 'json-plugin');  // add this line
                throw new \Exception("Gemini API fout (HTTP {$httpCode}): " . $response);
            }

            $data = json_decode($response, true);
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Geen antwoord ontvangen.';

            $finishReason = $data['candidates'][0]['finishReason'] ?? null;

            return new AiResult(
                content: $text,
                finishReason: $finishReason === 'MAX_TOKENS' ? 'length' : $finishReason,
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
