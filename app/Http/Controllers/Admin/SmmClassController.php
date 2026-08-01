<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaClass;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class SmmClassController extends Controller
{
    /**
     * Display a listing of the SMM classes.
     */
    public function index(): View
    {
        $classes = SocialMediaClass::orderBy('name')->get(['id', 'name', 'color', 'external_link']);

        return view('admin.smm-classes.index', compact('classes'));
    }

    /**
     * Store a newly created class in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:100', 'unique:social_media_classes,name'],
            'color' => ['required', 'string', 'max:7', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'external_link' => ['nullable', 'url', 'max:255'],
        ]);

        SocialMediaClass::create([
            'name'       => $validated['name'],
            'color'      => $validated['color'],
            'external_link' => $validated['external_link'] ?? null,
            'status'     => 'active',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('admin.smm-classes.index')
            ->with('success', "Class Label \"{$validated['name']}\" created successfully.");
    }

    /**
     * Update the specified class in storage.
     */
    public function update(Request $request, SocialMediaClass $smm_class): RedirectResponse
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:100', 'unique:social_media_classes,name,' . $smm_class->id],
            'color' => ['required', 'string', 'max:7', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'external_link' => ['nullable', 'url', 'max:255'],
        ]);

        $smm_class->update($validated);

        return redirect()->route('admin.smm-classes.index')
            ->with('success', "Class Label updated successfully.");
    }

    /**
     * Remove the specified class from storage.
     */
    public function destroy(SocialMediaClass $smm_class): RedirectResponse
    {
        $smm_class->delete();

        return redirect()->route('admin.smm-classes.index')
            ->with('success', 'Class Label deleted successfully.');
    }
}
