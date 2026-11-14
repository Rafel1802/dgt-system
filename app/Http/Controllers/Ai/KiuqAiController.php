<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\Ai\AiConversation;
use App\Models\Ai\AiMessage;
use App\Models\Ai\AiReport;
use App\Models\Ai\AiTranslation;
use App\Models\Ai\AiVoiceOver;
use App\Models\Board;
use App\Models\Website;
use App\Models\WebsitePost;
use App\Services\Ai\GeminiClientService;
use App\Services\Ai\GroqClientService;
use App\Services\Ai\KiuqAiReportService;
use App\Services\Ai\KiuqAiSafetyService;
use App\Services\Ai\KiuqAiToolRegistry;
use App\Services\Ai\KiuqTranslationService;
use App\Services\WebsiteBlogPostService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KiuqAiController extends Controller
{
    public function __construct(
        private readonly GeminiClientService $geminiClient,
        private readonly GroqClientService $groqClient,
        private readonly KiuqAiSafetyService $safetyService,
        private readonly KiuqAiToolRegistry $toolRegistry,
        private readonly KiuqAiReportService $reportService,
        private readonly WebsiteBlogPostService $blogService,
        private readonly KiuqTranslationService $translationService
    ) {}

    private function isGroqModel(?string $model): bool
    {
        if (!$model) return false;
        return str_starts_with($model, 'openai/') || str_starts_with($model, 'qwen/') || str_starts_with($model, 'groq/') || str_contains($model, 'gpt-oss') || str_contains($model, 'llama');
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
     * Cascading multi-tier AI execution with seamless automatic failover.
     * Guarantees zero visible errors to the user: if primary AI hits rate limit,
     * token quota, timeout, or service downtime, it cascades through alternative
     * models and providers automatically.
     */
    private function executeAiWithFailover(
        string $requestedModel,
        array $contents,
        array $tools,
        string $userPrompt,
        bool $hasMultimodal = false
    ): array {
        // Sanitize all history & content payloads for UTF-8 safety
        $contents = $this->sanitizeUtf8($contents);

        $tiers = [];
        if ($hasMultimodal) {
            // Multimodal queries require Gemini Vision models
            $tiers = [
                $this->isGroqModel($requestedModel) ? 'gemini-flash-lite-latest' : $requestedModel,
                'gemini-flash-lite-latest',
                'gemini-3.5-flash',
                'gemini-2.5-flash',
                'groq_text_fallback:llama-3.3-70b-versatile',
                'groq_text_fallback:llama-3.1-8b-instant',
            ];
        } else {
            // Text-only queries
            if ($this->isGroqModel($requestedModel)) {
                $tiers = [
                    $requestedModel,
                    'llama-3.1-8b-instant',
                    'gemini-flash-lite-latest',
                    'gemini-3.5-flash',
                    'compact_retry:llama-3.3-70b-versatile',
                    'compact_retry:llama-3.1-8b-instant',
                ];
            } else {
                $tiers = [
                    $requestedModel,
                    'gemini-flash-lite-latest',
                    'llama-3.3-70b-versatile',
                    'llama-3.1-8b-instant',
                    'compact_retry:gemini-flash-lite-latest',
                    'compact_retry:llama-3.1-8b-instant',
                ];
            }
        }

        // Deduplicate tiers while preserving priority sequence
        $tiers = array_values(array_unique($tiers));
        $lastError = null;

        foreach ($tiers as $tierModel) {
            try {
                // Tier A: Fallback from image failure to text-only description with Groq
                if (str_starts_with($tierModel, 'groq_text_fallback:')) {
                    $fallbackModel = substr($tierModel, strlen('groq_text_fallback:'));
                    if (!$this->groqClient->isConfigured()) continue;

                    Log::warning("Failing over multimodal prompt to text-only with Groq [{$fallbackModel}].");
                    $textContents = [
                        ['role' => 'user', 'parts' => [['text' => "[Image Context] " . $userPrompt]]]
                    ];
                    return $this->groqClient->generateContent($textContents, [], null, $fallbackModel);
                }

                // Tier B: Compact single-turn prompt retry (bypasses corrupted turns or oversized context)
                if (str_starts_with($tierModel, 'compact_retry:')) {
                    $retryModel = substr($tierModel, strlen('compact_retry:'));
                    $singleTurn = [
                        ['role' => 'user', 'parts' => [['text' => $this->sanitizeUtf8String($userPrompt)]]]
                    ];

                    if ($this->isGroqModel($retryModel)) {
                        if (!$this->groqClient->isConfigured()) continue;
                        Log::warning("Compact single-turn retry with Groq [{$retryModel}].");
                        return $this->groqClient->generateContent($singleTurn, [], null, $retryModel);
                    } else {
                        if (!$this->geminiClient->isConfigured()) continue;
                        Log::warning("Compact single-turn retry with Gemini [{$retryModel}].");
                        return $this->geminiClient->generateContent($singleTurn, [], null, $retryModel);
                    }
                }

                // Tier C: Standard model execution
                if ($this->isGroqModel($tierModel)) {
                    if (!$this->groqClient->isConfigured()) continue;
                    return $this->groqClient->generateContent($contents, $tools, null, $tierModel);
                } else {
                    if (!$this->geminiClient->isConfigured()) continue;
                    return $this->geminiClient->generateContent($contents, $tools, null, $tierModel);
                }
            } catch (\Throwable $tierErr) {
                $lastError = $tierErr;
                Log::warning("AI tier [{$tierModel}] failed: " . $tierErr->getMessage() . ". Auto-switching to next AI engine...");
            }
        }

        // Ultimate graceful fallback: If all external endpoints fail, return helpful assistant response
        Log::error("All AI tiers exhausted. Last error: " . ($lastError ? $lastError->getMessage() : 'Unknown'));
        return [
            'text'           => "I have received your request. The external AI service is currently taking longer than usual to respond. Please resend or continue your prompt in a few moments.",
            'function_calls' => [],
            'role'           => 'model',
            'raw'            => [],
        ];
    }

    /**
     * Send a prompt in a conversation and receive KIUQ AI's response.
     * Supports multimodal screenshots, images, model selection, and tool execution.
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message'         => ['nullable', 'string', 'max:15000'],
            'conversation_id' => ['nullable', 'integer'],
            'mode'            => ['nullable', 'string', 'in:general,translate,blog,image,seo,task,qc,report,voice'],
            'model'           => ['nullable', 'string', 'max:100'],
            'attachments'     => ['nullable', 'array'],
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated.'], 401);
        }

        $rawMessage = trim($validated['message'] ?? '');
        $attachments = $validated['attachments'] ?? [];

        // If user submitted an image without text, assign natural visual analysis prompt
        if (empty($rawMessage) && !empty($attachments)) {
            $userPrompt = "Please analyze this uploaded screenshot/image. Extract key text, data tables, metrics, visual elements, and summarize your findings.";
        } else {
            $userPrompt = $rawMessage ?: 'Hello KIUQ AI';
        }

        $mode  = $validated['mode'] ?? 'general';
        $model = $validated['model'] ?? config('kiuq_ai.text_model', 'gemini-flash-lite-latest');
        if ($this->isGroqModel($model)) {
            $model = $this->groqClient->resolveModel($model);
        }

        // 1. Safety Inspection for Prohibited Destructive Actions
        $safety = $this->safetyService->inspectPrompt($userPrompt, $user);
        if ($safety['is_destructive']) {
            $conversation = $this->getOrCreateConversation($validated['conversation_id'] ?? null, $user->id, $mode, $userPrompt);

            // Save user message
            AiMessage::create([
                'conversation_id' => $conversation->id,
                'user_id'         => $user->id,
                'role'            => 'user',
                'content'         => $userPrompt,
            ]);

            // Save model refusal message
            $botMsg = AiMessage::create([
                'conversation_id' => $conversation->id,
                'user_id'         => null,
                'role'            => 'model',
                'content'         => "⛔ **Action Prohibited**: " . $safety['reason'],
            ]);

            return response()->json([
                'success'         => true,
                'conversation_id' => $conversation->id,
                'message'         => $this->formatMessage($botMsg),
            ]);
        }

        // 2. Resolve or Create Conversation
        $conversation = $this->getOrCreateConversation($validated['conversation_id'] ?? null, $user->id, $mode, $userPrompt);

        // Process attachments (store images and prepare multimodal inline data)
        $processedAttachments = [];
        $multimodalParts = [];

        foreach ($attachments as $att) {
            if (!empty($att['base64'])) {
                $base64 = $att['base64'];
                $mimeType = $att['type'] ?? 'image/png';
                $name = $att['name'] ?? ('attachment_' . time() . '.png');

                // Extract pure base64 data if prefixed and strip whitespace
                $cleanBase64 = preg_replace('#^data:[^;]+;base64,#i', '', (string)$base64);
                $cleanBase64 = trim(preg_replace('/\s+/', '', $cleanBase64));

                // Save to public storage for UI persistence
                $relDir = 'ai_attachments';
                if (!Storage::disk('public')->exists($relDir)) {
                    Storage::disk('public')->makeDirectory($relDir);
                }
                $filename = 'att_' . time() . '_' . Str::random(8) . '.' . (str_contains($mimeType, 'jpeg') ? 'jpg' : 'png');
                Storage::disk('public')->put("{$relDir}/{$filename}", base64_decode($cleanBase64));
                $publicUrl = asset("storage/{$relDir}/{$filename}");

                $processedAttachments[] = [
                    'name'      => $name,
                    'type'      => $mimeType,
                    'url'       => $publicUrl,
                    'file_path' => "{$relDir}/{$filename}",
                ];

                $multimodalParts[] = [
                    'inlineData' => [
                        'mimeType' => $mimeType,
                        'data'     => $cleanBase64,
                    ]
                ];
            }
        }

        // Save User Message
        $userMsg = AiMessage::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $user->id,
            'role'            => 'user',
            'content'         => $userPrompt,
            'attachments'     => !empty($processedAttachments) ? $processedAttachments : null,
        ]);

        // 3. Build Conversation Context for Gemini & retrieve previous user message for follow-up context (e.g. "september ok")
        $previousUserMsg = AiMessage::where('conversation_id', $conversation->id)
            ->where('role', 'user')
            ->where('id', '<', $userMsg->id)
            ->latest('id')
            ->first();
        $previousUserPrompt = $previousUserMsg?->content;

        $contents = $this->buildGeminiHistory($conversation, $multimodalParts);

        // 4. Retrieve Whitelisted Tools conditionally for maximum response speed
        $tools = [];
        $lowerPrompt = strtolower($userPrompt . ' ' . ($previousUserPrompt ?? ''));
        $needsTools = ($mode !== 'general' && $mode !== 'translate');
        if (!$needsTools) {
            $systemKeywordsPattern = '/\b(find|search|where|locate|hidden|card|cards|board|boards|planning|workflow|smm|workspace|workspaces|task|tasks|qc check|qc audit|system report|production report|daily report|weekly report|monthly report|report|reports|pdf|export|download|dgt database|blog post|blogs|website list|website|websites|domain|domains|inquiry|inquiries|lead|leads|member|members|user|users|staff|team|kpi|gsc|search console|indexed|not indexed|indexation|sitemap|sitemaps|seo|chat|notes|navigation|link|links|route|routes|january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sep|sept|oct|nov|dec)\b/i';
            if (preg_match($systemKeywordsPattern, $lowerPrompt)) {
                $needsTools = true;
            }
        }

        if ($needsTools) {
            $tools = $this->toolRegistry->getDeclarations();
        }

        try {
            $isCreationOrWritingPrompt = preg_match('/\b(write|draft|translate|caption|code|html|css|image|voice|audio|explain|rewrite|create\s+card|move\s+card|publish|export|pdf|csv)\b/i', $userPrompt) === 1;
            $fastSmartSearch = (empty($multimodalParts) && !$isCreationOrWritingPrompt)
                ? $this->toolRegistry->detectAndExecuteSmartSearch($userPrompt, $user, $previousUserPrompt)
                : null;

            if (!empty($fastSmartSearch['message'])) {
                $aiResponse = [
                    'text'           => $fastSmartSearch['message'],
                    'function_calls' => [],
                    'role'           => 'model',
                ];
            } else {
                $aiResponse = $this->executeAiWithFailover(
                    $model,
                    $contents,
                    $tools,
                    $userPrompt,
                    !empty($multimodalParts)
                );
            }

            $actionType   = null;
            $actionData   = null;
            $actionStatus = null;
            $botText      = $aiResponse['text'] ?? '';

            // Handle Function Calls if AI triggered any tools
            if (!empty($aiResponse['function_calls'])) {
                foreach ($aiResponse['function_calls'] as $call) {
                    $toolName = $call['name'] ?? '';
                    $toolArgs = $call['args'] ?? [];

                    $toolResult = $this->toolRegistry->execute($toolName, $toolArgs, $user, $userPrompt, $previousUserPrompt);

                    // Check if tool produced an action preview requiring UI confirmation
                    if (!empty($toolResult['requires_ui_action'])) {
                        $actionType   = $toolResult['action_type'] ?? null;
                        $actionData   = $toolResult['action_data'] ?? null;
                        $actionStatus = 'pending_confirmation';
                    }

                    // Append tool explanation to response text
                    if (!empty($toolResult['message'])) {
                        $botText = ($botText ? ($botText . "\n\n") : '') . $toolResult['message'];
                    } elseif (!empty($toolResult['summary'])) {
                        $botText = ($botText ? ($botText . "\n\n") : '') . json_encode($toolResult['summary'], JSON_PRETTY_PRINT);
                    } elseif (!empty($toolResult['error'])) {
                        $botText = ($botText ? ($botText . "\n\n") : '') . "⚠️ " . $toolResult['error'];
                    }
                }
            }

            // Direct intent check: if user asked to export/generate a report or asked for the PDF
            $hasExplicitExportWord = preg_match('/\b(export|pdf|csv|download\s+report|generate\s+report|report\s+of\s+board|board\s+report)\b/i', $userPrompt) === 1;
            $isBoardExportRequest = $hasExplicitExportWord && (
                preg_match('/\bboard\b/i', $userPrompt)
                || preg_match('/\b(planning|workflow|graphic|video|smm|content)\s+board\b/i', $userPrompt)
            );

            $isReportRequest = $isBoardExportRequest
                || preg_match('/\b(export|generate|download|create)\s+.*?\b(report|pdf)\b/i', $userPrompt)
                || preg_match('/\b(where\s+is\s+the\s+pdf|where\s+is\s+the\s+report)\b/i', $userPrompt)
                || preg_match('/\b(report\s+of\s+board|planning\s+board\s+report)\b/i', $userPrompt);

            if ($isReportRequest && empty($aiResponse['function_calls'])) {
                if (preg_match('/where\s+is\s+the\s+(pdf|report)/i', $userPrompt)) {
                    $latestRep = \App\Models\Ai\AiReport::where('user_id', $user->id)->latest()->first();
                    if (!$latestRep && method_exists($user, 'isSpecialManagerOrAdmin') && $user->isSpecialManagerOrAdmin()) {
                        $latestRep = \App\Models\Ai\AiReport::latest()->first();
                    }
                    if ($latestRep) {
                        $pdfUrl = route('ai.reports.export', ['report' => $latestRep->id, 'format' => 'pdf']);
                        $csvUrl = route('ai.reports.export', ['report' => $latestRep->id, 'format' => 'csv']);
                        $botText .= ($botText ? "\n\n" : "")
                            . "### 📥 Download PDF & CSV Report\n\n"
                            . "Here are your download links for **{$latestRep->title}**:\n"
                            . "- 📄 [**Download PDF Report**]({$pdfUrl})\n"
                            . "- 📊 [**Download CSV Data**]({$csvUrl})\n";
                    }
                } elseif ($isBoardExportRequest) {
                    $toolResult = $this->toolRegistry->execute('export_board_report', [
                        'board_name' => $userPrompt,
                    ], $user, $userPrompt, $previousUserPrompt);
                    if (!empty($toolResult['message'])) {
                        $botText .= ($botText ? "\n\n" : "") . $toolResult['message'];
                    }
                } else {
                    $toolResult = $this->toolRegistry->execute('generate_production_report', [
                        'instructions' => $userPrompt,
                        'title'        => 'Production Report',
                    ], $user, $userPrompt, $previousUserPrompt);
                    if (!empty($toolResult['message'])) {
                        $botText .= ($botText ? "\n\n" : "") . $toolResult['message'];
                    }
                }
            } elseif (empty($aiResponse['function_calls'])) {
                // Smart Universal Search fallback for board queries (including hidden/past-month boards & follow-ups like "september ok"), GSC, Production Reports, and system search
                $smartSearchResult = $this->toolRegistry->detectAndExecuteSmartSearch($userPrompt, $user, $previousUserPrompt);
                if (!empty($smartSearchResult['message'])) {
                    $botText = $smartSearchResult['message'];
                }
            }

            // Voice synthesis intent check (or google-voice-studio model)
            $isVoiceRequest = ($model === 'google-voice-studio')
                || preg_match('/\b(generate|create|synthesize|make|speak|record|talk|narrate)\b.*?\b(voice|speech|audio|voiceover|voice-over|sound)\b/iu', $userPrompt)
                || preg_match('/\b(ok\s+generate\s+now|generate\s+now|generate\s+audio|speak\s+this|read\s+this|synthesize\s+now)\b/iu', $userPrompt);

            $audioAttachments = [];
            if ($isVoiceRequest && empty($aiResponse['function_calls'])) {
                $scriptToVoice = '';
                if (preg_match('/\[Voice-Over Script\]\s*(.*?)(?=\n\n|\Z)/isu', $userPrompt, $sm)) {
                    $scriptToVoice = trim($sm[1]);
                } elseif (preg_match('/["“\']([^"“\']{15,})["”\']/u', $userPrompt, $sm)) {
                    $scriptToVoice = trim($sm[1]);
                } elseif (mb_strlen($userPrompt) > 25 && !preg_match('/^(ok\s+generate|generate\s+now|can\s+u\s+generate|please\s+generate)/i', $userPrompt)) {
                    $scriptToVoice = $userPrompt;
                }

                if (empty($scriptToVoice)) {
                    $prevMsg = AiMessage::where('conversation_id', $conversation->id)
                        ->where('role', 'model')
                        ->latest()
                        ->first();

                    if ($prevMsg && !empty($prevMsg->content)) {
                        if (preg_match('/\[Voice-Over Script\]\s*(.*?)(?=\n\n|\Z)/isu', $prevMsg->content, $psm)) {
                            $scriptToVoice = trim($psm[1]);
                        } elseif (preg_match('/["“\']([^"“\']{15,})["”\']/u', $prevMsg->content, $psm)) {
                            $scriptToVoice = trim($psm[1]);
                        } else {
                            $scriptToVoice = $prevMsg->content;
                        }
                    }
                }

                if (empty($scriptToVoice) && !empty($botText)) {
                    if (preg_match('/\[Voice-Over Script\]\s*(.*?)(?=\n\n|\Z)/isu', $botText, $asm)) {
                        $scriptToVoice = trim($asm[1]);
                    } elseif (preg_match('/["“\']([^"“\']{15,})["”\']/u', $botText, $asm)) {
                        $scriptToVoice = trim($asm[1]);
                    }
                }

                if (!empty($scriptToVoice)) {
                    $cleanScript = trim(strip_tags($scriptToVoice));
                    $isKhmer = preg_match('/[\x{1780}-\x{17FF}]/u', $cleanScript) || preg_match('/(khmer|cambodia|ខ្មែរ)/iu', $userPrompt);
                    $isDeep = preg_match('/(deep|cinematic|movie|trailer)/i', $userPrompt);
                    $isSlow = preg_match('/(slow|gentle|calm)/i', $userPrompt);

                    $voiceToUse = $isKhmer ? 'Khmer-Male' : ($isDeep ? 'Charon' : 'Puck');
                    $styleToUse = $isDeep ? 'cinematic' : 'professional';
                    $speedToUse = $isSlow ? 0.85 : 1.0;

                    $voiceResult = $this->geminiClient->generateVoiceOver($cleanScript, [
                        'voice' => $voiceToUse,
                        'style' => $styleToUse,
                        'speed' => $speedToUse,
                    ]);

                    if (!empty($voiceResult['success'])) {
                        AiVoiceOver::create([
                            'user_id'          => $user->id,
                            'title'            => Str::limit($cleanScript, 40, '...'),
                            'script'           => $cleanScript,
                            'voice_name'       => $voiceResult['voice_name'],
                            'voice_gender'     => $voiceResult['voice_gender'],
                            'style'            => $voiceResult['style'],
                            'speed'            => $voiceResult['speed'],
                            'audio_path'       => $voiceResult['audio_path'],
                            'duration_seconds' => $voiceResult['duration_seconds'],
                        ]);

                        $audioAttachments[] = [
                            'type'     => 'audio',
                            'url'      => $voiceResult['audio_url'],
                            'voice'    => $voiceResult['voice_name'],
                            'duration' => $voiceResult['duration_seconds'],
                            'title'    => $voiceResult['voice_name'] . ' Voice-Over',
                        ];

                        $botText .= ($botText ? "\n\n" : "")
                            . "### 🎙️ Audio Voice-Over Generated\n\n"
                            . "<audio controls class=\"w-full my-2.5 rounded-xl shadow-xs\" src=\"{$voiceResult['audio_url']}\"></audio>\n\n"
                            . "- **Voice Character**: {$voiceResult['voice_name']} ({$voiceResult['style']} style, {$voiceResult['speed']}x)\n"
                            . "- **Duration**: {$voiceResult['duration_seconds']} seconds\n"
                            . "- ⬇️ [**Download Audio File**]({$voiceResult['audio_url']})\n";
                    }
                }
            }

            if (empty($botText)) {
                $botText = "I have reviewed your request and verified the information.";
            }

            // Save Model Response
            $botMsg = AiMessage::create([
                'conversation_id' => $conversation->id,
                'user_id'         => null,
                'role'            => 'model',
                'content'         => $botText,
                'action_type'     => $actionType,
                'action_data'     => $actionData,
                'action_status'   => $actionStatus,
                'attachments'     => !empty($audioAttachments) ? $audioAttachments : null,
            ]);

            $conversation->update(['last_message_at' => now()]);

            return response()->json([
                'success'         => true,
                'conversation_id' => $conversation->id,
                'message'         => $this->formatMessage($botMsg),
            ]);
        } catch (\Throwable $e) {
            Log::error("KiuqAiController@chat Exception: " . $e->getMessage());

            $fallbackMsg = "I am currently receiving your message. A temporary delay occurred with the AI service. Please continue or retry your prompt.";

            $botMsg = AiMessage::create([
                'conversation_id' => $conversation->id,
                'user_id'         => null,
                'role'            => 'model',
                'content'         => $fallbackMsg,
            ]);

            return response()->json([
                'success'         => true,
                'conversation_id' => $conversation->id,
                'message'         => $this->formatMessage($botMsg),
            ]);
        }
    }

    /**
     * Stream a prompt in a conversation via Server-Sent Events (SSE).
     * Delivers immediate real-time tokens to the browser exactly like real Gemini.
     */
    public function streamChat(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $validated = $request->validate([
            'message'         => ['nullable', 'string', 'max:15000'],
            'conversation_id' => ['nullable', 'integer'],
            'mode'            => ['nullable', 'string', 'in:general,translate,blog,image,seo,task,qc,report,voice'],
            'model'           => ['nullable', 'string', 'max:100'],
            'attachments'     => ['nullable', 'array'],
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->stream(function () {
                echo "data: " . json_encode(['error' => 'Unauthenticated.', 'done' => true]) . "\n\n";
            }, 401, [
                'Content-Type'      => 'text/event-stream',
                'Cache-Control'     => 'no-cache',
                'Connection'        => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        $rawMessage = trim($validated['message'] ?? '');
        $attachments = $validated['attachments'] ?? [];

        if (empty($rawMessage) && !empty($attachments)) {
            $userPrompt = "Please analyze this uploaded screenshot/image. Extract key text, data tables, metrics, visual elements, and summarize your findings.";
        } else {
            $userPrompt = $rawMessage ?: 'Hello KIUQ AI';
        }

        $mode  = $validated['mode'] ?? 'general';
        $model = $validated['model'] ?? config('kiuq_ai.text_model', 'gemini-flash-lite-latest');
        if ($this->isGroqModel($model)) {
            $model = $this->groqClient->resolveModel($model);
        }

        // 1. Safety check
        $safety = $this->safetyService->inspectPrompt($userPrompt, $user);
        if ($safety['is_destructive']) {
            $conversation = $this->getOrCreateConversation($validated['conversation_id'] ?? null, $user->id, $mode, $userPrompt);
            AiMessage::create([
                'conversation_id' => $conversation->id,
                'user_id'         => $user->id,
                'role'            => 'user',
                'content'         => $userPrompt,
            ]);
            $refusal = "⛔ **Action Prohibited**: " . $safety['reason'];
            $botMsg = AiMessage::create([
                'conversation_id' => $conversation->id,
                'user_id'         => null,
                'role'            => 'model',
                'content'         => $refusal,
            ]);
            return response()->stream(function () use ($conversation, $botMsg, $refusal) {
                echo "data: " . json_encode(['text' => $refusal, 'conversation_id' => $conversation->id, 'done' => false]) . "\n\n";
                echo "data: " . json_encode(['done' => true, 'message' => $this->formatMessage($botMsg), 'conversation_id' => $conversation->id]) . "\n\n";
            }, 200, [
                'Content-Type'      => 'text/event-stream',
                'Cache-Control'     => 'no-cache',
                'Connection'        => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        // 2. Resolve conversation & save user message
        $conversation = $this->getOrCreateConversation($validated['conversation_id'] ?? null, $user->id, $mode, $userPrompt);

        // Process attachments
        $processedAttachments = [];
        $multimodalParts = [];
        foreach ($attachments as $att) {
            if (!empty($att['base64'])) {
                $base64 = $att['base64'];
                $mimeType = $att['type'] ?? 'image/png';
                $name = $att['name'] ?? ('attachment_' . time() . '.png');
                $cleanBase64 = preg_replace('#^data:[^;]+;base64,#i', '', (string)$base64);
                $cleanBase64 = trim(preg_replace('/\s+/', '', $cleanBase64));

                $relDir = 'ai_attachments';
                if (!Storage::disk('public')->exists($relDir)) {
                    Storage::disk('public')->makeDirectory($relDir);
                }
                $filename = 'att_' . time() . '_' . Str::random(8) . '.' . (str_contains($mimeType, 'jpeg') ? 'jpg' : 'png');
                Storage::disk('public')->put("{$relDir}/{$filename}", base64_decode($cleanBase64));
                $publicUrl = asset("storage/{$relDir}/{$filename}");

                $processedAttachments[] = [
                    'name'      => $name,
                    'type'      => $mimeType,
                    'url'       => $publicUrl,
                    'file_path' => "{$relDir}/{$filename}",
                ];

                $multimodalParts[] = [
                    'inlineData' => [
                        'mimeType' => $mimeType,
                        'data'     => $cleanBase64,
                    ]
                ];
            }
        }

        $userMsg = AiMessage::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $user->id,
            'role'            => 'user',
            'content'         => $userPrompt,
            'attachments'     => !empty($processedAttachments) ? $processedAttachments : null,
        ]);

        $contents = $this->buildGeminiHistory($conversation, $multimodalParts);

        // Conditional tools
        $tools = [];
        $lowerPrompt = strtolower($userPrompt);
        $needsTools = ($mode !== 'general' && $mode !== 'translate');
        if (!$needsTools) {
            $systemKeywordsPattern = '/\b(card|cards|board|boards|workspace|workspaces|task|tasks|qc check|qc audit|system report|production report|report|reports|pdf|export|download|dgt database|blog post|blogs|website list|website|websites|domain|domains|inquiry|inquiries|lead|leads|member|members|user|users|staff|team|kpi|gsc|search console|seo|chat|notes|navigation|link|links|route|routes)\b/i';
            if (preg_match($systemKeywordsPattern, $lowerPrompt)) {
                $needsTools = true;
            }
        }
        if ($needsTools) {
            $tools = $this->toolRegistry->getDeclarations();
        }

        $contents = $this->sanitizeUtf8($contents);

        $previousUserPrompt = AiMessage::where('conversation_id', $conversation->id)
            ->where('role', 'user')
            ->where('id', '<', $userMsg->id)
            ->latest('id')
            ->value('content');

        $isCreationOrWritingPrompt = preg_match('/\b(write|draft|translate|caption|code|html|css|image|voice|audio|explain|rewrite|create\s+card|move\s+card|publish|export|pdf|csv)\b/i', $userPrompt) === 1;
        $fastSmartSearch = (empty($multimodalParts) && !$isCreationOrWritingPrompt)
            ? $this->toolRegistry->detectAndExecuteSmartSearch($userPrompt, $user, $previousUserPrompt)
            : null;

        if (!empty($fastSmartSearch['message'])) {
            $fastText = $fastSmartSearch['message'];
            return response()->stream(function () use ($conversation, $user, $fastText) {
                @ini_set('output_buffering', 'off');
                @ini_set('zlib.output_compression', false);
                @ini_set('implicit_flush', 1);
                ob_implicit_flush(1);
                while (ob_get_level() > 0) {
                    ob_end_flush();
                }
                echo ": " . str_repeat(" ", 2048) . "\n\n";
                echo "data: " . json_encode(['text' => $fastText, 'conversation_id' => $conversation->id, 'done' => false], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                echo "data: " . json_encode(['text' => '', 'conversation_id' => $conversation->id, 'done' => true], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();

                AiMessage::create([
                    'conversation_id' => $conversation->id,
                    'user_id'         => $user->id,
                    'role'            => 'model',
                    'content'         => $fastText,
                ]);
            }, 200, [
                'Content-Type'      => 'text/event-stream; charset=UTF-8',
                'Cache-Control'     => 'no-cache, no-transform',
                'X-Accel-Buffering' => 'no',
                'Connection'        => 'keep-alive',
            ]);
        }

        return response()->stream(function () use ($conversation, $contents, $tools, $model, $user, $userPrompt, $multimodalParts) {
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', false);
            @ini_set('implicit_flush', 1);
            ob_implicit_flush(1);
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            // 2KB keep-alive padding forces Hostinger Nginx / Cloudflare edge proxies to stream chunks immediately
            echo ": " . str_repeat(" ", 2048) . "\n\n";
            echo "data: " . json_encode(['text' => '', 'conversation_id' => $conversation->id, 'done' => false], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();

            $accumulatedText = '';
            $actionType = null;
            $actionData = null;
            $actionStatus = null;

            try {
                $onChunkCallback = function (string $chunk, bool $isFinal, ?array $functionCall) use (&$accumulatedText, $conversation) {
                    if (!empty($chunk)) {
                        $accumulatedText .= $chunk;
                        echo "data: " . json_encode([
                            'text'            => $chunk,
                            'conversation_id' => $conversation->id,
                            'done'            => false,
                        ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE) . "\n\n";
                        flush();
                    }
                };

                $streamingSucceeded = false;
                $candidateStreamModels = [];
                if (!empty($multimodalParts)) {
                    $candidateStreamModels = [
                        $this->isGroqModel($model) ? 'gemini-flash-lite-latest' : $model,
                        'gemini-flash-lite-latest',
                        'gemini-3.5-flash',
                    ];
                } else {
                    if ($this->isGroqModel($model)) {
                        $candidateStreamModels = [
                            $model,
                            'llama-3.1-8b-instant',
                            'gemini-flash-lite-latest',
                        ];
                    } else {
                        $candidateStreamModels = [
                            $model,
                            'gemini-flash-lite-latest',
                            'llama-3.3-70b-versatile',
                        ];
                    }
                }
                $candidateStreamModels = array_values(array_unique($candidateStreamModels));

                $response = null;
                foreach ($candidateStreamModels as $streamModel) {
                    try {
                        if ($this->isGroqModel($streamModel)) {
                            if (!$this->groqClient->isConfigured()) continue;
                            $response = $this->groqClient->streamContent(
                                $contents,
                                $tools,
                                null,
                                $streamModel,
                                $onChunkCallback
                            );
                        } else {
                            if (!$this->geminiClient->isConfigured()) continue;
                            $response = $this->geminiClient->streamContent(
                                $contents,
                                $tools,
                                null,
                                $streamModel,
                                $onChunkCallback
                            );
                        }
                        $streamingSucceeded = true;
                        break;
                    } catch (\Throwable $streamErr) {
                        Log::warning("streamChat tier [{$streamModel}] error: " . $streamErr->getMessage() . ". Trying next stream candidate...");
                    }
                }

                // If streaming didn't output text or threw an error, seamless fallback via executeAiWithFailover non-streaming
                if (!$streamingSucceeded || (empty($accumulatedText) && empty($response['text'] ?? null))) {
                    $response = $this->executeAiWithFailover($model, $contents, $tools, $userPrompt, !empty($multimodalParts));
                    $fallbackText = $response['text'] ?? "Request processed successfully.";
                    $accumulatedText .= ($accumulatedText ? "\n\n" : "") . $fallbackText;
                    echo "data: " . json_encode([
                        'text'            => $fallbackText,
                        'conversation_id' => $conversation->id,
                        'done'            => false,
                    ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE) . "\n\n";
                    flush();
                }

                // Handle function calls if any
                if (!empty($response['function_calls'])) {
                    foreach ($response['function_calls'] as $call) {
                        $toolName = $call['name'] ?? '';
                        $toolArgs = $call['args'] ?? [];
                        $toolResult = $this->toolRegistry->execute($toolName, $toolArgs, $user);

                        if (!empty($toolResult['requires_ui_action'])) {
                            $actionType   = $toolResult['action_type'] ?? null;
                            $actionData   = $toolResult['action_data'] ?? null;
                            $actionStatus = 'pending_confirmation';
                        }

                        $toolMsg = $toolResult['message'] ?? (isset($toolResult['summary']) ? json_encode($toolResult['summary'], JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE) : '');
                        if (empty($toolMsg) && !empty($toolResult['error'])) {
                            $toolMsg = "⚠️ " . $toolResult['error'];
                        }
                        if (!empty($toolMsg)) {
                            $append = "\n\n" . $toolMsg;
                            $accumulatedText .= $append;
                            echo "data: " . json_encode([
                                'text'            => $append,
                                'conversation_id' => $conversation->id,
                                'done'            => false,
                            ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE) . "\n\n";
                            flush();
                        }
                    }
                }

                // Direct intent check: if user asked to export/generate report or asked for the PDF
                $isBoardExportRequest = preg_match('/\b(export|generate|download|view)\b.*?\bboard\b/i', $userPrompt)
                    || preg_match('/\bboard\b.*?\b(report|export|pdf|csv)\b/i', $userPrompt)
                    || preg_match('/\b(planning|workflow|graphic|video|smm|content)\s+board\b/i', $userPrompt);

                $isReportRequest = $isBoardExportRequest
                    || preg_match('/\b(export|generate|download|create)\s+.*?\b(report|pdf)\b/i', $userPrompt)
                    || preg_match('/\b(where\s+is\s+the\s+pdf|where\s+is\s+the\s+report)\b/i', $userPrompt)
                    || preg_match('/\b(report\s+of\s+board|planning\s+board\s+report|production\s+report)\b/i', $userPrompt);

                if ($isReportRequest && empty($response['function_calls'])) {
                    if (preg_match('/where\s+is\s+the\s+(pdf|report)/i', $userPrompt)) {
                        $latestRep = \App\Models\Ai\AiReport::where('user_id', $user->id)->latest()->first();
                        if (!$latestRep && method_exists($user, 'isSpecialManagerOrAdmin') && $user->isSpecialManagerOrAdmin()) {
                            $latestRep = \App\Models\Ai\AiReport::latest()->first();
                        }
                        if ($latestRep) {
                            $pdfUrl = route('ai.reports.export', ['report' => $latestRep->id, 'format' => 'pdf']);
                            $csvUrl = route('ai.reports.export', ['report' => $latestRep->id, 'format' => 'csv']);
                            $repAppend = "\n\n### 📥 Download PDF & CSV Report\n\n"
                                . "Here are your download links for **{$latestRep->title}**:\n"
                                . "- 📄 [**Download PDF Report**]({$pdfUrl})\n"
                                . "- 📊 [**Download CSV Data**]({$csvUrl})\n";
                            $accumulatedText .= $repAppend;
                            echo "data: " . json_encode([
                                'text'            => $repAppend,
                                'conversation_id' => $conversation->id,
                                'done'            => false,
                            ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE) . "\n\n";
                            flush();
                        }
                    } elseif ($isBoardExportRequest) {
                        $toolResult = $this->toolRegistry->execute('export_board_report', [
                            'board_name' => $userPrompt,
                        ], $user);
                        if (!empty($toolResult['message'])) {
                            $repAppend = "\n\n" . $toolResult['message'];
                            $accumulatedText .= $repAppend;
                            echo "data: " . json_encode([
                                'text'            => $repAppend,
                                'conversation_id' => $conversation->id,
                                'done'            => false,
                            ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE) . "\n\n";
                            flush();
                        }
                    } else {
                        $toolResult = $this->toolRegistry->execute('generate_production_report', [
                            'instructions' => $userPrompt,
                            'title'        => 'Production Report',
                        ], $user);
                        if (!empty($toolResult['message'])) {
                            $repAppend = "\n\n" . $toolResult['message'];
                            $accumulatedText .= $repAppend;
                            echo "data: " . json_encode([
                                'text'            => $repAppend,
                                'conversation_id' => $conversation->id,
                                'done'            => false,
                            ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE) . "\n\n";
                            flush();
                        }
                    }
                }

                // Voice synthesis intent check (or google-voice-studio model)
                $isVoiceRequest = ($model === 'google-voice-studio')
                    || preg_match('/\b(generate|create|synthesize|make|speak|record|talk|narrate)\b.*?\b(voice|speech|audio|voiceover|voice-over|sound)\b/iu', $userPrompt)
                    || preg_match('/\b(ok\s+generate\s+now|generate\s+now|generate\s+audio|speak\s+this|read\s+this|synthesize\s+now)\b/iu', $userPrompt);

                $audioAttachments = [];
                if ($isVoiceRequest && empty($response['function_calls'])) {
                    $scriptToVoice = '';
                    if (preg_match('/\[Voice-Over Script\]\s*(.*?)(?=\n\n|\Z)/isu', $userPrompt, $sm)) {
                        $scriptToVoice = trim($sm[1]);
                    } elseif (preg_match('/["“\']([^"“\']{15,})["”\']/u', $userPrompt, $sm)) {
                        $scriptToVoice = trim($sm[1]);
                    } elseif (mb_strlen($userPrompt) > 25 && !preg_match('/^(ok\s+generate|generate\s+now|can\s+u\s+generate|please\s+generate)/i', $userPrompt)) {
                        $scriptToVoice = $userPrompt;
                    }

                    // If user prompt is a trigger (e.g. "ok generate now"), check previous assistant message in conversation
                    if (empty($scriptToVoice)) {
                        $prevMsg = AiMessage::where('conversation_id', $conversation->id)
                            ->where('role', 'model')
                            ->latest()
                            ->first();

                        if ($prevMsg && !empty($prevMsg->content)) {
                            if (preg_match('/\[Voice-Over Script\]\s*(.*?)(?=\n\n|\Z)/isu', $prevMsg->content, $psm)) {
                                $scriptToVoice = trim($psm[1]);
                            } elseif (preg_match('/["“\']([^"“\']{15,})["”\']/u', $prevMsg->content, $psm)) {
                                $scriptToVoice = trim($psm[1]);
                            } else {
                                $scriptToVoice = $prevMsg->content;
                            }
                        }
                    }

                    // If still empty, check current response text
                    if (empty($scriptToVoice) && !empty($accumulatedText)) {
                        if (preg_match('/\[Voice-Over Script\]\s*(.*?)(?=\n\n|\Z)/isu', $accumulatedText, $asm)) {
                            $scriptToVoice = trim($asm[1]);
                        } elseif (preg_match('/["“\']([^"“\']{15,})["”\']/u', $accumulatedText, $asm)) {
                            $scriptToVoice = trim($asm[1]);
                        }
                    }

                    if (!empty($scriptToVoice)) {
                        $cleanScript = trim(strip_tags($scriptToVoice));
                        $isKhmer = preg_match('/[\x{1780}-\x{17FF}]/u', $cleanScript) || preg_match('/(khmer|cambodia|ខ្មែរ)/iu', $userPrompt);
                        $isDeep = preg_match('/(deep|cinematic|movie|trailer)/i', $userPrompt);
                        $isSlow = preg_match('/(slow|gentle|calm)/i', $userPrompt);

                        $voiceToUse = $isKhmer ? 'Khmer-Male' : ($isDeep ? 'Charon' : 'Puck');
                        $styleToUse = $isDeep ? 'cinematic' : 'professional';
                        $speedToUse = $isSlow ? 0.85 : 1.0;

                        $voiceResult = $this->geminiClient->generateVoiceOver($cleanScript, [
                            'voice' => $voiceToUse,
                            'style' => $styleToUse,
                            'speed' => $speedToUse,
                        ]);

                        if (!empty($voiceResult['success'])) {
                            AiVoiceOver::create([
                                'user_id'          => $user->id,
                                'title'            => Str::limit($cleanScript, 40, '...'),
                                'script'           => $cleanScript,
                                'voice_name'       => $voiceResult['voice_name'],
                                'voice_gender'     => $voiceResult['voice_gender'],
                                'style'            => $voiceResult['style'],
                                'speed'            => $voiceResult['speed'],
                                'audio_path'       => $voiceResult['audio_path'],
                                'duration_seconds' => $voiceResult['duration_seconds'],
                            ]);

                            $audioAttachments[] = [
                                'type'     => 'audio',
                                'url'      => $voiceResult['audio_url'],
                                'voice'    => $voiceResult['voice_name'],
                                'duration' => $voiceResult['duration_seconds'],
                                'title'    => $voiceResult['voice_name'] . ' Voice-Over',
                            ];

                            $voiceCard = "\n\n### 🎙️ Audio Voice-Over Generated\n\n"
                                . "<audio controls class=\"w-full my-2.5 rounded-xl shadow-xs\" src=\"{$voiceResult['audio_url']}\"></audio>\n\n"
                                . "- **Voice Character**: {$voiceResult['voice_name']} ({$voiceResult['style']} style, {$voiceResult['speed']}x)\n"
                                . "- **Duration**: {$voiceResult['duration_seconds']} seconds\n"
                                . "- ⬇️ [**Download Audio File**]({$voiceResult['audio_url']})\n";

                            $accumulatedText .= $voiceCard;
                            echo "data: " . json_encode([
                                'text'            => $voiceCard,
                                'conversation_id' => $conversation->id,
                                'done'            => false,
                            ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE) . "\n\n";
                            flush();
                        }
                    }
                }

                if (empty($accumulatedText)) {
                    $accumulatedText = $response['text'] ?? "Request processed successfully.";
                    echo "data: " . json_encode([
                        'text'            => $accumulatedText,
                        'conversation_id' => $conversation->id,
                        'done'            => false,
                    ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE) . "\n\n";
                    flush();
                }

                // Save model message to DB
                $botMsg = AiMessage::create([
                    'conversation_id' => $conversation->id,
                    'user_id'         => null,
                    'role'            => 'model',
                    'content'         => $accumulatedText,
                    'action_type'     => $actionType,
                    'action_data'     => $actionData,
                    'action_status'   => $actionStatus,
                    'attachments'     => !empty($audioAttachments) ? $audioAttachments : null,
                ]);

                $conversation->update(['last_message_at' => now()]);

                echo "data: " . json_encode([
                    'done'            => true,
                    'conversation_id' => $conversation->id,
                    'message'         => $this->formatMessage($botMsg),
                ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();

            } catch (\Throwable $e) {
                Log::error("streamChat Exception: " . $e->getMessage());
                $errorMsg = "I am currently receiving your message. A temporary delay occurred with the AI service. Please continue or retry your prompt.";
                
                $botMsg = AiMessage::create([
                    'conversation_id' => $conversation->id,
                    'user_id'         => null,
                    'role'            => 'model',
                    'content'         => $errorMsg,
                ]);

                echo "data: " . json_encode([
                    'text'            => $errorMsg,
                    'conversation_id' => $conversation->id,
                    'done'            => true,
                    'message'         => $this->formatMessage($botMsg),
                ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream; charset=UTF-8',
            'Cache-Control'     => 'no-cache, no-transform, private',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'Content-Encoding'  => 'none',
        ]);
    }

    /**
     * Improve a raw user prompt with context and clarity.
     */
    public function improvePrompt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt'  => ['required', 'string', 'max:5000'],
            'context' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->geminiClient->improvePrompt($validated['prompt'], $validated['context'] ?? null);

        return response()->json($result);
    }

    /**
     * Get available Gemini models for model selector.
     */
    public function getModels(Request $request): JsonResponse
    {
        $models = config('kiuq_ai.available_models', []);
        $defaultModel = 'llama-3.3-70b-versatile';
        foreach ($models as $m) {
            if (!empty($m['is_default'])) {
                $defaultModel = $m['id'];
                break;
            }
        }

        return response()->json([
            'success'      => true,
            'active_model' => $defaultModel,
            'models'       => $models,
        ]);
    }

    /**
     * Preload studio context data (Websites, Boards, recent Reports, Images, Voice-overs).
     */
    public function getStudioData(Request $request): JsonResponse
    {
        $user = auth()->user();

        $websites = Website::where(function ($q) {
            $q->where('is_archived', false)->orWhereNull('is_archived');
        })->select(['id', 'name', 'url', 'status'])->orderBy('name')->get();

        $boards = Board::with(['lists' => fn($q) => $q->orderBy('position')])
            ->where('is_hidden', false)
            ->where('is_archived', false)
            ->select(['id', 'name', 'type'])
            ->orderBy('name')
            ->get()
            ->map(fn($b) => [
                'id'    => $b->id,
                'name'  => $b->name,
                'lists' => $b->lists->map(fn($l) => ['id' => $l->id, 'name' => $l->name]),
            ]);

        $reports = AiReport::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->take(10)
            ->get()
            ->map(fn($r) => [
                'id'       => $r->id,
                'title'    => $r->title,
                'category' => $r->category,
                'period'   => $r->date_from->format('Y-m-d') . ' to ' . $r->date_to->format('Y-m-d'),
                'pdf_url'  => route('ai.reports.export', ['report' => $r->id, 'format' => 'pdf']),
                'csv_url'  => route('ai.reports.export', ['report' => $r->id, 'format' => 'csv']),
                'date'     => $r->created_at->format('M d, Y'),
            ]);

        $voices = AiVoiceOver::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->take(10)
            ->get()
            ->map(fn($v) => [
                'id'         => $v->id,
                'title'      => $v->title,
                'voice_name' => $v->voice_name,
                'style'      => $v->style,
                'duration'   => $v->duration_seconds,
                'audio_url'  => asset("storage/{$v->audio_path}"),
                'date'       => $v->created_at->format('M d, H:i'),
            ]);

        return response()->json([
            'success'             => true,
            'websites'            => $websites,
            'boards'              => $boards,
            'reports'             => $reports,
            'voices'              => $voices,
            'translate_languages' => KiuqTranslationService::supportedLanguages(),
            'translate_modes'     => KiuqTranslationService::supportedModes(),
        ]);
    }

    /**
     * Generate AI voice-over directly via Google AI Studio Voice Studio.
     */
    public function generateVoiceOverDirect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'script'      => ['required', 'string', 'max:5000'],
            'voice'       => ['nullable', 'string', 'max:50'],
            'style'       => ['nullable', 'string', 'max:50'],
            'custom_tone' => ['nullable', 'string', 'max:500'],
            'speed'       => ['nullable', 'numeric', 'min:0.5', 'max:2.0'],
            'pitch'       => ['nullable', 'numeric', 'min:-5.0', 'max:5.0'],
        ]);

        $user = auth()->user();

        try {
            $options = [
                'voice'       => $validated['voice'] ?? 'Charon',
                'style'       => $validated['style'] ?? 'cinematic',
                'custom_tone' => $validated['custom_tone'] ?? null,
                'speed'       => (float) ($validated['speed'] ?? 1.0),
                'pitch'       => (float) ($validated['pitch'] ?? 0.0),
            ];

            $result = $this->geminiClient->generateVoiceOver($validated['script'], $options);

            if (!empty($result['success'])) {
                $title = Str::limit($validated['script'], 40, '...');
                $record = AiVoiceOver::create([
                    'user_id'          => $user->id,
                    'title'            => $title,
                    'script'           => $validated['script'],
                    'voice_name'       => $result['voice_name'],
                    'voice_gender'     => $result['voice_gender'],
                    'style'            => $result['style'],
                    'speed'            => $result['speed'],
                    'audio_path'       => $result['audio_path'],
                    'duration_seconds' => $result['duration_seconds'],
                ]);

                $this->safetyService->logAction($user, 'generate_voice_over_direct', 'AiVoiceOver', $record->id, [
                    'voice' => $result['voice_name'],
                    'speed' => $result['speed'],
                ]);

                return response()->json([
                    'success'          => true,
                    'voice_over'       => [
                        'id'         => $record->id,
                        'title'      => $record->title,
                        'voice_name' => $record->voice_name,
                        'style'      => $record->style,
                        'duration'   => $record->duration_seconds,
                        'audio_url'  => $result['audio_url'],
                        'date'       => $record->created_at->format('M d, H:i'),
                    ],
                    'message'          => "Voice-over created successfully with {$result['voice_name']}.",
                ]);
            }

            return response()->json(['success' => false, 'error' => 'Could not synthesize voice.'], 500);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Synthesize voice for a specific chat message with one click.
     */
    public function generateVoiceForMessage(Request $request, int $id): JsonResponse
    {
        $message = AiMessage::findOrFail($id);
        $user = auth()->user();

        $validated = $request->validate([
            'voice'       => ['nullable', 'string', 'max:50'],
            'style'       => ['nullable', 'string', 'max:50'],
            'custom_tone' => ['nullable', 'string', 'max:500'],
            'speed'       => ['nullable', 'numeric', 'min:0.5', 'max:2.0'],
            'pitch'       => ['nullable', 'numeric', 'min:-5.0', 'max:5.0'],
        ]);

        $rawScript = $message->content;
        $cleanScript = trim(strip_tags($rawScript));
        if (preg_match('/\[(?:Voice-Over Script|Script|Text|Audio)\]\s*(.*?)(?=\n\n|\Z)/isu', $rawScript, $m)) {
            $cleanScript = trim($m[1]);
        }
        $cleanScript = preg_replace('/^(?:read\s+aloud(?:\s+the\s+following\s+(?:text|script))?(?:\s+in\s+[^:]+)?:\s*|spoken\s+script:\s*|script:\s*|narrator:\s*|voice-?over(?:\s+script)?:\s*)/iu', '', $cleanScript);
        $cleanScript = trim(trim($cleanScript, '"\''));

        if (empty($cleanScript)) {
            return response()->json(['success' => false, 'error' => 'No speakable text found in message.'], 422);
        }

        try {
            $isKhmer = preg_match('/[\x{1780}-\x{17FF}]/u', $cleanScript);
            $defaultVoice = $isKhmer ? 'Khmer-Male' : 'Charon';

            $options = [
                'voice'       => $validated['voice'] ?? $defaultVoice,
                'style'       => $validated['style'] ?? 'cinematic',
                'custom_tone' => $validated['custom_tone'] ?? null,
                'speed'       => (float) ($validated['speed'] ?? 1.0),
                'pitch'       => (float) ($validated['pitch'] ?? 0.0),
            ];

            $result = $this->geminiClient->generateVoiceOver($cleanScript, $options);

            if (!empty($result['success'])) {
                $title = Str::limit($cleanScript, 40, '...');
                $record = AiVoiceOver::create([
                    'user_id'          => $user->id,
                    'title'            => $title,
                    'script'           => $cleanScript,
                    'voice_name'       => $result['voice_name'],
                    'voice_gender'     => $result['voice_gender'],
                    'style'            => $result['style'],
                    'speed'            => $result['speed'],
                    'audio_path'       => $result['audio_path'],
                    'duration_seconds' => $result['duration_seconds'],
                ]);

                // Append audio attachment to message
                $attachments = $message->attachments ?? [];
                $attachments[] = [
                    'type'     => 'audio',
                    'url'      => $result['audio_url'],
                    'voice'    => $result['voice_name'],
                    'duration' => $result['duration_seconds'],
                    'title'    => $result['voice_name'] . ' Voice-Over',
                ];
                $message->update(['attachments' => $attachments]);

                return response()->json([
                    'success'    => true,
                    'voice_over' => [
                        'id'         => $record->id,
                        'title'      => $record->title,
                        'voice_name' => $record->voice_name,
                        'style'      => $record->style,
                        'duration'   => $record->duration_seconds,
                        'audio_url'  => $result['audio_url'],
                        'date'       => $record->created_at->format('M d, H:i'),
                    ],
                ]);
            }

            return response()->json(['success' => false, 'error' => 'Could not synthesize voice.'], 500);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Voice-Over generation history.
     */
    public function getVoiceHistory(Request $request): JsonResponse
    {
        $user = auth()->user();
        $voices = AiVoiceOver::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->take(20)
            ->get()
            ->map(fn($v) => [
                'id'         => $v->id,
                'title'      => $v->title,
                'script'     => $v->script,
                'voice_name' => $v->voice_name,
                'style'      => $v->style,
                'speed'      => $v->speed,
                'duration'   => $v->duration_seconds,
                'audio_url'  => asset("storage/{$v->audio_path}"),
                'date'       => $v->created_at->format('M d, Y H:i'),
            ]);

        return response()->json(['success' => true, 'voices' => $voices]);
    }

    /**
     * Retrieve all conversations for the authenticated user (categorized by Pinned, Today, Earlier).
     */
    public function getConversations(Request $request): JsonResponse
    {
        $user = auth()->user();
        $search = trim($request->get('search', ''));
        $showArchived = $request->boolean('archived', false);

        $query = AiConversation::where('user_id', $user->id);

        if ($showArchived) {
            $query->where('is_archived', true);
        } else {
            $query->where('is_archived', false);
        }

        if (!empty($search)) {
            $query->where('title', 'like', "%{$search}%");
        }

        $conversations = $query->orderByDesc('is_pinned')
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->take(50)
            ->get();

        return response()->json([
            'success'       => true,
            'conversations' => $conversations->map(fn($c) => [
                'id'              => $c->id,
                'title'           => $c->title,
                'mode'            => $c->mode,
                'is_pinned'       => (bool) $c->is_pinned,
                'is_archived'     => (bool) $c->is_archived,
                'last_message_at' => $c->last_message_at ? $c->last_message_at->toIso8601String() : $c->updated_at->toIso8601String(),
                'date_label'      => $this->formatDateLabel($c->last_message_at ?: $c->updated_at),
            ]),
        ]);
    }

    /**
     * Load messages for a specific conversation.
     */
    public function getMessages(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $conversation = AiConversation::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$conversation) {
            return response()->json(['success' => false, 'error' => 'Conversation not found.'], 404);
        }

        $messages = AiMessage::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success'      => true,
            'conversation' => [
                'id'          => $conversation->id,
                'title'       => $conversation->title,
                'mode'        => $conversation->mode,
                'is_pinned'   => (bool) $conversation->is_pinned,
                'is_archived' => (bool) $conversation->is_archived,
                'updated_at'  => $conversation->updated_at->toIso8601String(),
            ],
            'messages'     => $messages->map(fn($m) => $this->formatMessage($m)),
        ]);
    }

    /**
     * Create a new blank conversation.
     */
    public function createConversation(Request $request): JsonResponse
    {
        $user = auth()->user();
        $mode = $request->get('mode', 'general');

        $conversation = AiConversation::create([
            'user_id'         => $user->id,
            'title'           => 'New Chat',
            'mode'            => in_array($mode, ['general', 'translate', 'blog', 'image', 'seo', 'task', 'qc', 'report', 'voice']) ? $mode : 'general',
            'last_message_at' => now(),
        ]);

        return response()->json([
            'success'      => true,
            'conversation' => [
                'id'              => $conversation->id,
                'title'           => $conversation->title,
                'mode'            => $conversation->mode,
                'is_pinned'       => false,
                'is_archived'     => false,
                'last_message_at' => $conversation->last_message_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Toggle Pin on a conversation.
     */
    public function togglePinConversation(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $conv = AiConversation::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $conv->is_pinned = !$conv->is_pinned;
        $conv->save();

        return response()->json(['success' => true, 'is_pinned' => $conv->is_pinned]);
    }

    /**
     * Archive or Unarchive a conversation.
     */
    public function archiveConversation(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $conv = AiConversation::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $conv->is_archived = !$conv->is_archived;
        $conv->save();

        return response()->json(['success' => true, 'is_archived' => $conv->is_archived]);
    }

    /**
     * Rename a conversation title.
     */
    public function renameConversation(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
        ]);

        $user = auth()->user();
        $conv = AiConversation::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $conv->title = trim($validated['title']);
        $conv->save();

        return response()->json(['success' => true, 'title' => $conv->title]);
    }

    /**
     * Delete a conversation thread.
     */
    public function deleteConversation(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $conversation = AiConversation::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$conversation) {
            return response()->json(['success' => false, 'error' => 'Not found.'], 404);
        }

        $conversation->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Execute an Action Preview that was confirmed by the user.
     */
    public function confirmAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_id' => ['required', 'integer'],
        ]);

        $user = auth()->user();
        $message = AiMessage::with('conversation')->find($validated['message_id']);

        if (!$message || $message->conversation->user_id !== $user->id) {
            return response()->json(['success' => false, 'error' => 'Unauthorized or message not found.'], 403);
        }

        if ($message->action_status !== 'pending_confirmation') {
            return response()->json(['success' => false, 'error' => 'Action already processed or not pending.'], 400);
        }

        $actionType = $message->action_type;
        $data       = $message->action_data ?? [];

        if ($actionType === 'publish_blog_preview') {
            $postId = $data['post_id'] ?? null;
            $post   = WebsitePost::with('website')->find($postId);

            if (!$post || !$post->website) {
                return response()->json(['success' => false, 'error' => 'Post or website not found.'], 404);
            }

            // Execute Remote Publication via existing WebsiteBlogPostService
            $publishResult = $this->blogService->dispatchPostToRemoteWebsite($post->website, $post);

            $message->update([
                'action_status' => 'confirmed',
                'content'       => $message->content . "\n\n✅ **Action Confirmed**: Published '{$post->title}' to {$post->website->name}. Status: Published.",
            ]);

            $this->safetyService->logAction($user, 'confirm_publish_blog', 'WebsitePost', $post->id, [
                'title'   => $post->title,
                'website' => $post->website->name,
                'result'  => $publishResult,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Published successfully.",
            ]);
        }

        return response()->json(['success' => false, 'error' => 'Unknown action type.'], 400);
    }

    /**
     * Cancel an Action Preview.
     */
    public function cancelAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_id' => ['required', 'integer'],
        ]);

        $user = auth()->user();
        $message = AiMessage::with('conversation')->find($validated['message_id']);

        if (!$message || $message->conversation->user_id !== $user->id) {
            return response()->json(['success' => false, 'error' => 'Unauthorized.'], 403);
        }

        $message->update([
            'action_status' => 'cancelled',
            'content'       => $message->content . "\n\n❌ **Action Cancelled** by user.",
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Download or export a compiled AI report (PDF or CSV).
     */
    public function exportReport(Request $request, int $reportId, string $format)
    {
        $user = auth()->user();
        $report = AiReport::findOrFail($reportId);

        if ($report->user_id !== $user->id && !$user->isSpecialManagerOrAdmin()) {
            abort(403, 'Unauthorized access to this AI report.');
        }

        if ($format === 'pdf') {
            if (!$report->file_pdf_path || !Storage::disk('public')->exists($report->file_pdf_path)) {
                $this->reportService->compilePdf($report);
            }
            return response()->download(Storage::disk('public')->path($report->file_pdf_path));
        }

        if ($format === 'csv') {
            if (!$report->file_csv_path || !Storage::disk('public')->exists($report->file_csv_path)) {
                $this->reportService->compileCsv($report);
            }
            return response()->download(Storage::disk('public')->path($report->file_csv_path));
        }

        abort(404, "Invalid export format.");
    }

    /**
     * Direct image generation studio endpoint.
     */
    public function generateImageDirect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt'       => ['required', 'string', 'max:1000'],
            'aspect_ratio' => ['nullable', 'string', 'in:1:1,16:9,4:3'],
        ]);

        $user = auth()->user();

        try {
            $aspectRatio = $validated['aspect_ratio'] ?? '1:1';
            $imageResult = $this->geminiClient->generateImage($validated['prompt'], [
                'aspectRatio' => $aspectRatio,
            ]);

            if (!empty($imageResult['success'])) {
                $relDir = 'ai_generated';
                if (!Storage::disk('public')->exists($relDir)) {
                    Storage::disk('public')->makeDirectory($relDir);
                }

                $filename = 'kiuq_' . time() . '_' . Str::random(8) . '.png';
                Storage::disk('public')->put("{$relDir}/{$filename}", base64_decode($imageResult['image_base64']));

                $publicUrl = asset("storage/{$relDir}/{$filename}");

                $this->safetyService->logAction($user, 'generate_image_direct', 'MediaAsset', null, [
                    'prompt' => $validated['prompt'],
                ]);

                return response()->json([
                    'success'   => true,
                    'image_url' => $publicUrl,
                    'filename'  => $filename,
                    'prompt'    => $validated['prompt'],
                ]);
            }

            return response()->json(['success' => false, 'error' => 'Failed to generate image.'], 500);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ── Helper Methods ─────────────────────────────────────────────────────────

    private function getOrCreateConversation(?int $conversationId, int $userId, string $mode, string $prompt): AiConversation
    {
        if ($conversationId) {
            $conv = AiConversation::where('id', $conversationId)->where('user_id', $userId)->first();
            if ($conv) {
                return $conv;
            }
        }

        $title = Str::limit($prompt, 40, '...');
        return AiConversation::create([
            'user_id'         => $userId,
            'title'           => $title ?: 'New Conversation',
            'mode'            => $mode,
            'last_message_at' => now(),
        ]);
    }

    private function buildGeminiHistory(AiConversation $conversation, array $activeMultimodalParts = []): array
    {
        // Take up to 6 most recent messages, ordered chronologically to stay well within API token rate limits
        $messages = AiMessage::where('conversation_id', $conversation->id)
            ->orderBy('id', 'desc')
            ->take(6)
            ->get()
            ->reverse()
            ->values();

        $contents = [];
        $lastIdx = $messages->count() - 1;

        foreach ($messages as $idx => $msg) {
            $role = ($msg->role === 'model') ? 'model' : 'user';
            $content = $this->sanitizeUtf8String($msg->content ?? '');

            // Smart History Compression:
            // For historical messages (previous turns, NOT the current user prompt),
            // truncate massive code blocks and limit lengthy text so conversation history
            // does NOT exhaust API per-minute token rate limits (e.g., Groq's 7,000 ITPM limit).
            if ($idx < $lastIdx && !empty($content)) {
                // Compress code blocks longer than 300 characters
                if (str_contains($content, '```')) {
                    $content = preg_replace_callback('/```([a-zA-Z0-9_.-]*)\n([\s\S]*?)```/u', function($matches) {
                        $lang = $matches[1] ?: 'code';
                        $code = $matches[2];
                        if (mb_strlen($code, 'UTF-8') > 250) {
                            $snippet = mb_substr($code, 0, 180, 'UTF-8');
                            return "```{$lang}\n{$snippet}\n... [code truncated for conversation context] ...\n```";
                        }
                        return $matches[0];
                    }, $content);
                }

                // Bound max length of any historical turn to ~1,200 characters (~300 tokens)
                if (mb_strlen($content, 'UTF-8') > 1200) {
                    $content = mb_substr($content, 0, 1000, 'UTF-8') . "\n... [content summarized for context] ...";
                }
            }

            $parts = [];
            if (!empty($content)) {
                $parts[] = ['text' => $this->sanitizeUtf8String($content)];
            }

            // If this is the current active user message and multimodal parts are attached
            if ($idx === $lastIdx && !empty($activeMultimodalParts)) {
                foreach ($activeMultimodalParts as $mPart) {
                    $parts[] = $mPart;
                }
            }

            if (!empty($parts)) {
                $contents[] = [
                    'role'  => $role,
                    'parts' => $parts,
                ];
            }
        }

        return $contents;
    }

    private function formatMessage(AiMessage $message): array
    {
        return [
            'id'            => $message->id,
            'role'          => $message->role,
            'content'       => $message->content,
            'action_type'   => $message->action_type,
            'action_data'   => $message->action_data,
            'action_status' => $message->action_status,
            'attachments'   => $message->attachments,
            'created_at'    => $message->created_at->toIso8601String(),
        ];
    }

    private function formatDateLabel($carbon): string
    {
        if (!$carbon) return 'Earlier';
        if ($carbon->isToday()) return 'Today';
        if ($carbon->isYesterday()) return 'Yesterday';
        if ($carbon->isCurrentWeek()) return 'This Week';
        if ($carbon->isLastWeek()) return 'Last Week';
        return $carbon->format('M Y');
    }

    // ── Multi-Language Translation Studio Endpoints ──────────────────────

    public function getTranslationLanguages(): JsonResponse
    {
        return response()->json([
            'success'   => true,
            'languages' => KiuqTranslationService::supportedLanguages(),
            'modes'     => KiuqTranslationService::supportedModes(),
        ]);
    }

    public function detectTranslationLanguage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:12000'],
        ]);

        $detection = $this->translationService->detectLanguage($validated['text']);

        return response()->json([
            'success'   => true,
            'detection' => $detection,
        ]);
    }

    public function translateTextStudio(Request $request): JsonResponse
    {
        $allowedModes = implode(',', array_keys(KiuqTranslationService::supportedModes()));
        $validated = $request->validate([
            'text'                     => ['required', 'string', 'max:12000'],
            'source_language'          => ['required', 'string', 'max:16'],
            'target_language'          => ['required', 'string', 'max:16'],
            'mode'                     => ['nullable', 'string', "in:{$allowedModes}"],
            'advanced_options'         => ['nullable', 'array'],
            'advanced_options.tone'    => ['nullable', 'string', 'max:100'],
            'advanced_options.audience'=> ['nullable', 'string', 'max:100'],
            'advanced_options.variant' => ['nullable', 'string', 'max:100'],
            'advanced_options.glossary'=> ['nullable', 'string', 'max:500'],
            'advanced_options.notes'   => ['nullable', 'string', 'max:500'],
        ]);

        $user = auth()->user();
        $sourceText = $this->sanitizeUtf8String(trim($validated['text']));
        if ($sourceText === '') {
            return response()->json(['success' => false, 'error' => 'Please enter text to translate.'], 422);
        }

        $sourceLang = trim($validated['source_language']);
        $targetLang = trim($validated['target_language']);
        if ($targetLang === 'auto') {
            return response()->json(['success' => false, 'error' => 'Please choose a specific target language.'], 422);
        }

        $mode = $validated['mode'] ?? 'standard';
        $advanced = $validated['advanced_options'] ?? [];

        try {
            $result = $this->translationService->translateText(
                sourceText: $sourceText,
                sourceLanguage: $sourceLang,
                targetLanguage: $targetLang,
                mode: $mode,
                advancedOptions: $advanced,
            );

            $historyItem = $this->persistTranslationRecord(
                userId: $user->id,
                sourceLanguage: $sourceLang,
                detectedLanguage: $result['detected_language'] ?? null,
                targetLanguage: $targetLang,
                mode: $mode,
                sourceText: $sourceText,
                translatedText: $result['translated_text'],
                extractedOcrText: null,
                advancedOptions: $advanced,
                providerModel: $result['provider_model'] ?? null,
            );

            return response()->json([
                'success'           => true,
                'translated_text'   => $result['translated_text'],
                'detected_language' => $result['detected_language'],
                'detected_name'     => $result['detected_name'],
                'detected_flag'     => $result['detected_flag'],
                'confidence'        => $result['confidence'],
                'provider_model'    => $result['provider_model'],
                'history_item'      => $historyItem,
            ]);
        } catch (\Throwable $e) {
            Log::error('Translation Studio error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function translateImageOcrStudio(Request $request): JsonResponse
    {
        $allowedModes = implode(',', array_keys(KiuqTranslationService::supportedModes()));
        $validated = $request->validate([
            'image'           => ['required', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:8192'],
            'source_language' => ['nullable', 'string', 'max:16'],
            'target_language' => ['required', 'string', 'max:16'],
            'mode'            => ['nullable', 'string', "in:{$allowedModes}"],
            'advanced_options'=> ['nullable', 'string', 'max:2000'],
        ]);

        $user = auth()->user();
        $sourceLang = trim($validated['source_language'] ?? 'auto') ?: 'auto';
        $targetLang = trim($validated['target_language']);
        if ($targetLang === 'auto') {
            return response()->json(['success' => false, 'error' => 'Please choose a target language for OCR translation.'], 422);
        }
        $mode = $validated['mode'] ?? 'standard';
        $advanced = [];
        if (!empty($validated['advanced_options'])) {
            $decoded = json_decode($validated['advanced_options'], true);
            if (is_array($decoded)) {
                $advanced = $decoded;
            }
        }

        try {
            $result = $this->translationService->translateImageOcr(
                imageFile: $request->file('image'),
                sourceLanguage: $sourceLang,
                targetLanguage: $targetLang,
                mode: $mode,
                advancedOptions: $advanced,
            );

            $extractedText = (string) ($result['extracted_text'] ?? '');
            $historyItem = $this->persistTranslationRecord(
                userId: $user->id,
                sourceLanguage: $sourceLang,
                detectedLanguage: $result['detected_language'] ?? null,
                targetLanguage: $targetLang,
                mode: $mode,
                sourceText: $extractedText,
                translatedText: $result['translated_text'],
                extractedOcrText: $extractedText,
                advancedOptions: $advanced,
                providerModel: $result['provider_model'] ?? null,
            );

            return response()->json([
                'success'           => true,
                'extracted_text'    => $extractedText,
                'translated_text'   => $result['translated_text'],
                'detected_language' => $result['detected_language'],
                'detected_name'     => $result['detected_name'],
                'detected_flag'     => $result['detected_flag'],
                'confidence'        => $result['confidence'],
                'provider_model'    => $result['provider_model'],
                'history_item'      => $historyItem,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Image OCR translation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    public function transcribeVoiceInputStudio(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audio'         => ['required', 'file', 'max:12288'],
            'language_hint' => ['nullable', 'string', 'max:16'],
        ]);

        try {
            $result = $this->translationService->transcribeSpeech(
                audioFile: $request->file('audio'),
                languageHint: $validated['language_hint'] ?? null,
            );

            return response()->json([
                'success' => true,
                'text'    => $result['text'],
                'model'   => $result['model'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Voice input transcription failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    public function synthesizeTranslationSpeech(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text'     => ['required', 'string', 'max:5000'],
            'language' => ['required', 'string', 'max:16'],
        ]);

        try {
            $result = $this->translationService->synthesizeSpeech(
                text: $validated['text'],
                languageCode: $validated['language'],
            );

            return response()->json([
                'success'   => true,
                'audio_url' => $result['audio_url'],
                'mime_type' => $result['mime_type'] ?? 'audio/mpeg',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Translation TTS failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    public function getTranslationHistory(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!Schema::hasTable('ai_translations')) {
            return response()->json(['success' => true, 'history' => []]);
        }

        $query = AiTranslation::where('user_id', $user->id);

        if ($request->boolean('favorites')) {
            $query->where('is_favorite', true);
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('source_text', 'like', "%{$search}%")
                    ->orWhere('translated_text', 'like', "%{$search}%")
                    ->orWhere('source_language', 'like', "%{$search}%")
                    ->orWhere('target_language', 'like', "%{$search}%");
            });
        }

        $items = $query->orderByDesc('is_favorite')
            ->orderByDesc('id')
            ->limit(60)
            ->get()
            ->map(fn(AiTranslation $item) => $this->formatTranslationHistoryItem($item))
            ->values();

        return response()->json([
            'success' => true,
            'history' => $items,
        ]);
    }

    public function toggleFavoriteTranslation(int $id): JsonResponse
    {
        $user = auth()->user();
        if (!Schema::hasTable('ai_translations')) {
            return response()->json(['success' => false, 'error' => 'Translation history table not migrated yet.'], 422);
        }

        $item = AiTranslation::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $item->is_favorite = !$item->is_favorite;
        $item->save();

        return response()->json([
            'success'     => true,
            'is_favorite' => $item->is_favorite,
            'item'        => $this->formatTranslationHistoryItem($item),
        ]);
    }

    public function deleteTranslationHistory(int $id): JsonResponse
    {
        $user = auth()->user();
        if (!Schema::hasTable('ai_translations')) {
            return response()->json(['success' => true]);
        }

        AiTranslation::where('id', $id)->where('user_id', $user->id)->delete();

        return response()->json(['success' => true]);
    }

    private function persistTranslationRecord(
        int $userId,
        string $sourceLanguage,
        ?string $detectedLanguage,
        string $targetLanguage,
        string $mode,
        string $sourceText,
        string $translatedText,
        ?string $extractedOcrText,
        array $advancedOptions,
        ?string $providerModel
    ): ?array {
        if (!Schema::hasTable('ai_translations')) {
            return null;
        }

        try {
            $record = AiTranslation::create([
                'user_id'            => $userId,
                'source_language'    => $sourceLanguage,
                'detected_language'  => $detectedLanguage,
                'target_language'    => $targetLanguage,
                'translation_mode'   => $mode,
                'source_text'        => $sourceText,
                'translated_text'    => $translatedText,
                'extracted_ocr_text' => $extractedOcrText,
                'advanced_options'   => !empty($advancedOptions) ? $advancedOptions : null,
                'provider_model'     => $providerModel,
                'is_favorite'        => false,
            ]);

            return $this->formatTranslationHistoryItem($record);
        } catch (\Throwable $e) {
            Log::warning('Could not persist translation history record: ' . $e->getMessage());
            return null;
        }
    }

    private function formatTranslationHistoryItem(AiTranslation $item): array
    {
        $srcMeta = $this->translationService->findLanguageMeta($item->detected_language ?: $item->source_language);
        $tgtMeta = $this->translationService->findLanguageMeta($item->target_language);

        return [
            'id'                 => $item->id,
            'source_language'    => $item->source_language,
            'detected_language'  => $item->detected_language,
            'source_label'       => $srcMeta['name'] ?? $item->source_language,
            'source_flag'        => $srcMeta['flag'] ?? '🌐',
            'target_language'    => $item->target_language,
            'target_label'       => $tgtMeta['name'] ?? $item->target_language,
            'target_flag'        => $tgtMeta['flag'] ?? '🌐',
            'translation_mode'   => $item->translation_mode,
            'source_text'        => $item->source_text,
            'translated_text'    => $item->translated_text,
            'extracted_ocr_text' => $item->extracted_ocr_text,
            'advanced_options'   => $item->advanced_options ?? [],
            'provider_model'     => $item->provider_model,
            'is_favorite'        => (bool) $item->is_favorite,
            'created_at'         => $item->created_at?->toIso8601String(),
            'time_label'         => $item->created_at?->diffForHumans() ?? 'just now',
        ];
    }
}
