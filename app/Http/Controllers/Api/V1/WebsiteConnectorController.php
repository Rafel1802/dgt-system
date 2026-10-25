<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Models\WebsiteIntegration;
use App\Services\WebsiteSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class WebsiteConnectorController extends Controller
{
    public function __construct(
        private readonly WebsiteSyncService $syncService
    ) {}

    /**
     * Handshake endpoint: verifies credentials and returns server status.
     * GET /api/v1/connector/handshake
     */
    public function handshake(Request $request): JsonResponse
    {
        /** @var WebsiteIntegration $integration */
        $integration = $request->attributes->get('integration');
        /** @var Website $website */
        $website = $request->attributes->get('website');

        return response()->json([
            'success'            => true,
            'message'            => 'Handshake successful. Connected to DGT Central System.',
            'website_id'         => $website->id,
            'website_name'       => $website->name,
            'domain'             => $website->clean_domain,
            'is_verified'        => (bool) $integration->is_verified,
            'cms_status'         => $integration->cms_status,
            'analytics_status'   => $integration->analytics_status,
            'server_time'        => time(),
            'server_timezone'    => config('app.timezone'),
            'api_version'        => 'v1',
            'supported_features' => [
                'posts_sync',
                'views_tracking',
                'daily_snapshots',
                'health_ping',
                'tech_detection',
            ],
        ]);
    }

    /**
     * Domain Verification endpoint.
     * POST /api/v1/connector/verify
     */
    public function verify(Request $request): JsonResponse
    {
        /** @var WebsiteIntegration $integration */
        $integration = $request->attributes->get('integration');
        /** @var Website $website */
        $website = $request->attributes->get('website');

        $method = $request->input('method', 'direct_handshake');

        $integration->update([
            'is_verified'         => true,
            'verified_at'         => now(),
            'verification_method' => $method,
            'cms_status'          => WebsiteIntegration::CMS_STATUS_CONNECTED,
            'last_ping_at'        => now(),
        ]);

        return response()->json([
            'success'     => true,
            'message'     => "Website '{$website->name}' successfully verified.",
            'website_id'  => $website->id,
            'verified_at' => $integration->verified_at->toIso8601String(),
        ]);
    }

    /**
     * Health check and environment ping.
     * POST /api/v1/connector/ping
     */
    public function ping(Request $request): JsonResponse
    {
        /** @var WebsiteIntegration $integration */
        $integration = $request->attributes->get('integration');
        /** @var Website $website */
        $website = $request->attributes->get('website');

        $techStack = $request->input('tech_stack', []);
        $hostinger = $request->input('hostinger_account');
        $connectorType = $request->input('connector_type');

        $updates = [
            'last_ping_at' => now(),
            'cms_status'   => WebsiteIntegration::CMS_STATUS_CONNECTED,
        ];

        if (!empty($techStack) && is_array($techStack)) {
            $updates['tech_stack'] = array_merge($integration->tech_stack ?? [], $techStack);
        }

        if (!empty($hostinger)) {
            $updates['hostinger_account'] = $hostinger;
        }

        if (!empty($connectorType) && in_array($connectorType, [
            WebsiteIntegration::CONNECTOR_WP_PLUGIN,
            WebsiteIntegration::CONNECTOR_WP_REST,
            WebsiteIntegration::CONNECTOR_LARAVEL,
            WebsiteIntegration::CONNECTOR_PHP,
            WebsiteIntegration::CONNECTOR_API,
        ])) {
            $updates['connector_type'] = $connectorType;
        }

        $integration->update($updates);

        return response()->json([
            'success'      => true,
            'status'       => 'pong',
            'server_time'  => time(),
            'website_id'   => $website->id,
            'last_sync_at' => $integration->last_sync_at?->toIso8601String(),
        ]);
    }

    /**
     * Push posts and view counts to DGT Central System.
     * POST /api/v1/connector/sync/posts
     */
    public function syncPosts(Request $request): JsonResponse
    {
        /** @var WebsiteIntegration $integration */
        $integration = $request->attributes->get('integration');
        /** @var Website $website */
        $website = $request->attributes->get('website');

        $validator = Validator::make($request->all(), [
            'posts'                      => ['required', 'array'],
            'posts.*.external_id'        => ['required'],
            'posts.*.title'              => ['required', 'string'],
            'posts.*.canonical_url'      => ['nullable', 'string'],
            'posts.*.status'             => ['nullable', 'string'],
            'posts.*.cumulative_views'   => ['nullable', 'integer', 'min:0'],
            'posts.*.period_views'       => ['nullable', 'integer', 'min:0'],
            'posts.*.page_views'         => ['nullable', 'integer', 'min:0'],
            'posts.*.unique_visitors'    => ['nullable', 'integer', 'min:0'],
            'posts.*.daily_snapshots'    => ['nullable', 'array'],
            'posts.*.daily_snapshots.*.date' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error'   => 'validation_error',
                'details' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $postsData = $request->input('posts', []);
        $triggeredBy = $request->input('sync_source', 'api_push');

        $result = $this->syncService->ingestPosts(
            $website,
            $postsData,
            $triggeredBy,
            null,
            $request->ip()
        );

        return response()->json([
            'success'        => true,
            'website_id'     => $website->id,
            'total_received' => count($postsData),
            'items_synced'   => $result['items_synced'],
            'items_updated'  => $result['items_updated'],
            'items_skipped'  => $result['items_skipped'],
            'errors'         => $result['errors'],
            'synced_at'      => now()->toIso8601String(),
        ]);
    }
}
