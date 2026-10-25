<?php

namespace App\Http\Middleware;

use App\Models\WebsiteIntegration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWebsiteConnector
{
    /**
     * Handle an incoming website connector request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-DGT-Key');
        $signature = $request->header('X-DGT-Signature');
        $timestamp = $request->header('X-DGT-Timestamp');
        $nonce = $request->header('X-DGT-Nonce');
        $secret = $request->header('X-DGT-Secret');

        // Allow Authorization: Bearer <token> fallback
        if (!$apiKey && $request->bearerToken()) {
            $bearer = $request->bearerToken();
            // Bearer can be "apiKey:secret" or just apiKey if header secret present
            if (str_contains($bearer, ':')) {
                [$apiKey, $secret] = explode(':', $bearer, 2);
            } else {
                $apiKey = $bearer;
            }
        }

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'error'   => 'unauthorized',
                'message' => 'Missing integration API key header (X-DGT-Key or Authorization Bearer).',
            ], Response::HTTP_UNAUTHORIZED);
        }

        /** @var WebsiteIntegration|null $integration */
        $integration = WebsiteIntegration::with('website')
            ->where('api_key', $apiKey)
            ->where('is_active', true)
            ->first();

        if (!$integration || !$integration->website) {
            return response()->json([
                'success' => false,
                'error'   => 'invalid_key',
                'message' => 'Invalid, inactive, or revoked integration credential.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Authenticate via HMAC Signature or Direct Secret
        $authenticated = false;

        if ($signature && $timestamp) {
            // Replay protection: verify timestamp within 300 seconds
            if (abs(time() - (int)$timestamp) > 300) {
                return response()->json([
                    'success' => false,
                    'error'   => 'timestamp_expired',
                    'message' => 'Request timestamp is outside the allowed 5-minute drift window.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Nonce verification if provided
            if ($nonce) {
                $cacheKey = 'dgt_nonce_' . md5($apiKey . ':' . $nonce);
                if (!Cache::add($cacheKey, true, 300)) {
                    return response()->json([
                        'success' => false,
                        'error'   => 'replay_detected',
                        'message' => 'Replay protection error: Nonce has already been consumed.',
                    ], Response::HTTP_UNAUTHORIZED);
                }
            }

            $rawPayload = $request->isMethod('GET') ? '' : (string) ($request->getContent() ?: '');
            $authenticated = $integration->verifySignature($signature, $timestamp, $rawPayload);
        } elseif ($secret) {
            $authenticated = hash_equals($integration->api_secret, $secret);
        }

        if (!$authenticated) {
            return response()->json([
                'success' => false,
                'error'   => 'invalid_signature',
                'message' => 'Invalid HMAC signature or API secret.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Security check: Client-supplied website ID must match credential owner
        $requestedWebsiteId = $request->input('website_id') ?? $request->route('website_id');
        if ($requestedWebsiteId && (int)$requestedWebsiteId !== (int)$integration->website_id) {
            return response()->json([
                'success' => false,
                'error'   => 'website_mismatch',
                'message' => 'Authenticated credential does not belong to the requested website ID.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Update ping timestamp and connection status if needed
        $integration->forceFill([
            'last_ping_at' => now(),
            'cms_status'   => $integration->cms_status === WebsiteIntegration::CMS_STATUS_DISCONNECTED
                ? WebsiteIntegration::CMS_STATUS_CONNECTED
                : $integration->cms_status,
        ])->saveQuietly();

        // Pass resolved models downstream
        $request->attributes->set('integration', $integration);
        $request->attributes->set('website', $integration->website);

        return $next($request);
    }
}
