<?php

namespace App\Services\Ai;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqClientService
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private string $defaultModel;

    public function __construct()
    {
        $this->apiKey = (string) config('kiuq_ai.groq_api_key', env('GROQ_API_KEY', ''));
        $this->baseUrl = rtrim((string) config('kiuq_ai.groq_base_url', 'https://api.groq.com/openai/v1'), '/');
        $this->timeout = (int) config('kiuq_ai.timeout', 25);
        $this->defaultModel = (string) config('kiuq_ai.groq_default_model', 'llama-3.3-70b-versatile');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Map model aliases to officially supported Groq model IDs.
     */
    public function resolveModel(?string $model): string
    {
        $m = $model ?: $this->defaultModel;
        if (str_contains($m, 'qwen')) {
            return 'llama-3.3-70b-versatile';
        }
        return match ($m) {
            'openai/gpt-oss-120b', 'gpt-oss-120b', 'groq-120b', 'qwen/qwen3.8-27b', 'qwen-2.5-coder-32b' => 'llama-3.3-70b-versatile',
            'openai/gpt-oss-20b', 'gpt-oss-20b', 'groq-20b'   => 'llama-3.1-8b-instant',
            default => $m,
        };
    }

    /**
     * Sanitize string to guarantee valid UTF-8 encoding.
     */
    public function sanitizeUtf8String(?string $str): string
    {
        if ($str === null || $str === '') {
            return '';
        }
        $str = str_replace("\0", '', $str);
        if (!mb_check_encoding($str, 'UTF-8')) {
            $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
        }
        return $str;
    }

    /**
     * Recursively sanitize payload to guarantee valid UTF-8 encoding.
     */
    public function sanitizeUtf8(mixed $data): mixed
    {
        if (is_string($data)) {
            return $this->sanitizeUtf8String($data);
        }
        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $k => $v) {
                $cleanKey = is_string($k) ? $this->sanitizeUtf8String($k) : $k;
                $cleaned[$cleanKey] = $this->sanitizeUtf8($v);
            }
            return $cleaned;
        }
        return $data;
    }

    /**
     * Convert Gemini contents format to OpenAI standard messages format.
     */
    public function formatContentsToMessages(array $contents, ?string $systemInstruction = null): array
    {
        $messages = [];
        $systemPrompt = $systemInstruction ?? config('kiuq_ai.system_prompt');

        if (!empty($systemPrompt)) {
            $messages[] = [
                'role'    => 'system',
                'content' => $this->sanitizeUtf8String($systemPrompt),
            ];
        }

        // Enforce strict character budget so request never exceeds Groq rate limits (e.g. 7000 ITPM limit)
        $totalChars = 0;
        foreach ($contents as $c) {
            foreach ($c['parts'] ?? [] as $p) {
                $totalChars += mb_strlen($p['text'] ?? '', 'UTF-8');
            }
        }
        if ($totalChars > 8000 && count($contents) > 2) {
            $contents = array_slice($contents, -2);
        } elseif (count($contents) > 6) {
            $contents = array_slice($contents, -6);
        }

        $totalItems = count($contents);
        foreach ($contents as $idx => $item) {
            $role = ($item['role'] === 'model' || $item['role'] === 'assistant') ? 'assistant' : 'user';
            $textParts = [];

            if (!empty($item['parts']) && is_array($item['parts'])) {
                foreach ($item['parts'] as $part) {
                    if (isset($part['text'])) {
                        $textParts[] = $this->sanitizeUtf8String($part['text']);
                    }
                }
            }

            $content = implode("\n", $textParts);

            // If it is an earlier historical message and lengthy, trim safely using multibyte UTF-8
            if ($idx < ($totalItems - 1) && mb_strlen($content, 'UTF-8') > 1000) {
                $content = mb_substr($content, 0, 800, 'UTF-8') . "\n... [context trimmed for speed] ...";
            }

            if (!empty($content)) {
                $messages[] = [
                    'role'    => $role,
                    'content' => $this->sanitizeUtf8String($content),
                ];
            }
        }

        return $messages;
    }

    /**
     * Convert Gemini function declarations to OpenAI-compatible tools schema for Groq.
     */
    public function convertToolsToOpenAi(array $geminiTools): array
    {
        $openAiTools = [];
        foreach ($geminiTools as $tool) {
            $name = $tool['name'] ?? '';
            if (empty($name)) continue;

            $props = [];
            if (!empty($tool['parameters']['properties']) && is_array($tool['parameters']['properties'])) {
                foreach ($tool['parameters']['properties'] as $pKey => $pDef) {
                    $cleanDef = $pDef;
                    if (isset($cleanDef['type'])) {
                        $cleanDef['type'] = strtolower($cleanDef['type']);
                    }
                    $props[$pKey] = $cleanDef;
                }
            }

            $params = [
                'type'       => strtolower($tool['parameters']['type'] ?? 'object'),
                'properties' => !empty($props) ? $props : (object)[],
            ];
            if (!empty($tool['parameters']['required'])) {
                $params['required'] = array_values($tool['parameters']['required']);
            }

            $openAiTools[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => $name,
                    'description' => $tool['description'] ?? '',
                    'parameters'  => $params,
                ],
            ];
        }
        return $openAiTools;
    }

    /**
     * Generate content via Groq API.
     */
    public function generateContent(
        array $contents,
        array $tools = [],
        ?string $systemInstruction = null,
        ?string $modelOverride = null
    ): array {
        if (!$this->isConfigured()) {
            throw new Exception("Groq API key is not configured. Please add GROQ_API_KEY in your .env file.");
        }

        $model = $this->resolveModel($modelOverride);
        $messages = $this->formatContentsToMessages($contents, $systemInstruction);

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => (float) config('kiuq_ai.temperature', 0.7),
            'max_tokens'  => (int) config('kiuq_ai.max_tokens', 4096),
        ];

        if (!empty($tools)) {
            $payload['tools'] = $this->convertToolsToOpenAi($tools);
            $payload['tool_choice'] = 'auto';
        }

        $payload = $this->sanitizeUtf8($payload);
        $jsonPayload = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);

        $url = "{$this->baseUrl}/chat/completions";

        $httpOptions = [
            'verify' => file_exists('/etc/ssl/cert.pem') ? '/etc/ssl/cert.pem' : false,
        ];

        try {
            $response = Http::withOptions($httpOptions)->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type'  => 'application/json',
            ])->withBody($jsonPayload, 'application/json')->timeout($this->timeout)->post($url);

            if (!$response->successful()) {
                $err = $response->json('error.message') ?? $response->body();
                // If 413 (too large) or 429 (rate limit), auto-recover seamlessly with trimmed context
                if ($response->status() === 413 || $response->status() === 429) {
                    Log::warning("Groq hit {$response->status()} ({$err}). Auto-retrying with single latest prompt and llama-3.1-8b-instant.");
                    $lastTurn = end($contents);
                    $singleTurn = [
                        ['role' => 'user', 'parts' => [['text' => $this->sanitizeUtf8String($lastTurn['parts'][0]['text'] ?? 'Hello')]]]
                    ];
                    return $this->generateContent(
                        $singleTurn,
                        [],
                        "You are KIUQ AI. Be accurate, fast, and helpful.",
                        'llama-3.1-8b-instant'
                    );
                }
                Log::error("Groq API Error [{$response->status()}]: {$err}");
                throw new Exception("Groq API Error ({$response->status()}): {$err}");
            }

            $json = $response->json();
            $choice = $json['choices'][0] ?? null;
            $text = $choice['message']['content'] ?? '';

            $functionCalls = [];
            if (!empty($choice['message']['tool_calls'])) {
                foreach ($choice['message']['tool_calls'] as $tc) {
                    if (($tc['type'] ?? '') === 'function') {
                        $fnName = $tc['function']['name'] ?? '';
                        $rawArgs = $tc['function']['arguments'] ?? '{}';
                        $args = is_array($rawArgs) ? $rawArgs : (json_decode($rawArgs, true) ?: []);
                        if (!empty($fnName)) {
                            $functionCalls[] = [
                                'name' => $fnName,
                                'args' => $args,
                            ];
                        }
                    }
                }
            }

            return [
                'text'           => $text ?: (empty($functionCalls) ? 'No response returned by Groq.' : ''),
                'function_calls' => $functionCalls,
                'role'           => 'model',
                'raw'            => $json,
            ];
        } catch (Exception $e) {
            Log::error("GroqClientService::generateContent Exception: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Stream chat content from Groq via Server-Sent Events (SSE).
     */
    public function streamContent(
        array $contents,
        array $tools = [],
        ?string $systemInstruction = null,
        ?string $modelOverride = null,
        ?callable $onChunk = null
    ): array {
        if (!$this->isConfigured()) {
            throw new Exception("Groq API key is not configured. Please add GROQ_API_KEY in your .env file.");
        }

        $model = $this->resolveModel($modelOverride);
        $messages = $this->formatContentsToMessages($contents, $systemInstruction);

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => (float) config('kiuq_ai.temperature', 0.7),
            'max_tokens'  => (int) config('kiuq_ai.max_tokens', 4096),
            'stream'      => true,
        ];

        if (!empty($tools)) {
            $payload['tools'] = $this->convertToolsToOpenAi($tools);
            $payload['tool_choice'] = 'auto';
        }

        $payload = $this->sanitizeUtf8($payload);
        $jsonPayload = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);

        $url = "{$this->baseUrl}/chat/completions";
        $fullText = '';
        $rawBuffer = '';
        $streamedToolCalls = [];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->apiKey}",
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        if (file_exists('/etc/ssl/cert.pem')) {
            curl_setopt($ch, CURLOPT_CAINFO, '/etc/ssl/cert.pem');
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$fullText, &$rawBuffer, &$streamedToolCalls, $onChunk) {
            $rawBuffer .= $chunk;
            while (($pos = strpos($rawBuffer, "\n")) !== false) {
                $line = trim(substr($rawBuffer, 0, $pos));
                $rawBuffer = substr($rawBuffer, $pos + 1);

                if (str_starts_with($line, 'data: ')) {
                    $jsonStr = trim(substr($line, 6));
                    if ($jsonStr === '[DONE]') continue;
                    $data = json_decode($jsonStr, true);
                    if (!$data) continue;

                    $delta = $data['choices'][0]['delta'] ?? [];
                    if (!empty($delta['content'])) {
                        $fullText .= $delta['content'];
                        if ($onChunk) {
                            $onChunk($delta['content'], false, null);
                        }
                    }

                    if (!empty($delta['tool_calls'])) {
                        foreach ($delta['tool_calls'] as $tc) {
                            $tcIdx = $tc['index'] ?? 0;
                            if (!isset($streamedToolCalls[$tcIdx])) {
                                $streamedToolCalls[$tcIdx] = [
                                    'name'      => $tc['function']['name'] ?? '',
                                    'arguments' => '',
                                ];
                            }
                            if (!empty($tc['function']['name'])) {
                                $streamedToolCalls[$tcIdx]['name'] = $tc['function']['name'];
                            }
                            if (!empty($tc['function']['arguments'])) {
                                $streamedToolCalls[$tcIdx]['arguments'] .= $tc['function']['arguments'];
                            }
                        }
                    }
                }
            }
            return strlen($chunk);
        });

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $functionCalls = [];
        foreach ($streamedToolCalls as $stc) {
            if (!empty($stc['name'])) {
                $args = json_decode($stc['arguments'] ?: '{}', true) ?: [];
                $functionCalls[] = [
                    'name' => $stc['name'],
                    'args' => $args,
                ];
            }
        }

        if (!$success || $httpCode !== 200 || (empty($fullText) && empty($functionCalls))) {
            Log::warning("Groq streaming failed HTTP {$httpCode} ({$curlError}), attempting resilient sync fallback with llama-3.3-70b-versatile");
            $lastTurn = end($contents);
            $singleTurn = [
                ['role' => 'user', 'parts' => [['text' => $lastTurn['parts'][0]['text'] ?? 'Hello']]]
            ];
            return $this->generateContent($singleTurn, $tools, "You are KIUQ AI. Be accurate, fast, and helpful.", 'llama-3.3-70b-versatile');
        }

        if ($onChunk) {
            $onChunk('', true, !empty($functionCalls) ? $functionCalls[0] : null);
        }

        return [
            'text'           => $fullText,
            'function_calls' => $functionCalls,
            'role'           => 'model',
        ];
    }
}

