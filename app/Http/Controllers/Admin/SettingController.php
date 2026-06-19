<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Display the system settings page (External Tool Menus).
     */
    public function index()
    {
        abort_unless(
            auth()->user()->hasAnyRole(['super-admin', 'admin-digital']),
            403,
            'Access restricted to admins.'
        );

        $tools = Setting::externalTools();
        $settings = Setting::all()->pluck('value', 'key')->all();

        return view('admin.settings.index', compact('settings', 'tools'));
    }

    /**
     * Store/update the system settings.
     */
    public function store(Request $request)
    {
        abort_unless(
            auth()->user()->hasAnyRole(['super-admin', 'admin-digital']),
            403,
            'Access restricted to admins.'
        );        $rules = collect(Setting::externalToolKeys())
            ->flatMap(fn (string $key) => [
                $key => ['nullable', 'url', 'max:2048'],
                $key . '_icon' => ['nullable', 'url', 'max:2048'],
                $key . '_label' => ['nullable', 'string', 'max:255']
            ])
            ->merge([
                'custom_ai_tools' => ['nullable', 'array'],
                'custom_ai_tools.*.label' => ['required_with:custom_ai_tools.*.url', 'nullable', 'string', 'max:255'],
                'custom_ai_tools.*.url' => ['required_with:custom_ai_tools.*.label', 'nullable', 'url', 'max:2048'],
                'custom_ai_tools.*.icon_url' => ['nullable', 'url', 'max:2048'],
            ])
            ->all();

        $validated = $request->validate($rules);

        foreach ($validated as $key => $value) {
            if ($key === 'custom_ai_tools' || str_contains($key, '.')) {
                continue;
            }

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value ? trim($value) : null]
            );
        }

        $customAiTools = collect($request->input('custom_ai_tools', []))
            ->filter(fn ($tool) => !empty($tool['label']) && !empty($tool['url']))
            ->values()
            ->toArray();

        Setting::updateOrCreate(
            ['key' => 'custom_ai_tools'],
            ['value' => json_encode($customAiTools)]
        );

        $route = $request->input('redirect_to') === 'dashboard'
            ? 'dashboard'
            : 'admin.settings.index';

        return redirect()->route($route)->with('success', 'External system links saved successfully.');
    }
}
