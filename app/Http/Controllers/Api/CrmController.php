<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\EbayCustomerRecord;
use App\Models\EbayOffer;
use App\Models\EbayStore;
use App\Models\Lead;
use App\Models\Logistic;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmController extends Controller
{
    use FormatsApiResponses;

    public function customers(Request $request): JsonResponse
    {
        return response()->json($this->paginated(
            Customer::with(['assignee:id,name,avatar', 'creator:id,name'])
                ->when($request->filled('q'), fn (Builder $query) => $query->search($request->string('q')->toString()))
                ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
                ->latest()
                ->paginate($request->integer('per_page', 25))
        ));
    }

    public function logistics(Request $request): JsonResponse
    {
        return response()->json($this->paginated(
            Logistic::with(['customer:id,name,email,phone', 'product:id,name,sku', 'truckingCompany:id,company_name', 'assignee:id,name,avatar'])
                ->when($request->filled('q'), fn (Builder $query) => $query->search($request->string('q')->toString()))
                ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
                ->latest()
                ->paginate($request->integer('per_page', 25))
        ));
    }

    public function storeLogistic(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'product_id' => ['nullable', 'exists:products,id'],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'trucking_company_id' => ['nullable', 'exists:trucking_companies,id'],
            'order_id' => ['nullable', 'string', 'max:255'],
            'product_description' => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:100'],
            'shipping_budget' => ['nullable', 'numeric'],
            'final_shipping_cost' => ['nullable', 'numeric'],
            'currency' => ['nullable', 'string', 'max:10'],
            'pickup_datetime' => ['nullable', 'date'],
            'estimated_arrival' => ['nullable', 'date'],
            'actual_arrival' => ['nullable', 'date'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $logistic = Logistic::create([...$validated, 'created_by' => $request->user()->id]);

        return response()->json(['logistic' => $logistic->load(['customer', 'product', 'truckingCompany', 'assignee'])], 201);
    }

    public function updateLogistic(Request $request, Logistic $logistic): JsonResponse
    {
        $validated = $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
            'trucking_company_id' => ['nullable', 'exists:trucking_companies,id'],
            'shipping_address' => ['nullable', 'string'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:100'],
            'shipping_budget' => ['nullable', 'numeric'],
            'final_shipping_cost' => ['nullable', 'numeric'],
            'pickup_datetime' => ['nullable', 'date'],
            'estimated_arrival' => ['nullable', 'date'],
            'actual_arrival' => ['nullable', 'date'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $logistic->update($validated);

        return response()->json(['logistic' => $logistic->fresh(['customer', 'product', 'truckingCompany', 'assignee'])]);
    }

    public function deleteLogistic(Logistic $logistic): JsonResponse
    {
        $logistic->delete();
        return response()->json(['message' => 'Logistic item deleted.']);
    }

    public function shipments(Request $request): JsonResponse
    {
        return response()->json($this->paginated(
            Shipment::with(['truckingCompany:id,company_name', 'assignee:id,name,avatar', 'shipmentCustomers'])
                ->when($request->filled('q'), fn (Builder $query) => $query->search($request->string('q')->toString()))
                ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
                ->latest()
                ->paginate($request->integer('per_page', 25))
        ));
    }

    public function ebayStores(Request $request): JsonResponse
    {
        return response()->json($this->paginated(
            EbayStore::with('handler:id,name,avatar')
                ->withCount(['offers', 'orders'])
                ->when($request->filled('q'), fn (Builder $query) => $query->search($request->string('q')->toString()))
                ->latest()
                ->paginate($request->integer('per_page', 25))
        ));
    }

    public function ebayOffers(Request $request): JsonResponse
    {
        return response()->json($this->paginated(
            EbayOffer::with(['store:id,store_name,logo_url', 'customer:id,name,email', 'handler:id,name,avatar', 'product:id,name,sku'])
                ->when($request->filled('q'), fn (Builder $query) => $query->search($request->string('q')->toString()))
                ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
                ->latest('received_at')
                ->paginate($request->integer('per_page', 25))
        ));
    }

    public function ebayCustomerRecords(Request $request): JsonResponse
    {
        $tab = $request->string('tab', EbayCustomerRecord::TAB_URGENT)->toString();

        return response()->json([
            ...$this->paginated(
                EbayCustomerRecord::with(['store:id,store_name,logo_url', 'creator:id,name', 'updater:id,name'])
                    ->forTab($tab)
                    ->when($request->filled('q'), fn (Builder $query) => $query->search($request->string('q')->toString()))
                    ->latest()
                    ->paginate($request->integer('per_page', 25))
            ),
            'tabs' => EbayCustomerRecord::tabs(),
            'columns' => EbayCustomerRecord::columnsForTab($tab),
        ]);
    }

    public function websiteLeads(Request $request): JsonResponse
    {
        return response()->json($this->paginated(
            Lead::with(['customer:id,name,email', 'handler:id,name,avatar', 'assignee:id,name,avatar', 'product:id,name,sku'])
                ->when($request->filled('q'), fn (Builder $query) => $query->search($request->string('q')->toString()))
                ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
                ->latest('received_at')
                ->paginate($request->integer('per_page', 25))
        ));
    }
}
