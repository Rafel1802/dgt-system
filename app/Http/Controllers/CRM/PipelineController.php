<?php

namespace App\Http\Controllers\CRM;

use App\Enums\DealStage;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\User;
use App\Services\CrmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PipelineController extends Controller
{
    public function __construct(
        private readonly CrmService $crmService
    ) {}

    /** Sales pipeline Kanban-style view */
    public function index(): View
    {
        $this->authorize('viewAny', Customer::class);

        $pipeline  = $this->crmService->getPipelineData(auth()->user());
        $customers = Customer::active()->get(['id', 'name', 'company']);
        $users     = User::active()->get(['id', 'name']);

        // Pipeline summary stats
        $totalActive = collect($pipeline)
            ->filter(fn($col) => ! in_array($col['stage'], [DealStage::Won, DealStage::Lost]))
            ->sum('total_value');

        return view('crm.pipeline', compact('pipeline', 'customers', 'users', 'totalActive'));
    }

    /** Store a new deal */
    public function storeDeal(Request $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validate([
            'customer_id'         => ['required', 'integer', 'exists:customers,id'],
            'title'               => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string', 'max:2000'],
            'value'               => ['required', 'numeric', 'min:0'],
            'probability'         => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'assigned_to'         => ['nullable', 'integer', 'exists:users,id'],
            'product_interests'   => ['nullable', 'array'],
        ]);

        $stage = DealStage::NewLead;

        $deal = Deal::create(array_merge($validated, [
            'stage'       => $stage->value,
            'created_by'  => auth()->id(),
            'probability' => $validated['probability'] ?? $stage->defaultProbability(),
            'position'    => Deal::where('stage', $stage->value)->max('position') + 1,
        ]));

        $deal->load(['customer:id,name,company', 'assignee:id,name,avatar']);

        return response()->json(['success' => true, 'deal' => $deal], 201);
    }

    /** Move deal to a new stage (drag-drop) */
    public function moveDeal(Request $request, Deal $deal): JsonResponse
    {
        $this->authorize('update', $deal->customer);

        $validated = $request->validate([
            'stage'    => ['required', Rule::enum(DealStage::class)],
            'position' => ['required', 'integer', 'min:0'],
        ]);

        $newStage = DealStage::tryFrom($validated['stage']);
        if (! $newStage) {
            return response()->json(['message' => 'Invalid stage.'], 422);
        }

        $deal->update([
            'stage'       => $newStage->value,
            'position'    => $validated['position'],
            'probability' => $newStage->defaultProbability(),
            'closed_at'   => in_array($newStage, [DealStage::Won, DealStage::Lost]) ? now() : null,
        ]);

        return response()->json(['success' => true, 'deal' => $deal->fresh()]);
    }

    /** Update deal details */
    public function updateDeal(Request $request, Deal $deal): JsonResponse
    {
        $this->authorize('update', $deal->customer);

        $validated = $request->validate([
            'title'               => ['required', 'string', 'max:255'],
            'value'               => ['required', 'numeric', 'min:0'],
            'probability'         => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'lost_reason'         => ['nullable', 'string', 'max:500'],
        ]);

        $deal->update($validated);

        return response()->json(['success' => true, 'deal' => $deal->fresh()]);
    }

    /** Delete a deal */
    public function destroyDeal(Deal $deal): JsonResponse
    {
        $this->authorize('delete', $deal->customer);
        $deal->delete();
        return response()->json(['success' => true]);
    }
}
