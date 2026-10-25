<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    /**
     * Display the maintenance system page.
     */
    public function index()
    {
        $user = auth()->user();
        abort_unless(
            $user && ($user->isDara() || $user->hasAnyRole(['super-admin', 'admin-digital'])),
            403,
            'Access restricted to admins.'
        );

        $maintenanceJson = Setting::where('key', 'maintenance_modules')->value('value') ?? '[]';
        $maintenanceModules = json_decode($maintenanceJson, true) ?: [];

        $templateStyle = Setting::where('key', 'maintenance_template_style')->value('value') ?? 'custom';
        $siteName = Setting::where('key', 'maintenance_site_name')->value('value') ?: 'KiuQ.com';
        if ($siteName === 'MyWebsite') {
            $siteName = 'KiuQ.com';
        }
        $titlePreset = Setting::where('key', 'maintenance_title_preset')->value('value') ?: 'Under Maintenance';
        $titleCustom = Setting::where('key', 'maintenance_title_custom')->value('value') ?: '';
        $title = Setting::where('key', 'maintenance_title')->value('value')
            ?: ($titlePreset === 'other' && !empty($titleCustom) ? $titleCustom : $titlePreset);

        $message = Setting::where('key', 'maintenance_message')->value('value') ?? "We're currently performing scheduled maintenance.";
        $time = Setting::where('key', 'maintenance_time')->value('value') ?? "We'll be back shortly!";
        $email = Setting::where('key', 'maintenance_email')->value('value') ?? 'digital@typhonmachinery.com';

        return view('admin.maintenance.index', compact(
            'maintenanceModules',
            'templateStyle',
            'siteName',
            'titlePreset',
            'titleCustom',
            'title',
            'message',
            'time',
            'email'
        ));
    }

    /**
     * Store/update the maintenance settings.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        abort_unless(
            $user && ($user->isDara() || $user->hasAnyRole(['super-admin', 'admin-digital'])),
            403,
            'Access restricted to admins.'
        );

        $validated = $request->validate([
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string'],
            'template_style' => ['required', 'in:original,custom'],
            'title_preset' => ['nullable', 'string', 'max:255'],
            'title_custom' => ['nullable', 'string', 'max:255'],
            'site_name' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'time' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $modules = $validated['modules'] ?? [];

        Setting::updateOrCreate(
            ['key' => 'maintenance_modules'],
            ['value' => json_encode(array_keys($modules))]
        );

        $titlePreset = $validated['title_preset'] ?? 'Under Maintenance';
        $titleCustom = trim($validated['title_custom'] ?? '');
        $effectiveTitle = ($titlePreset === 'other') ? ($titleCustom ?: 'Under Maintenance') : $titlePreset;

        $siteName = trim($validated['site_name'] ?? '');
        if (empty($siteName) || $siteName === 'MyWebsite') {
            $siteName = 'KiuQ.com';
        }

        Setting::updateOrCreate(['key' => 'maintenance_template_style'], ['value' => $validated['template_style']]);
        Setting::updateOrCreate(['key' => 'maintenance_title_preset'], ['value' => $titlePreset]);
        Setting::updateOrCreate(['key' => 'maintenance_title_custom'], ['value' => $titleCustom]);
        Setting::updateOrCreate(['key' => 'maintenance_title'], ['value' => $effectiveTitle]);
        Setting::updateOrCreate(['key' => 'maintenance_site_name'], ['value' => $siteName]);
        Setting::updateOrCreate(['key' => 'maintenance_message'], ['value' => $validated['message'] ?? '']);
        Setting::updateOrCreate(['key' => 'maintenance_time'], ['value' => $validated['time'] ?? '']);
        Setting::updateOrCreate(['key' => 'maintenance_email'], ['value' => $validated['email'] ?? '']);

        return redirect()->route('admin.maintenance.index')->with('success', 'Maintenance settings updated successfully.');
    }
}
