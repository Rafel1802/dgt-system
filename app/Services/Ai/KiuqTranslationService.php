<?php

namespace App\Services\Ai;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KiuqTranslationService
{
    public function __construct(
        private readonly GeminiClientService $geminiClient,
        private readonly GroqClientService $groqClient,
    ) {}

    /**
     * Comprehensive, maintainable language & regional locale catalog.
     * Uses proper ISO 639 / BCP-47 language-locale codes with flags strictly as visual aids.
     */
    public static function supportedLanguages(): array
    {
        return [
            // Popular Regional & Global Languages
            ['code' => 'en-US', 'name' => 'English (United States)', 'native' => 'English (US)', 'flag' => '🇺🇸', 'tts' => 'en-us', 'speech' => 'en-US', 'popular' => true, 'rtl' => false],
            ['code' => 'en-GB', 'name' => 'English (United Kingdom)', 'native' => 'English (UK)', 'flag' => '🇬🇧', 'tts' => 'en-gb', 'speech' => 'en-GB', 'popular' => false, 'rtl' => false],
            ['code' => 'en-AU', 'name' => 'English (Australia)', 'native' => 'English (AU)', 'flag' => '🇦🇺', 'tts' => 'en-au', 'speech' => 'en-AU', 'popular' => false, 'rtl' => false],
            ['code' => 'km',    'name' => 'Khmer', 'native' => 'ភាសាខ្មែរ', 'flag' => '🇰🇭', 'tts' => 'km', 'speech' => 'km-KH', 'popular' => true, 'rtl' => false],
            ['code' => 'th',    'name' => 'Thai', 'native' => 'ภาษาไทย', 'flag' => '🇹🇭', 'tts' => 'th', 'speech' => 'th-TH', 'popular' => true, 'rtl' => false],
            ['code' => 'zh-CN', 'name' => 'Chinese (Simplified)', 'native' => '简体中文', 'flag' => '🇨🇳', 'tts' => 'zh-CN', 'speech' => 'zh-CN', 'popular' => true, 'rtl' => false],
            ['code' => 'zh-TW', 'name' => 'Chinese (Traditional)', 'native' => '繁體中文', 'flag' => '🇹🇼', 'tts' => 'zh-TW', 'speech' => 'zh-TW', 'popular' => true, 'rtl' => false],
            ['code' => 'ja',    'name' => 'Japanese', 'native' => '日本語', 'flag' => '🇯🇵', 'tts' => 'ja', 'speech' => 'ja-JP', 'popular' => true, 'rtl' => false],
            ['code' => 'ko',    'name' => 'Korean', 'native' => '한국어', 'flag' => '🇰🇷', 'tts' => 'ko', 'speech' => 'ko-KR', 'popular' => true, 'rtl' => false],
            ['code' => 'vi',    'name' => 'Vietnamese', 'native' => 'Tiếng Việt', 'flag' => '🇻🇳', 'tts' => 'vi', 'speech' => 'vi-VN', 'popular' => true, 'rtl' => false],
            ['code' => 'lo',    'name' => 'Lao', 'native' => 'ພາສາລາວ', 'flag' => '🇱🇦', 'tts' => 'lo', 'speech' => 'lo-LA', 'popular' => true, 'rtl' => false],
            ['code' => 'fr-FR', 'name' => 'French', 'native' => 'Français', 'flag' => '🇫🇷', 'tts' => 'fr', 'speech' => 'fr-FR', 'popular' => true, 'rtl' => false],
            ['code' => 'fr-CA', 'name' => 'French (Canada)', 'native' => 'Français (Canada)', 'flag' => '🇨🇦', 'tts' => 'fr-ca', 'speech' => 'fr-CA', 'popular' => false, 'rtl' => false],
            ['code' => 'de',    'name' => 'German', 'native' => 'Deutsch', 'flag' => '🇩🇪', 'tts' => 'de', 'speech' => 'de-DE', 'popular' => true, 'rtl' => false],
            ['code' => 'es-ES', 'name' => 'Spanish (Spain)', 'native' => 'Español (España)', 'flag' => '🇪🇸', 'tts' => 'es', 'speech' => 'es-ES', 'popular' => true, 'rtl' => false],
            ['code' => 'es-MX', 'name' => 'Spanish (Latin America)', 'native' => 'Español (Latinoamérica)', 'flag' => '🇲🇽', 'tts' => 'es-us', 'speech' => 'es-MX', 'popular' => false, 'rtl' => false],
            // All Additional Supported Languages
            ['code' => 'pt-PT', 'name' => 'Portuguese (Portugal)', 'native' => 'Português (Portugal)', 'flag' => '🇵🇹', 'tts' => 'pt-pt', 'speech' => 'pt-PT', 'popular' => false, 'rtl' => false],
            ['code' => 'pt-BR', 'name' => 'Portuguese (Brazil)', 'native' => 'Português (Brasil)', 'flag' => '🇧🇷', 'tts' => 'pt-br', 'speech' => 'pt-BR', 'popular' => false, 'rtl' => false],
            ['code' => 'it',    'name' => 'Italian', 'native' => 'Italiano', 'flag' => '🇮🇹', 'tts' => 'it', 'speech' => 'it-IT', 'popular' => false, 'rtl' => false],
            ['code' => 'ru',    'name' => 'Russian', 'native' => 'Русский', 'flag' => '🇷🇺', 'tts' => 'ru', 'speech' => 'ru-RU', 'popular' => false, 'rtl' => false],
            ['code' => 'ar',    'name' => 'Arabic', 'native' => 'العربية', 'flag' => '🇸🇦', 'tts' => 'ar', 'speech' => 'ar-SA', 'popular' => false, 'rtl' => true],
            ['code' => 'hi',    'name' => 'Hindi', 'native' => 'हिन्दी', 'flag' => '🇮🇳', 'tts' => 'hi', 'speech' => 'hi-IN', 'popular' => false, 'rtl' => false],
            ['code' => 'id',    'name' => 'Indonesian', 'native' => 'Bahasa Indonesia', 'flag' => '🇮🇩', 'tts' => 'id', 'speech' => 'id-ID', 'popular' => true, 'rtl' => false],
            ['code' => 'ms',    'name' => 'Malay', 'native' => 'Bahasa Melayu', 'flag' => '🇲🇾', 'tts' => 'ms', 'speech' => 'ms-MY', 'popular' => true, 'rtl' => false],
            ['code' => 'fil',   'name' => 'Filipino', 'native' => 'Filipino / Tagalog', 'flag' => '🇵🇭', 'tts' => 'tl', 'speech' => 'fil-PH', 'popular' => false, 'rtl' => false],
            ['code' => 'my',    'name' => 'Burmese', 'native' => 'မြန်မာဘာသာ', 'flag' => '🇲🇲', 'tts' => 'my', 'speech' => 'my-MM', 'popular' => false, 'rtl' => false],
            ['code' => 'tr',    'name' => 'Turkish', 'native' => 'Türkçe', 'flag' => '🇹🇷', 'tts' => 'tr', 'speech' => 'tr-TR', 'popular' => false, 'rtl' => false],
            ['code' => 'nl',    'name' => 'Dutch', 'native' => 'Nederlands', 'flag' => '🇳🇱', 'tts' => 'nl', 'speech' => 'nl-NL', 'popular' => false, 'rtl' => false],
            ['code' => 'sv',    'name' => 'Swedish', 'native' => 'Svenska', 'flag' => '🇸🇪', 'tts' => 'sv', 'speech' => 'sv-SE', 'popular' => false, 'rtl' => false],
            ['code' => 'pl',    'name' => 'Polish', 'native' => 'Polski', 'flag' => '🇵🇱', 'tts' => 'pl', 'speech' => 'pl-PL', 'popular' => false, 'rtl' => false],
            ['code' => 'uk',    'name' => 'Ukrainian', 'native' => 'Українська', 'flag' => '🇺🇦', 'tts' => 'uk', 'speech' => 'uk-UA', 'popular' => false, 'rtl' => false],
            ['code' => 'bn',    'name' => 'Bengali', 'native' => 'বাংলা', 'flag' => '🇧🇩', 'tts' => 'bn', 'speech' => 'bn-BD', 'popular' => false, 'rtl' => false],
            ['code' => 'ur',    'name' => 'Urdu', 'native' => 'اردو', 'flag' => '🇵🇰', 'tts' => 'ur', 'speech' => 'ur-PK', 'popular' => false, 'rtl' => true],
            ['code' => 'fa',    'name' => 'Persian', 'native' => 'فارسی', 'flag' => '🇮🇷', 'tts' => 'fa', 'speech' => 'fa-IR', 'popular' => false, 'rtl' => true],
            ['code' => 'he',    'name' => 'Hebrew', 'native' => 'עברית', 'flag' => '🇮🇱', 'tts' => 'iw', 'speech' => 'he-IL', 'popular' => false, 'rtl' => true],
            ['code' => 'el',    'name' => 'Greek', 'native' => 'Ελληνικά', 'flag' => '🇬🇷', 'tts' => 'el', 'speech' => 'el-GR', 'popular' => false, 'rtl' => false],
            ['code' => 'cs',    'name' => 'Czech', 'native' => 'Čeština', 'flag' => '🇨🇿', 'tts' => 'cs', 'speech' => 'cs-CZ', 'popular' => false, 'rtl' => false],
            ['code' => 'ro',    'name' => 'Romanian', 'native' => 'Română', 'flag' => '🇷🇴', 'tts' => 'ro', 'speech' => 'ro-RO', 'popular' => false, 'rtl' => false],
            ['code' => 'hu',    'name' => 'Hungarian', 'native' => 'Magyar', 'flag' => '🇭🇺', 'tts' => 'hu', 'speech' => 'hu-HU', 'popular' => false, 'rtl' => false],
            ['code' => 'da',    'name' => 'Danish', 'native' => 'Dansk', 'flag' => '🇩🇰', 'tts' => 'da', 'speech' => 'da-DK', 'popular' => false, 'rtl' => false],
            ['code' => 'fi',    'name' => 'Finnish', 'native' => 'Suomi', 'flag' => '🇫🇮', 'tts' => 'fi', 'speech' => 'fi-FI', 'popular' => false, 'rtl' => false],
            ['code' => 'no',    'name' => 'Norwegian', 'native' => 'Norsk', 'flag' => '🇳🇴', 'tts' => 'no', 'speech' => 'nb-NO', 'popular' => false, 'rtl' => false],
            ['code' => 'ta',    'name' => 'Tamil', 'native' => 'தமிழ்', 'flag' => '🇮🇳', 'tts' => 'ta', 'speech' => 'ta-IN', 'popular' => false, 'rtl' => false],
            ['code' => 'ne',    'name' => 'Nepali', 'native' => 'नेपाली', 'flag' => '🇳🇵', 'tts' => 'ne', 'speech' => 'ne-NP', 'popular' => false, 'rtl' => false],
            ['code' => 'si',    'name' => 'Sinhala', 'native' => 'සිංහල', 'flag' => '🇱🇰', 'tts' => 'si', 'speech' => 'si-LK', 'popular' => false, 'rtl' => false],
            ['code' => 'sw',    'name' => 'Swahili', 'native' => 'Kiswahili', 'flag' => '🇰🇪', 'tts' => 'sw', 'speech' => 'sw-KE', 'popular' => false, 'rtl' => false],
        ];
    }

