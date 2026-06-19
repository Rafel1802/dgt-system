<?php

namespace App\Http\Controllers\CRM;

use App\Enums\AuthorizationStatus;
use App\Enums\EbayLeadStatus;
use App\Http\Controllers\Controller;
use App\Models\EbayOffer;
use App\Models\EbayOrder;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EbayCrmController extends Controller
{
    public function index(Request $request): View
    {
        $query = EbayOffer::with(['handler', 'product', 'customer']);

        if ($s = $request->get('search')) $query->search($s);
        if ($st = $request->get('status')) $query->where('status', $st);
        if ($auth = $request->get('auth_status')) $query->where('authorization_status', $auth);

        $offers  = $query->latest()->paginate(20)->withQueryString();
        $statuses = EbayLeadStatus::cases();
        $authStatuses = AuthorizationStatus::cases();

        return view('crm.ebay.index', compact('offers', 'statuses', 'authStatuses'));
    }

    public function create(): View
    {
        return view('crm.ebay.create', [
            'products' => Product::active()->orderBy('name')->get(),
            'statuses' => EbayLeadStatus::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_name'    => ['nullable', 'string', 'max:255'],
            'client_email'   => ['nullable', 'email'],
            'ebay_username'  => ['nullable', 'string', 'max:255'],
            'ebay_message_id'=> ['nullable', 'string'],
            'ebay_item_id'   => ['nullable', 'string'],
            'product_id'     => ['nullable', 'exists:products,id'],
            'inquiry_notes'  => ['nullable', 'string'],
            'offer_details'  => ['nullable', 'string'],
            'offer_amount'   => ['nullable', 'numeric', 'min:0'],
            'received_at'    => ['required', 'date'],
        ]);

        $offer = EbayOffer::create([
            ...$validated,
            'handled_by'           => auth()->id(),
            'status'               => EbayLeadStatus::Inquiry->value,
            'authorization_status' => AuthorizationStatus::Pending->value,
        ]);

        return redirect()->route('crm.ebay.show', $offer)->with('success', 'eBay inquiry logged.');
    }

    public function show(EbayOffer $offer): View
    {
        $offer->load(['handler', 'product', 'customer', 'authorizer', 'order', 'attachments']);
        return view('crm.ebay.show', [
            'offer'       => $offer,
            'statuses'    => EbayLeadStatus::cases(),
            'authStatuses'=> AuthorizationStatus::cases(),
        ]);
    }

    public function edit(EbayOffer $offer): View
    {
        return view('crm.ebay.edit', [
            'offer'    => $offer,
            'statuses' => EbayLeadStatus::cases(),
            'products' => Product::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, EbayOffer $offer): RedirectResponse
    {
        $validated = $request->validate([
            'client_name'    => ['nullable', 'string'],
            'client_email'   => ['nullable', 'email'],
            'ebay_username'  => ['nullable', 'string'],
            'ebay_message_id'=> ['nullable', 'string'],
            'ebay_item_id'   => ['nullable', 'string'],
            'product_id'     => ['nullable', 'exists:products,id'],
            'offer_details'  => ['nullable', 'string'],
            'offer_amount'   => ['nullable', 'numeric'],
            'final_amount'   => ['nullable', 'numeric'],
            'payment_status' => ['nullable', 'string', Rule::in(['unpaid', 'paid', 'partial', 'refunded'])],
            'status'         => ['required', Rule::enum(EbayLeadStatus::class)],
        ]);

        $offer->update($validated);

        return redirect()->route('crm.ebay.show', $offer)->with('success', 'eBay offer updated.');
    }

    /** Authorize/reject an offer (AJAX) — for Hongling / Dennis */
    public function authorizeOffer(Request $request, EbayOffer $offer): JsonResponse
    {
        abort_unless(
            auth()->user()->hasAnyRole(['super-admin', 'admin-crm', 'boss']),
            403, 'Only Admins/Boss can authorize offers.'
        );

        $request->validate([
            'authorization_status' => ['required', Rule::enum(AuthorizationStatus::class)],
            'notes'                => ['nullable', 'string'],
            'final_amount'         => ['nullable', 'numeric'],
        ]);

        $authStatus = AuthorizationStatus::from($request->authorization_status);

        $offer->update([
            'authorization_status' => $authStatus->value,
            'authorized_by'        => auth()->id(),
            'authorized_at'        => now(),
            'authorization_notes'  => $request->notes,
            'final_amount'         => $request->final_amount ?? $offer->final_amount,
            'status'               => match($authStatus) {
                AuthorizationStatus::Approved    => EbayLeadStatus::Authorized->value,
                AuthorizationStatus::Rejected    => EbayLeadStatus::Rejected->value,
                AuthorizationStatus::Negotiation => EbayLeadStatus::WaitingAuthorization->value,
                default                          => $offer->status->value,
            },
        ]);

        return response()->json([
            'message' => 'Offer ' . $authStatus->label() . '.',
            'status'  => $offer->fresh()->status->label(),
        ]);
    }

    /** Convert authorized offer to a confirmed order */
    public function convertToOrder(Request $request, EbayOffer $offer): RedirectResponse
    {
        abort_if($offer->authorization_status !== AuthorizationStatus::Approved, 403, 'Offer must be authorized first.');

        $request->validate([
            'ebay_order_id' => ['nullable', 'string', 'unique:ebay_orders,ebay_order_id'],
            'sale_amount'   => ['required', 'numeric'],
            'payment_status'=> ['required', 'string', Rule::in(['unpaid', 'paid', 'partial', 'refunded'])],
        ]);

        $order = EbayOrder::create([
            'ebay_offer_id'  => $offer->id,
            'customer_id'    => $offer->customer_id,
            'product_id'     => $offer->product_id,
            'created_by'     => auth()->id(),
            'ebay_order_id'  => $request->ebay_order_id,
            'ebay_username'  => $offer->ebay_username,
            'sale_amount'    => $request->sale_amount,
            'payment_status' => $request->payment_status,
            'status'         => EbayLeadStatus::OrderConfirmed->value,
        ]);

        $offer->update(['status' => EbayLeadStatus::OrderConfirmed->value]);

        return redirect()->route('crm.ebay.show', $offer)
            ->with('success', 'Order #' . ($order->ebay_order_id ?? $order->id) . ' confirmed.');
    }

    public function destroy(EbayOffer $offer): RedirectResponse
    {
        $offer->delete();
        return redirect()->route('crm.ebay.index')->with('success', 'Offer deleted.');
    }
}
