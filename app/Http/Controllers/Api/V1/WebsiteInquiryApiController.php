<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Models\WebsiteIntegration;
use App\Services\WebsiteInquiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebsiteInquiryApiController extends Controller
{
    public function __construct(
        protected WebsiteInquiryService $inquiryService
    ) {}

    /**
     * Ingest an inquiry from a connected website.
     * Authenticated via auth.connector (HMAC SHA-256, API Key/Secret, or Bearer token).
     */
    public function store(Request $request): JsonResponse
    {
        /** @var Website|null $website */
        $website = $request->attributes->get('website');
        /** @var WebsiteIntegration|null $integration */
        $integration = $request->attributes->get('integration');

        if (!$website) {
            return response()->json([
                'success' => false,
                'error'   => 'unauthorized',
                'message' => 'No authenticated website context found.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Validate basic payload requirements
        $request->validate([
            'customer_name'  => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:60',
            'subject'        => 'nullable|string|max:255',
            'message'        => 'nullable|string',
            'inquiry_type'   => 'nullable|string|max:50',
            'form_name'      => 'nullable|string|max:150',
            'source_url'     => 'nullable|string|max:2048',
        ]);

        $uploadedFiles = array_merge(
            $request->file('attachments') ?? [],
            $request->file('files') ?? [],
            $request->file('attachment') ? [$request->file('attachment')] : []
        );

        // Normalize flat uploaded files
        $flatFiles = [];
        array_walk_recursive($uploadedFiles, function ($file) use (&$flatFiles) {
            if ($file instanceof \Illuminate\Http\UploadedFile) {
                $flatFiles[] = $file;
            }
        });

        $inquiry = $this->inquiryService->ingestInquiry(
            $website,
            $request->all(),
            $integration,
            $flatFiles
        );

        return response()->json([
            'success'        => true,
            'message'        => 'Inquiry successfully received and queued.',
            'inquiry_id'     => $inquiry->id,
            'inquiry_type'   => $inquiry->inquiry_type,
            'status'         => $inquiry->status,
            'customer_name'  => $inquiry->customer_name,
            'submitted_at'   => $inquiry->submitted_at?->toIso8601String(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Health check and integration verification ping for inquiries.
     */
    public function ping(Request $request): JsonResponse
    {
        /** @var Website|null $website */
        $website = $request->attributes->get('website');

        return response()->json([
            'success'   => true,
            'message'   => 'DGT Inquiries API is connected and ready.',
            'website'   => [
                'id'     => $website?->id,
                'name'   => $website?->name,
                'domain' => $website?->domain,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
