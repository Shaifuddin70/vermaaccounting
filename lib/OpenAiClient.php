<?php

declare(strict_types=1);

/**
 * Minimal OpenAI Chat Completions client (JSON responses).
 * API key and model come from config.local.php under 'openai'.
 */
final class OpenAiClient
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    private string $apiKey;
    private string $model;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $config = app_config();
        $this->apiKey = trim((string) ($apiKey ?? ($config['openai']['api_key'] ?? '')));
        $this->model = trim((string) ($model ?? ($config['openai']['model'] ?? 'gpt-4o-mini'))) ?: 'gpt-4o-mini';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && !str_starts_with($this->apiKey, 'sk-YOUR_');
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * Chat with response_format json_object. Returns the decoded JSON object from the assistant message.
     *
     * @param list<array{role: string, content: string}> $messages
     * @return array<string, mixed>
     */
    public function chatJson(array $messages, float $temperature = 0.7, int $timeout = 120): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('OpenAI API key is not configured. Add openai.api_key in config.local.php.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for OpenAI.');
        }

        $payload = [
            'model' => $this->model,
            'temperature' => $temperature,
            'response_format' => ['type' => 'json_object'],
            'messages' => $messages,
        ];

        $ch = curl_init(self::ENDPOINT);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize HTTP client for OpenAI.');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException('OpenAI request failed: ' . $error);
        }
        if (!is_string($body) || $body === '') {
            throw new RuntimeException('OpenAI returned an empty response.');
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('OpenAI returned invalid JSON (HTTP ' . $status . ').');
        }
        if ($status >= 400) {
            $msg = (string) ($decoded['error']['message'] ?? ('HTTP ' . $status));
            throw new RuntimeException('OpenAI API error: ' . $msg);
        }

        $content = (string) ($decoded['choices'][0]['message']['content'] ?? '');
        if ($content === '') {
            throw new RuntimeException('OpenAI returned an empty assistant message.');
        }
        $json = json_decode($content, true);
        if (!is_array($json)) {
            throw new RuntimeException('OpenAI assistant message was not valid JSON.');
        }
        return $json;
    }
}
