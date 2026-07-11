<?php

namespace App\Http\Controllers\CRM;

use App\Enums\CustomerStatus;
use App\Enums\CustomerSource;
use App\Enums\LogisticStatus;
use App\Enums\ProductCategory;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\EbayOrder;
use App\Models\Lead;
use App\Models\Logistic;
use App\Models\LogisticUpdate;
use App\Models\Product;
use App\Models\User;
use App\Services\CrmCustomerMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LogisticCrmController extends Controller
{
    public function __construct(private readonly CrmCustomerMatchService $matcher)
    {
    }

    public function index(Request $request): View
    {
        $query = Logistic::with(['customer', 'product', 'assignee']);

        if ($s  = $request->get('search'))  $query->search($s);
        if ($st = $request->get('status'))  $query->where('status', $st);

        $logistics = $query->latest()->paginate(20)->withQueryString();
        $statuses  = LogisticStatus::cases();

        return view('crm.logistics.index', compact('logistics', 'statuses'));
    }

    public function create(): View
    {
        return view('crm.logistics.create', [
            'statuses'         => LogisticStatus::cases(),
            'products'         => Product::active()->orderBy('name')->get(),
            'customers'        => Customer::orderBy('name')->get(['id', 'name', 'email', 'company', 'phone', 'address']),
            'crmUsers'         => User::crmMembers()->orderBy('name')->get(),
            'ebayOrders'       => EbayOrder::confirmed()->with('customer')->latest()->limit(50)->get(),
            'truckingCompanies'=> \App\Models\TruckingCompany::active()->orderBy('company_name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'customer_id'        => ['required', 'exists:customers,id'],
            'product_id'         => ['nullable', 'exists:products,id'],
            'ebay_order_id'      => ['nullable', 'exists:ebay_orders,id'],
            'lead_id'            => ['nullable', 'exists:leads,id'],
            'order_id'           => ['nullable', 'string', 'max:100'],
            'product_description'=> ['nullable', 'string'],
            'shipping_address'   => ['required', 'string'],
            'recipient_name'     => ['required', 'string', 'max:255'],
            'recipient_phone'    => ['required', 'string', 'max:30'],
            'truck_company'      => ['nullable', 'string'],
            'driver_name'        => ['nullable', 'string'],
            'driver_phone'       => ['nullable', 'string'],
            'shipping_budget'    => ['nullable', 'numeric'],
            'estimated_arrival'  => ['nullable', 'date'],
            'assigned_to'        => ['nullable', 'exists:users,id'],
            'notes'              => ['nullable', 'string'],
        ]);

        $logistic = Logistic::create([...$v, 'created_by' => auth()->id(), 'status' => LogisticStatus::OrderConfirmed->value]);

        LogisticUpdate::create([
            'logistic_id' => $logistic->id,
            'user_id'     => auth()->id(),
            'status'      => LogisticStatus::OrderConfirmed->value,
            'notes'       => 'Logistic record created.',
            'occurred_at' => now(),
        ]);

        return redirect()->route('crm.logistics.show', $logistic)->with('success', 'Shipment created.');
    }

    public function show(Logistic $logistic): View
    {
        $logistic->load(['customer', 'product', 'assignee', 'updates.user', 'attachments', 'ebayOrder']);

        return view('crm.logistics.show', [
            'logistic' => $logistic,
            'statuses' => LogisticStatus::cases(),
        ]);
    }

    public function edit(Logistic $logistic): View
    {
        return view('crm.logistics.edit', [
            'logistic'         => $logistic,
            'statuses'         => LogisticStatus::cases(),
            'products'         => Product::active()->orderBy('name')->get(),
            'crmUsers'         => User::crmMembers()->orderBy('name')->get(),
            'truckingCompanies'=> \App\Models\TruckingCompany::active()->orderBy('company_name')->get(),
        ]);
    }

    public function update(Request $request, Logistic $logistic): RedirectResponse
    {
        $v = $request->validate([
            'shipping_address'    => ['required', 'string'],
            'recipient_name'      => ['required', 'string'],
            'recipient_phone'     => ['required', 'string'],
            'truck_company'       => ['nullable', 'string'],
            'driver_name'         => ['nullable', 'string'],
            'driver_phone'        => ['nullable', 'string'],
            'shipping_budget'     => ['nullable', 'numeric'],
            'final_shipping_cost' => ['nullable', 'numeric'],
            'tracking_number'     => ['nullable', 'string'],
            'pickup_datetime'     => ['nullable', 'date'],
            'estimated_arrival'   => ['nullable', 'date'],
            'actual_arrival'      => ['nullable', 'date'],
            'notes'               => ['nullable', 'string'],
            'assigned_to'         => ['nullable', 'exists:users,id'],
        ]);

        $logistic->update($v);

        return redirect()->route('crm.logistics.show', $logistic)->with('success', 'Shipment updated.');
    }

    public function pushStatus(Request $request, Logistic $logistic): JsonResponse
    {
        $request->validate([
            'status' => ['required', Rule::enum(LogisticStatus::class)],
            'notes' => ['nullable', 'string'],
            'tracking_number' => ['nullable', 'string'],
        ]);

        $logistic->update(['status' => $request->status]);
        if ($request->filled('tracking_number')) {
            $logistic->update(['tracking_number' => $request->tracking_number]);
        }

        $update = LogisticUpdate::create([
            'logistic_id' => $logistic->id,
            'user_id'     => auth()->id(),
            'status'      => $request->status,
            'notes'       => $request->notes,
            'occurred_at' => now(),
        ]);

        return response()->json([
            'message' => 'Status updated to: ' . LogisticStatus::from($request->status)->label(),
            'update'  => $update->load('user'),
        ]);
    }

    public function uploadProof(Request $request, Logistic $logistic): JsonResponse
    {
        $request->validate(['proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240']]);

        $path = $request->file('proof')->store('logistics/proof', 'public');
        $logistic->update(['delivery_proof' => $path, 'status' => LogisticStatus::Delivered->value, 'actual_arrival' => today()]);

        LogisticUpdate::create([
            'logistic_id' => $logistic->id,
            'user_id'     => auth()->id(),
            'status'      => LogisticStatus::Delivered->value,
            'notes'       => 'Delivery proof uploaded.',
            'attachment'  => $path,
            'occurred_at' => now(),
        ]);

        return response()->json(['message' => 'Delivered!', 'proof_url' => asset('storage/' . $path)]);
    }

    public function destroy(Logistic $logistic): RedirectResponse
    {
        abort_unless(auth()->user()->canDeleteCrmRecords('logistic'), 403, 'Only a Logistic Supervisor, CRM Supervisor, or Boss can delete shipments.');

        $logistic->delete();
        return redirect()->route('crm.logistics.index')->with('success', 'Shipment deleted.');
    }

    // ── AJAX: Customer search ────────────────────────────────────────────────

    public function searchCustomers(Request $request): JsonResponse
    {
        $term = $request->get('q', '');
        $customers = Customer::when($term, fn($q) => $q->search($term))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'phone', 'company']);

        return response()->json($customers->map(fn($c) => [
            'id'    => $c->id,
            'text'  => $c->name . ($c->company ? ' — ' . $c->company : '') . ($c->phone ? ' · ' . $c->phone : ''),
            'name'  => $c->name,
            'phone' => $c->phone,
            'email' => $c->email,
        ]));
    }

    // ── AJAX: Product search ─────────────────────────────────────────────────

    public function searchProducts(Request $request): JsonResponse
    {
        $term = $request->get('q', '');
        $products = Product::active()
            ->when($term, fn($q) => $q->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'category', 'sku']);

        return response()->json($products->map(fn($p) => [
            'id'   => $p->id,
            'text' => ($p->category?->icon() ?? '') . ' ' . $p->name . ($p->sku ? ' (' . $p->sku . ')' : ''),
            'name' => $p->name,
        ]));
    }

    // ── AJAX: Quick-create Customer ──────────────────────────────────────────

    public function quickCreateCustomer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:30'],
            'email'   => ['nullable', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
        ]);

        $customer = $this->matcher->findCustomerByContact($validated['email'] ?? null, $validated['phone'] ?? null);

        if (! $customer) {
            $customer = Customer::create([
                ...$validated,
                'status'     => CustomerStatus::Lead->value,
                'source'     => CustomerSource::Logistic->value,
                'created_by' => auth()->id(),
            ]);
        }

        return response()->json([
            'id'   => $customer->id,
            'text' => $customer->name . ($customer->company ? ' — ' . $customer->company : '') . ($customer->phone ? ' · ' . $customer->phone : ''),
            'name' => $customer->name,
        ], 201);
    }

    // ── AJAX: Quick-create Product ───────────────────────────────────────────

    public function quickCreateProduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(ProductCategory::class)],
            'sku'      => ['nullable', 'string', 'max:100'],
        ]);

        $product = Product::create([
            ...$validated,
            'is_active'  => true,
            'created_by' => auth()->id(),
        ]);

        $cat = ProductCategory::from($product->category instanceof ProductCategory ? $product->category->value : $product->category);

        return response()->json([
            'id'   => $product->id,
            'text' => $cat->icon() . ' ' . $product->name . ($product->sku ? ' (' . $product->sku . ')' : ''),
            'name' => $product->name,
        ], 201);
    }
}
