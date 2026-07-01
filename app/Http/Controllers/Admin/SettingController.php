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
                'custom_ai_tools'            => ['nullable', 'string'],
                'custom_board_tools'         => ['nullable', 'string'],
                'custom_generator_tools'     => ['nullable', 'string'],
                'custom_workspace_tools'     => ['nullable', 'string'],

                'board_tools_order'          => ['nullable', 'string'],
                'generator_tools_order'      => ['nullable', 'string'],
                'workspace_tools_order'      => ['nullable', 'string'],
                'ai_tools_order'             => ['nullable', 'string'],
            ])
            ->all();

        $validated = $request->validate($rules);

        $customKeys = ['custom_ai_tools', 'custom_board_tools', 'custom_generator_tools', 'custom_workspace_tools'];

        foreach ($validated as $key => $value) {
            if (in_array($key, $customKeys) || str_contains($key, '.')) {
                continue;
            }

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value ? trim($value) : null]
            );
        }

        foreach ($customKeys as $customKey) {
            $rawCustom = $request->input($customKey);
            if (is_string($rawCustom)) {
                $customTools = json_decode($rawCustom, true) ?: [];
            } else {
                $customTools = collect($rawCustom ?? [])
                    ->filter(fn ($tool) => !empty($tool['label']) && !empty($tool['url']))
                    ->values()
                    ->toArray();
            }

            // Strip internal _id and _static_key metadata before saving
            $customTools = collect($customTools)->map(function ($tool) {
                unset($tool['_id'], $tool['_static_key']);
                return $tool;
            })->filter(fn ($t) => !empty($t['label']) && !empty($t['url']))->values()->toArray();

            Setting::updateOrCreate(
                ['key' => $customKey],
                ['value' => json_encode($customTools)]
            );
        }

        // Save section display orders
        foreach (['board_tools_order', 'generator_tools_order', 'workspace_tools_order', 'ai_tools_order'] as $orderKey) {
            $orderValue = $request->input($orderKey);
            if ($orderValue !== null) {
                Setting::updateOrCreate(
                    ['key' => $orderKey],
                    ['value' => $orderValue]
                );
            }
        }

        $route = $request->input('redirect_to') === 'dashboard'
            ? 'dashboard'
            : 'admin.settings.index';

        return redirect()->route($route)->with('success', 'External system links saved successfully.');
    }
}
