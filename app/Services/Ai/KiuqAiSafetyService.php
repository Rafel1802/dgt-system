<?php

namespace App\Services\Ai;

use App\Models\Ai\AiAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class KiuqAiSafetyService
{
    /**
     * Regex patterns for prohibited bulk or destructive commands.
     */
    private const DESTRUCTIVE_PATTERNS = [
        '/\bdelete\s+(all|every|each|entire)\s+(cards?|tasks?|items?)\b/i',
        '/\bdelete\s+(all|every|each|entire)\s+(boards?|workspaces?)\b/i',
        '/\bdelete\s+(all|every|each|entire)\s+(websites?|sites?|domains?)\b/i',
        '/\b(remove|destroy|drop|wipe|purge|clear)\s+(all|every|entire)\s+(cards?|boards?|websites?|data|tasks?)\b/i',
        '/\b(truncate|drop\s+table|drop\s+database|wipe\s+database|empty\s+database|reset\s+database)\b/i',
        '/\b(delete|remove)\s+(everything|all\s+data|all\s+records)\b/i',
        '/\bclear\s+all\s+(task|board|card|website)\s+data\b/i',
    ];

    /**
     * Inspect a user prompt for prohibited destructive patterns.
     * Returns an array with [is_destructive => bool, reason => string|null].
     */
    public function inspectPrompt(string $prompt, ?User $user = null): array
    {
        $clean = trim($prompt);

        foreach (self::DESTRUCTIVE_PATTERNS as $pattern) {
            if (preg_match($pattern, $clean)) {
                $reason = "Corporate safety policy strictly prohibits bulk deletion, database truncation, or mass data removal. This request cannot be executed under any circumstances.";
                
                $this->logSecurityBlock($user, 'destructive_prompt_blocked', [
                    'prompt'  => $clean,
                    'matched' => $pattern,
                ], $reason);

                return [
                    'is_destructive' => true,
                    'reason'         => $reason,
                ];
            }
        }

        return [
            'is_destructive' => false,
            'reason'         => null,
        ];
    }

    /**
     * Inspect a function call tool and its arguments for bulk or destructive attempts.
     */
    public function inspectToolCall(string $toolName, array $args, ?User $user = null): array
    {
        $lowerTool = strtolower($toolName);

        // Block any delete-all or truncate tools
        if (str_contains($lowerTool, 'delete_all') || str_contains($lowerTool, 'truncate') || str_contains($lowerTool, 'wipe')) {
            $reason = "Destructive tool execution '{$toolName}' is permanently blocked by system policy.";
            $this->logSecurityBlock($user, $toolName, $args, $reason);
            return [
                'allowed' => false,
                'reason'  => $reason,
            ];
        }

        // Check if arguments specify 'all' or bulk flags on destructive tools
        if (str_contains($lowerTool, 'delete') || str_contains($lowerTool, 'remove') || str_contains($lowerTool, 'destroy') || str_contains($lowerTool, 'clear')) {
            foreach ($args as $key => $val) {
                if (is_string($val) && in_array(strtolower(trim($val)), ['all', '*', 'everything', 'true_all'])) {
                    if (str_contains(strtolower($key), 'card') || str_contains(strtolower($key), 'board') || str_contains(strtolower($key), 'website')) {
                        $reason = "Bulk execution on all records for argument '{$key}' is prohibited.";
                        $this->logSecurityBlock($user, $toolName, $args, $reason);
                        return [
                            'allowed' => false,
                            'reason'  => $reason,
                        ];
                    }
                }
            }
        }

        return [
            'allowed' => true,
            'reason'  => null,
        ];
    }

    /**
     * Log a blocked security event into ai_audit_logs.
     */
    public function logSecurityBlock(?User $user, string $action, array $payload, string $reason): void
    {
        try {
            AiAuditLog::create([
                'user_id'     => $user?->id,
                'action'      => $action,
                'target_type' => 'SecurityGuardrail',
                'target_id'   => null,
                'payload'     => $payload,
                'ip_address'  => Request::ip() ?? '127.0.0.1',
                'status'      => 'blocked',
                'reason'      => $reason,
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // Fail safely without throwing
        }
    }

    /**
     * Log a successful or failed action into ai_audit_logs.
     */
    public function logAction(?User $user, string $action, ?string $targetType = null, ?int $targetId = null, array $payload = [], string $status = 'success', ?string $reason = null): void
    {
        try {
            AiAuditLog::create([
                'user_id'     => $user?->id,
                'action'      => $action,
                'target_type' => $targetType,
                'target_id'   => $targetId,
                'payload'     => $payload,
                'ip_address'  => Request::ip() ?? '127.0.0.1',
                'status'      => $status,
                'reason'      => $reason,
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // Fail safely without throwing
        }
    }
}
