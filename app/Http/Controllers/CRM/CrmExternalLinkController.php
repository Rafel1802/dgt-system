<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\CrmExternalLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrmExternalLinkController extends Controller
{
    public function index(): View
    {
        $links = CrmExternalLink::ordered()->get();
        return view('crm.links.index', compact('links'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'url'         => ['required', 'url'],
            'icon'        => ['nullable', 'string', 'max:50'],
            'icon_url'    => ['nullable', 'url'],
            'description' => ['nullable', 'string'],
            'sort_order'  => ['nullable', 'integer'],
            'is_active'   => ['boolean'],
        ]);

        $validated['is_active']  = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? (CrmExternalLink::max('sort_order') + 1);
        $validated['created_by'] = auth()->id();

        CrmExternalLink::create($validated);

        return back()->with('success', "Link '{$validated['name']}' added.");
    }

    public function update(Request $request, CrmExternalLink $link): RedirectResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'url'         => ['required', 'url'],
            'icon'        => ['nullable', 'string', 'max:50'],
            'icon_url'    => ['nullable', 'url'],
            'description' => ['nullable', 'string'],
            'sort_order'  => ['nullable', 'integer'],
            'is_active'   => ['boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $link->update($validated);

        return back()->with('success', "Link '{$link->name}' updated.");
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $tools = json_decode($request->input('custom_crm_tools', '[]'), true);
        $order = json_decode($request->input('crm_tools_order', '[]'), true);

        // Map tools by custom_id
        $toolsMap = collect($tools)->keyBy('custom_id');

        CrmExternalLink::query()->delete();

        foreach ($order as $index => $id) {
            $tool = $toolsMap->get($id);
            if ($tool && !empty($tool['label']) && !empty($tool['url'])) {
                CrmExternalLink::create([
                    'name'        => $tool['label'],
                    'url'         => $tool['url'],
                    'icon_url'    => $tool['icon_url'] ?? null,
                    'description' => $tool['description'] ?? null,
                    'sort_order'  => $index,
                    'is_active'   => true,
                    'created_by'  => auth()->id(),
                ]);
            }
        }

        return back()->with('success', 'CRM External Links updated successfully.');
    }

    public function destroy(CrmExternalLink $link): RedirectResponse
    {
        $this->authorizeAdmin();
        $link->delete();
        return back()->with('success', 'Link deleted.');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(
            auth()->user()->hasAnyRole(['super-admin', 'admin-crm', 'boss']),
            403,
            'Only CRM admin can manage external links.'
        );
    }
}