    public static function supportedModes(): array
    {
        return [
            'standard'     => 'Natural and accurate everyday translation.',
            'professional' => 'Polished business and workplace communication.',
            'formal'       => 'Respectful, diplomatic, and official wording.',
            'casual'       => 'Friendly, natural everyday conversation.',
            'technical'    => 'Precise software, engineering, and technical terminology.',
            'marketing'    => 'Persuasive, brand-ready marketing and product copy.',
            'academic'     => 'Scholarly, structured academic and educational prose.',
            'literal'      => 'Close, faithful structural correspondence to the source wording.',
        ];
    }

    public function findLanguageMeta(string $code): ?array
    {
        if ($code === 'auto') {
            return ['code' => 'auto', 'name' => 'Detect Language', 'native' => 'Auto-Detect', 'flag' => '✨', 'tts' => 'en-us', 'speech' => 'en-US', 'rtl' => false];
        }
        $normalized = strtolower(trim($code));
        foreach (self::supportedLanguages() as $lang) {
            if (strtolower($lang['code']) === $normalized) {
                return $lang;
            }
        }
        // Fallback prefix match (e.g. 'en' -> 'en-US', 'zh' -> 'zh-CN', 'es' -> 'es-ES', 'fr' -> 'fr-FR', 'pt' -> 'pt-PT')
        foreach (self::supportedLanguages() as $lang) {
            if (str_starts_with(strtolower($lang['code']), $normalized . '-')) {
                return $lang;
            }
        }
        return null;
    }

