<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Models\WebsiteFollowUp;
use App\Services\WebsitesDashboardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteController extends Controller
{
    use FormatsApiResponses;

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->paginated(
            Website::with(['handler:id,name,avatar', 'creator:id,name', 'followUps' => fn ($query) => $query->latest()->limit(3)])
                ->where('is_archived', false)
                ->when($request->filled('q'), function (Builder $query) use ($request) {
                    $term = $request->string('q')->toString();
                    $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('url', 'like', "%{$term}%"));
                })
                ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
                ->when($request->filled('category'), fn (Builder $query) => $query->where('category', $request->string('category')->toString()))
                ->orderBy('category')
                ->orderBy('name')
                ->paginate($request->integer('per_page', 25))
        ));
    }

    public function statuses(): JsonResponse
    {
        return response()->json([
            'statuses' => Website::STATUSES,
            'progress_steps' => Website::PROGRESS_STEPS,
            'follow_up_types' => Website::FOLLOW_UP_TYPES,
        ]);
    }

    public function followUps(Request $request): JsonResponse
    {
        return response()->json($this->paginated(
            WebsiteFollowUp::with(['website:id,name,url', 'assignee:id,name,avatar', 'qcChecker:id,name', 'creator:id,name'])
                ->when($request->filled('website_id'), fn (Builder $query) => $query->where('website_id', $request->integer('website_id')))
                ->when($request->filled('qc_status'), fn (Builder $query) => $query->where('qc_status', $request->string('qc_status')->toString()))
                ->latest()
                ->paginate($request->integer('per_page', 25))
        ));
    }

    public function weeklyReport(Request $request, WebsitesDashboardService $dashboardService): JsonResponse
    {
        $filters = [
            'date_scope' => $request->string('date_scope', 'week')->toString(),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'member_id' => $request->integer('member_id') ?: null,
            'site_ids' => array_filter(array_map('intval', (array) $request->input('site_ids', []))),
        ];

        return response()->json($dashboardService->aggregate($filters));
    }
}
