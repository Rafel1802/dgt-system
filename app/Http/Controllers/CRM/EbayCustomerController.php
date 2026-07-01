<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\EbayCustomerRecord;
use App\Models\EbayStore;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EbayCustomerController extends Controller
{
    private function resolveTab(string $tab): string
    {
        $valid = array_keys(EbayCustomerRecord::tabs());
        abort_unless(in_array($tab, $valid), 404, 'Invalid tab.');
        return $tab;
    }

    public function index(Request $request, string $tab = 'urgent_client'): View
    {
        $tab = $this->resolveTab($tab);

        $query = EbayCustomerRecord::forTab($tab)->with(['store', 'creator']);

        if ($s = $request->get('search')) {
            $query->search($s);
        }
        if ($storeId = $request->get('store_id')) {
            $query->where('ebay_store_id', $storeId);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $records = $query->latest()->paginate(30)->withQueryString();
        $stores  = EbayStore::active()->orderBy('store_name')->get();
        $tabs    = EbayCustomerRecord::tabs();
        $columns = EbayCustomerRecord::columnsForTab($tab);

        return view('crm.ebay.customers.index', compact('records', 'stores', 'tabs', 'tab', 'columns'));
    }

    public function create(string $tab = 'urgent_client'): View
    {
        $tab = $this->resolveTab($tab);

        return view('crm.ebay.customers.create', [
            'tab'     => $tab,
            'tabs'    => EbayCustomerRecord::tabs(),
            'columns' => EbayCustomerRecord::columnsForTab($tab),
            'stores'  => EbayStore::active()->orderBy('store_name')->get(),
        ]);
    }

    public function store(Request $request, string $tab = 'urgent_client'): RedirectResponse
    {
        $tab = $this->resolveTab($tab);
        $cols = EbayCustomerRecord::columnsForTab($tab);

        $rules = [];
        if (in_array('username', $cols))            $rules['username']           = ['nullable', 'string', 'max:255'];
        if (in_array('buyer_name', $cols))          $rules['buyer_name']         = ['nullable', 'string', 'max:255'];
        if (in_array('informations', $cols))        $rules['informations']        = ['nullable', 'string'];
        if (in_array('email', $cols))               $rules['email']              = ['nullable', 'email', 'max:255'];
        if (in_array('ebay_store_id', $cols))       $rules['ebay_store_id']      = ['nullable', 'exists:ebay_stores,id'];
        if (in_array('order_id', $cols))            $rules['order_id']           = ['nullable', 'string', 'max:100'];
        if (in_array('summary', $cols))             $rules['summary']            = ['nullable', 'string'];
        if (in_array('sku_number', $cols))          $rules['sku_number']         = ['nullable', 'string', 'max:100'];
        if (in_array('date', $cols))                $rules['date']               = ['nullable', 'date'];
        if (in_array('order_date', $cols))          $rules['order_date']         = ['nullable', 'date'];
        if (in_array('attention_required', $cols))  $rules['attention_required'] = ['nullable', 'string'];
        if (in_array('required_attentions', $cols)) $rules['required_attentions']= ['nullable', 'string'];
        if (in_array('updates', $cols))             $rules['updates']            = ['nullable', 'string'];
        if (in_array('status', $cols))              $rules['status']             = ['nullable', 'string', 'max:50'];
        if (in_array('n', $cols))                   $rules['n']                  = ['nullable', 'integer'];

        $validated = $request->validate($rules);
        $validated['tab_type']   = $tab;
        $validated['created_by'] = auth()->id();

        // Auto-set sequence N for urgent_client
        if ($tab === EbayCustomerRecord::TAB_URGENT && empty($validated['n'])) {
            $validated['n'] = EbayCustomerRecord::forTab($tab)->max('n') + 1;
        }

        EbayCustomerRecord::create($validated);

        return redirect()->route('crm.ebay.customers.index', $tab)
            ->with('success', 'Record added.');
    }

    public function edit(string $tab, EbayCustomerRecord $record): View
    {
        $tab = $this->resolveTab($tab);

        return view('crm.ebay.customers.edit', [
            'record'  => $record,
            'tab'     => $tab,
            'tabs'    => EbayCustomerRecord::tabs(),
            'columns' => EbayCustomerRecord::columnsForTab($tab),
            'stores'  => EbayStore::active()->orderBy('store_name')->get(),
        ]);
    }

    public function update(Request $request, string $tab, EbayCustomerRecord $record): RedirectResponse
    {
        $tab = $this->resolveTab($tab);
        $cols = EbayCustomerRecord::columnsForTab($tab);

        $rules = [];
        if (in_array('username', $cols))            $rules['username']           = ['nullable', 'string', 'max:255'];
        if (in_array('buyer_name', $cols))          $rules['buyer_name']         = ['nullable', 'string', 'max:255'];
        if (in_array('informations', $cols))        $rules['informations']        = ['nullable', 'string'];
        if (in_array('email', $cols))               $rules['email']              = ['nullable', 'email', 'max:255'];
        if (in_array('ebay_store_id', $cols))       $rules['ebay_store_id']      = ['nullable', 'exists:ebay_stores,id'];
        if (in_array('order_id', $cols))            $rules['order_id']           = ['nullable', 'string', 'max:100'];
        if (in_array('summary', $cols))             $rules['summary']            = ['nullable', 'string'];
        if (in_array('sku_number', $cols))          $rules['sku_number']         = ['nullable', 'string', 'max:100'];
        if (in_array('date', $cols))                $rules['date']               = ['nullable', 'date'];
        if (in_array('order_date', $cols))          $rules['order_date']         = ['nullable', 'date'];
        if (in_array('attention_required', $cols))  $rules['attention_required'] = ['nullable', 'string'];
        if (in_array('required_attentions', $cols)) $rules['required_attentions']= ['nullable', 'string'];
        if (in_array('updates', $cols))             $rules['updates']            = ['nullable', 'string'];
        if (in_array('status', $cols))              $rules['status']             = ['nullable', 'string', 'max:50'];
        if (in_array('n', $cols))                   $rules['n']                  = ['nullable', 'integer'];

        $validated = $request->validate($rules);
        $validated['updated_by'] = auth()->id();

        $record->update($validated);

        return redirect()->route('crm.ebay.customers.index', $tab)
            ->with('success', 'Record updated.');
    }

    public function destroy(EbayCustomerRecord $record): RedirectResponse
    {
        $tab = $record->tab_type;
        $record->delete();
        return redirect()->route('crm.ebay.customers.index', $tab)
            ->with('success', 'Record deleted.');
    }
}