    /**
     * Fast, conservative language detector that avoids false certainty on short/ambiguous inputs.
     */
    public function detectLanguage(string $text): array
    {
        $clean = trim($text);
        // Strip numbers, URLs, emails, and punctuation to measure meaningful linguistic characters
        $alphaOnly = preg_replace('/[0-9\s\p{P}\p{S}]+/u', '', $clean) ?? '';
        $charLen = mb_strlen($alphaOnly, 'UTF-8');

        if ($charLen === 0) {
            return [
                'detected_code' => null,
                'detected_name' => null,
                'confidence'    => 'ambiguous',
                'reliable'      => false,
            ];
        }

        // Distinct Unicode script ranges (reliable even for short words >= 2 characters)
        $scriptChecks = [
            'km'    => '/[\x{1780}-\x{17FF}\x{19E0}-\x{19FF}]/u',
            'th'    => '/[\x{0E00}-\x{0E7F}]/u',
            'lo'    => '/[\x{0E80}-\x{0EFF}]/u',
            'my'    => '/[\x{1000}-\x{109F}]/u',
            'ja'    => '/[\x{3040}-\x{30FF}]/u', // Hiragana / Katakana uniquely identify Japanese
            'ko'    => '/[\x{AC00}-\x{D7AF}\x{1100}-\x{11FF}]/u',
            'ar'    => '/[\x{0600}-\x{06FF}]/u',
            'hi'    => '/[\x{0900}-\x{097F}]/u',
            'bn'    => '/[\x{0980}-\x{09FF}]/u',
            'ta'    => '/[\x{0B80}-\x{0BFF}]/u',
            'si'    => '/[\x{0D80}-\x{0DFF}]/u',
            'he'    => '/[\x{0590}-\x{05FF}]/u',
            'el'    => '/[\x{0370}-\x{03FF}]/u',
        ];

        foreach ($scriptChecks as $code => $regex) {
            if (preg_match($regex, $alphaOnly)) {
                $meta = $this->findLanguageMeta($code);
                return [
                    'detected_code' => $meta['code'] ?? $code,
                    'detected_name' => $meta['name'] ?? strtoupper($code),
                    'flag'          => $meta['flag'] ?? '🌐',
                    'confidence'    => $charLen >= 2 ? 'high' : 'low',
                    'reliable'      => $charLen >= 2,
                ];
            }
        }

        // CJK Han characters (distinguish Traditional vs Simplified heuristics)
        if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $alphaOnly)) {
            $isTraditional = (bool) preg_match('/[體國學專業會發開關點車書長門語電]/u', $alphaOnly);
            $code = $isTraditional ? 'zh-TW' : 'zh-CN';
            $meta = $this->findLanguageMeta($code);
            return [
                'detected_code' => $code,
                'detected_name' => $meta['name'] ?? 'Chinese',
                'flag'          => $meta['flag'] ?? '🇨🇳',
                'confidence'    => $charLen >= 2 ? 'high' : 'low',
                'reliable'      => $charLen >= 2,
            ];
        }

        // Cyrillic (Ukrainian vs Russian markers)
        if (preg_match('/[\x{0400}-\x{04FF}]/u', $alphaOnly)) {
            $code = preg_match('/[іїєґІЇЄҐ]/u', $alphaOnly) ? 'uk' : 'ru';
            $meta = $this->findLanguageMeta($code);
            return [
                'detected_code' => $code,
                'detected_name' => $meta['name'] ?? 'Russian',
                'flag'          => $meta['flag'] ?? '🇷🇺',
                'confidence'    => $charLen >= 4 ? 'high' : 'low',
                'reliable'      => $charLen >= 4,
            ];
        }

        // Vietnamese Latin diacritics
        if (preg_match('/[ăâđêôơưĂÂĐÊÔƠƯảẩẫậắằẳẵặẻểễệỉỉnhỏổỗộớờởỡợủửữựỳỷỹỵ]/u', $alphaOnly)) {
            $meta = $this->findLanguageMeta('vi');
            return [
                'detected_code' => 'vi',
                'detected_name' => $meta['name'] ?? 'Vietnamese',
                'flag'          => $meta['flag'] ?? '🇻🇳',
                'confidence'    => 'high',
                'reliable'      => true,
            ];
        }

        // For short Latin-script text (< 8 alphabetic chars, e.g. "OK", "Hi", "Pro"), avoid false certainty
        if ($charLen < 8) {
            return [
                'detected_code' => null,
                'detected_name' => null,
                'confidence'    => 'ambiguous',
                'reliable'      => false,
            ];
        }

        $lower = ' ' . mb_strtolower($clean, 'UTF-8') . ' ';
        $latinHints = [
            'fr-FR' => [' le ', ' la ', ' les ', ' des ', ' est ', ' avec ', ' pour ', ' dans ', ' bonjour ', ' merci '],
            'es-ES' => [' el ', ' los ', ' las ', ' una ', ' para ', ' con ', ' por ', ' gracias ', ' hola ', ' está '],
            'de'    => [' der ', ' die ', ' das ', ' und ', ' nicht ', ' mit ', ' für ', ' danke ', ' bitte '],
            'pt-PT' => [' não ', ' para ', ' com ', ' uma ', ' obrigado ', ' está ', ' você ', ' são '],
            'it'    => [' che ', ' non ', ' per ', ' una ', ' sono ', ' grazie ', ' ciao ', ' della '],
            'id'    => [' yang ', ' dan ', ' untuk ', ' tidak ', ' dengan ', ' dari ', ' terima kasih ', ' saya '],
            'ms'    => [' awak ', ' kepada ', ' kerana ', ' adalah ', ' boleh '],
            'nl'    => [' het ', ' een ', ' van ', ' niet ', ' voor ', ' met ', ' dank '],
            'tr'    => [' bir ', ' için ', ' değil ', ' teşekkür ', ' merhaba ', ' olan '],
            'en-US' => [' the ', ' and ', ' with ', ' for ', ' that ', ' this ', ' from ', ' have ', ' please ', ' thank '],
        ];

        $bestCode = null;
        $bestScore = 0;
        foreach ($latinHints as $code => $tokens) {
            $score = 0;
            foreach ($tokens as $tok) {
                if (str_contains($lower, $tok)) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCode = $code;
            }
        }

        if ($bestCode && $bestScore >= 1) {
            $meta = $this->findLanguageMeta($bestCode);
            return [
                'detected_code' => $bestCode,
                'detected_name' => $meta['name'] ?? $bestCode,
                'flag'          => $meta['flag'] ?? '🌐',
                'confidence'    => $bestScore >= 2 ? 'high' : 'medium',
                'reliable'      => true,
            ];
        }

        // Default Latin fallback for longer English sentences without stopword hits
        if ($charLen >= 16 && preg_match('/^[a-zA-Z\s]+$/', $alphaOnly)) {
            $meta = $this->findLanguageMeta('en-US');
            return [
                'detected_code' => 'en-US',
                'detected_name' => $meta['name'] ?? 'English (United States)',
                'flag'          => '🇺🇸',
                'confidence'    => 'medium',
                'reliable'      => true,
            ];
        }

        return [
            'detected_code' => null,
            'detected_name' => null,
            'confidence'    => 'ambiguous',
            'reliable'      => false,
        ];
    }

    /**
     * Executes high-accuracy AI translation using existing KIUQ AI providers (Groq LPU + Gemini failover).
     */
    public function translateText(
        string $sourceText,
        string $sourceLanguage,
        string $targetLanguage,
        string $mode = 'standard',
        array $advancedOptions = []
    ): array {
        $targetMeta = $this->findLanguageMeta($targetLanguage)
            ?? ['code' => $targetLanguage, 'name' => $targetLanguage, 'native' => $targetLanguage];
        $sourceMeta = $this->findLanguageMeta($sourceLanguage);

        $heuristicDetection = $sourceLanguage === 'auto'
            ? $this->detectLanguage($sourceText)
            : [
                'detected_code' => $sourceMeta['code'] ?? $sourceLanguage,
                'detected_name' => $sourceMeta['name'] ?? $sourceLanguage,
                'reliable'      => true,
                'confidence'    => 'high',
            ];

        $systemPrompt = $this->buildTranslationSystemPrompt(
            sourceMeta: $sourceLanguage === 'auto' ? null : $sourceMeta,
            targetMeta: $targetMeta,
            mode: $mode,
            advancedOptions: $advancedOptions,
            requestDetectedHeader: $sourceLanguage === 'auto' && empty($heuristicDetection['reliable']),
        );

        $userPayload = "SOURCE_TEXT_START\n{$sourceText}\nSOURCE_TEXT_END";
        $rawOutput = '';
        $usedModel = '';
        $lastException = null;

        // Tier 1: Ultra-low-latency Groq Llama 3.3 70B Versatile (<0.7s) when configured
        if ($this->groqClient->isConfigured()) {
            try {
                $res = $this->groqClient->generateContent(
                    contents: [['role' => 'user', 'parts' => [['text' => $userPayload]]]],
                    systemInstruction: $systemPrompt,
                    modelOverride: 'llama-3.3-70b-versatile',
                );
                if (!empty(trim($res['text'] ?? ''))) {
                    $rawOutput = trim($res['text']);
                    $usedModel = 'llama-3.3-70b-versatile';
                }
            } catch (\Throwable $e) {
                $lastException = $e;
                Log::warning('KiuqTranslationService Groq tier failed, failing over to Gemini: ' . $e->getMessage());
            }
        }

        // Tier 2: Gemini Flash / Flash-Lite failover
        if ($rawOutput === '' && $this->geminiClient->isConfigured()) {
            foreach (['gemini-flash-lite-latest', 'gemini-3.5-flash'] as $geminiModel) {
                try {
                    $res = $this->geminiClient->generateContent(
                        contents: [['role' => 'user', 'parts' => [['text' => $userPayload]]]],
                        tools: [],
                        systemInstruction: $systemPrompt,
                        modelOverride: $geminiModel,
                    );
                    if (!empty(trim($res['text'] ?? ''))) {
                        $rawOutput = trim($res['text']);
                        $usedModel = $geminiModel;
                        break;
                    }
                } catch (\Throwable $e) {
                    $lastException = $e;
                    Log::warning("KiuqTranslationService Gemini [{$geminiModel}] failed: " . $e->getMessage());
                }
            }
        }

        if ($rawOutput === '') {
            throw new Exception(
                $lastException
                    ? 'Translation engine error: ' . $lastException->getMessage()
                    : 'No AI translation provider is currently configured.'
            );
        }

        [$detectedFromModel, $cleanTranslation] = $this->extractDetectedHeaderAndBody($rawOutput);

        $finalDetectedCode = $heuristicDetection['detected_code'] ?? $detectedFromModel;
        $finalDetectedMeta = $finalDetectedCode ? $this->findLanguageMeta($finalDetectedCode) : null;

        return [
            'translated_text'   => $cleanTranslation,
            'detected_language' => $finalDetectedMeta['code'] ?? $finalDetectedCode,
            'detected_name'     => $finalDetectedMeta['name'] ?? ($heuristicDetection['detected_name'] ?? null),
            'detected_flag'     => $finalDetectedMeta['flag'] ?? ($heuristicDetection['flag'] ?? null),
            'confidence'        => $heuristicDetection['confidence'] ?? ($finalDetectedCode ? 'medium' : 'ambiguous'),
            'provider_model'    => $usedModel,
        ];
    }

    /**
     * Multimodal OCR Image Text Extraction + Translation via Gemini Vision.
     */
    public function translateImageOcr(
        UploadedFile $imageFile,
        string $sourceLanguage,
        string $targetLanguage,
        string $mode = 'standard',
        array $advancedOptions = []
    ): array {
        if (!$this->geminiClient->isConfigured()) {
            throw new Exception('Image OCR translation requires GEMINI_API_KEY to be configured.');
        }

        $mimeType = $imageFile->getMimeType() ?: 'image/jpeg';
        $base64 = base64_encode((string) file_get_contents($imageFile->getRealPath()));

        $extractInstruction = <<<'PROMPT'
You are an exact optical character recognition (OCR) engine.
Extract ALL visible text from the provided image accurately, preserving line breaks, lists, numbers, product codes, URLs, and script characters (Khmer, Thai, Chinese, Japanese, Korean, English, etc.).
Rules:
- Output ONLY the extracted text itself.
- Do NOT describe the image, do NOT summarize, and do NOT add markdown fences or commentary.
- If there is zero readable text in the image, output exact token: [NO_TEXT_FOUND]
PROMPT;

        $ocrResponse = $this->geminiClient->generateContent(
            contents: [[
                'role'  => 'user',
                'parts' => [
                    ['text' => 'Extract all text from this image verbatim.'],
                    ['inlineData' => ['mimeType' => $mimeType, 'data' => $base64]],
                ],
            ]],
            tools: [],
            systemInstruction: $extractInstruction,
            modelOverride: 'gemini-flash-lite-latest',
        );

        $extracted = trim($ocrResponse['text'] ?? '');
        if ($extracted === '' || str_contains($extracted, '[NO_TEXT_FOUND]')) {
            throw new Exception('No readable text could be detected in the uploaded image.');
        }

        $translation = $this->translateText(
            sourceText: $extracted,
            sourceLanguage: $sourceLanguage,
            targetLanguage: $targetLanguage,
            mode: $mode,
            advancedOptions: $advancedOptions,
        );

        $translation['extracted_text'] = $extracted;
        return $translation;
    }

    /**
     * Server-side Speech-to-Text transcription (Groq Whisper large-v3-turbo + Gemini multimodal audio fallback)
     * for devices/WebViews where Web Speech API is unavailable.
     */
    public function transcribeSpeech(UploadedFile $audioFile, ?string $languageHint = null): array
    {
        $groqKey = (string) config('kiuq_ai.groq_api_key', '');
        $groqBase = rtrim((string) config('kiuq_ai.groq_base_url', 'https://api.groq.com/openai/v1'), '/');

        if ($groqKey !== '') {
            try {
                $multipart = Http::withToken($groqKey)
                    ->timeout(30)
                    ->attach(
                        'file',
                        (string) file_get_contents($audioFile->getRealPath()),
                        $audioFile->getClientOriginalName() ?: 'voice.webm'
                    );

                $fields = [
                    'model'           => 'whisper-large-v3-turbo',
                    'response_format' => 'verbose_json',
                ];
                if ($languageHint && $languageHint !== 'auto') {
                    $iso2 = strtolower(explode('-', $languageHint)[0]);
                    if (strlen($iso2) === 2) {
                        $fields['language'] = $iso2;
                    }
                }

                $resp = $multipart->post("{$groqBase}/audio/transcriptions", $fields);
                if ($resp->successful()) {
                    $json = $resp->json();
                    $transcript = trim((string) ($json['text'] ?? ''));
                    if ($transcript !== '') {
                        $detected = $this->detectLanguage($transcript);
                        return [
                            'success'           => true,
                            'text'              => $transcript,
                            'detected_language' => $detected['detected_code'] ?? ($json['language'] ?? null),
                            'provider'          => 'groq_whisper',
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Groq Whisper STT failed, trying Gemini audio fallback: ' . $e->getMessage());
            }
        }

        if ($this->geminiClient->isConfigured()) {
            $mime = $audioFile->getMimeType() ?: 'audio/webm';
            $base64 = base64_encode((string) file_get_contents($audioFile->getRealPath()));
            $res = $this->geminiClient->generateContent(
                contents: [[
                    'role'  => 'user',
                    'parts' => [
                        ['text' => 'Transcribe the spoken audio verbatim in its original language and script. Output ONLY the spoken words with no commentary.'],
                        ['inlineData' => ['mimeType' => $mime, 'data' => $base64]],
                    ],
                ]],
                tools: [],
                systemInstruction: 'You are a verbatim multilingual speech-to-text transcriber.',
                modelOverride: 'gemini-flash-lite-latest',
            );
            $transcript = trim($res['text'] ?? '');
            if ($transcript !== '') {
                $detected = $this->detectLanguage($transcript);
                return [
                    'success'           => true,
                    'text'              => $transcript,
                    'detected_language' => $detected['detected_code'],
                    'provider'          => 'gemini_audio',
                ];
            }
        }

        throw new Exception('Voice transcription could not recognize clear speech or speech service is unavailable.');
    }

    /**
     * Synthesizes natural multilingual speech MP3 for any supported language/locale.
     */
    public function synthesizeSpeech(string $text, string $languageCode): array
    {
        $meta = $this->findLanguageMeta($languageCode);
        $ttsLang = $meta['tts'] ?? strtolower($languageCode);

        $cleanText = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($cleanText === '') {
            throw new Exception('Cannot synthesize empty text.');
        }

        // Split into <= 140-char sentence chunks so long paragraphs play smoothly
        $sentences = preg_split('/(?<=[.!?។៕।\n])\s+/u', mb_substr($cleanText, 0, 1800, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY)
            ?: [mb_substr($cleanText, 0, 140, 'UTF-8')];

        $chunks = [];
        $buf = '';
        foreach ($sentences as $sentence) {
            if (mb_strlen($buf . ' ' . $sentence, 'UTF-8') < 140) {
                $buf = trim($buf . ' ' . $sentence);
            } else {
                if ($buf !== '') {
                    $chunks[] = $buf;
                }
                if (mb_strlen($sentence, 'UTF-8') >= 140) {
                    $len = mb_strlen($sentence, 'UTF-8');
                    for ($i = 0; $i < $len; $i += 130) {
                        $chunks[] = mb_substr($sentence, $i, 130, 'UTF-8');
                    }
                    $buf = '';
                } else {
                    $buf = $sentence;
                }
            }
        }
        if ($buf !== '') {
            $chunks[] = $buf;
        }

        $mp3Bytes = '';
        foreach ($chunks as $chunk) {
            $q = urlencode($chunk);
            $url = "https://translate.google.com/translate_tts?ie=UTF-8&q={$q}&tl={$ttsLang}&client=tw-ob";
            $resp = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                'Referer'    => 'https://translate.google.com/',
            ])->timeout(12)->get($url);

            if ($resp->successful() && strlen($resp->body()) > 100) {
                $mp3Bytes .= $resp->body();
            }
        }

        if ($mp3Bytes !== '') {
            $dir = 'ai_translate_tts';
            if (!Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
            }
            $filename = 'tts_' . time() . '_' . Str::random(8) . '.mp3';
            $relPath = "{$dir}/{$filename}";
            Storage::disk('public')->put($relPath, $mp3Bytes);

            return [
                'success'   => true,
                'audio_url' => asset("storage/{$relPath}"),
                'language'  => $ttsLang,
            ];
        }

        // Fallback to GeminiClientService voice generator for English/Khmer
        $fallback = $this->geminiClient->generateVoiceOver($cleanText, [
            'voice_name' => str_starts_with($ttsLang, 'km') ? 'Khmer-Female' : 'Kore',
        ]);
        if (!empty($fallback['success']) && !empty($fallback['audio_url'])) {
            return [
                'success'   => true,
                'audio_url' => $fallback['audio_url'],
                'language'  => $ttsLang,
            ];
        }

        throw new Exception('Text-to-speech audio is currently unavailable for this language.');
    }

    private function buildTranslationSystemPrompt(
        ?array $sourceMeta,
        array $targetMeta,
        string $mode,
        array $advancedOptions,
        bool $requestDetectedHeader
    ): string {
        $modes = self::supportedModes();
        $modeGuide = $modes[$mode] ?? $modes['standard'];
        $sourceLabel = $sourceMeta
            ? "{$sourceMeta['name']} ({$sourceMeta['code']})"
            : 'Automatically detected source language';
        $targetLabel = "{$targetMeta['name']} ({$targetMeta['code']} — {$targetMeta['native']})";

        $extraDirectives = [];
        if (!empty($advancedOptions['tone'])) {
            $extraDirectives[] = "- Tone: " . trim((string) $advancedOptions['tone']);
        }
        if (!empty($advancedOptions['audience'])) {
            $extraDirectives[] = "- Target Audience: " . trim((string) $advancedOptions['audience']);
        }
        if (!empty($advancedOptions['regional_variant'])) {
            $extraDirectives[] = "- Regional Language Variant: " . trim((string) $advancedOptions['regional_variant']);
        }
        if (!empty($advancedOptions['terminology'])) {
            $extraDirectives[] = "- Preferred Terminology / Glossary: " . trim((string) $advancedOptions['terminology']);
        }
        if (!empty($advancedOptions['instructions'])) {
            $extraDirectives[] = "- Additional User Instructions: " . trim((string) $advancedOptions['instructions']);
        }

        $advancedBlock = !empty($extraDirectives)
            ? "\nADVANCED CUSTOMIZATION:\n" . implode("\n", $extraDirectives)
            : '';

        $headerRule = $requestDetectedHeader
            ? "FIRST LINE REQUIREMENT: On the very first line, output `[DETECTED:<code>]` using the ISO/BCP-47 language code of the source text (e.g. `[DETECTED:en-US]`, `[DETECTED:km]`, `[DETECTED:fr-FR]`, or `[DETECTED:ambiguous]` if fewer than 2 clear words), followed by a newline and ONLY the translation."
            : "OUTPUT REQUIREMENT: Output ONLY the final translated text itself—no preamble, no explanations, no quotes, and no notes.";

        return <<<PROMPT
You are the KIUQ AI Professional Multi-Language Translation Engine.
Translate the text between `SOURCE_TEXT_START` and `SOURCE_TEXT_END` from {$sourceLabel} into {$targetLabel}.

TRANSLATION MODE: {$mode} — {$modeGuide}{$advancedBlock}

STRICT FIDELITY & NATURALNESS RULES:
1. Preserve the exact original meaning, intent, paragraph breaks, bullet/numbered lists, and markdown tables.
2. Preserve all personal/company names, numbers, currencies, dates, product model numbers, SKUs, technical specifications, code identifiers, URLs, and email addresses intact.
3. Do NOT summarize, shorten, censor, or rewrite into commentary. Translate directly and completely.
4. Idioms & Khmer/Asian Fluency: When translating from or into Khmer (ភាសាខ្មែរ), Thai, Lao, Vietnamese, Chinese, Japanese, or Korean, use authentic, natural phrasing that native speakers genuinely use in real life rather than awkward word-for-word literal calques (unless `literal` mode is explicitly selected).
5. {$headerRule}
PROMPT;
    }

    private function extractDetectedHeaderAndBody(string $raw): array
    {
        $detected = null;
        $cleaned = trim($raw);

        if (preg_match('/^\[DETECTED:([a-zA-Z\-]+)\]\s*/u', $cleaned, $m)) {
            $code = trim($m[1]);
            if (strtolower($code) !== 'ambiguous' && strtolower($code) !== 'auto') {
                $detected = $code;
            }
            $cleaned = trim((string) substr($cleaned, strlen($m[0])));
        }

        // Remove accidental echoes of delimiter markers if any model repeats them
        $cleaned = preg_replace('/^SOURCE_TEXT_START\s*/u', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s*SOURCE_TEXT_END$/u', '', $cleaned) ?? $cleaned;

        return [$detected, trim($cleaned)];
    }
}
