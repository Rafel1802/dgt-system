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
        abort_unless(
            auth()->user()->hasAnyRole(['super-admin', 'admin-digital']),
            403,
            'Access restricted to admins.'
        );

        $maintenanceJson = Setting::where('key', 'maintenance_modules')->value('value') ?? '[]';
        $maintenanceModules = json_decode($maintenanceJson, true) ?: [];

        $templateStyle = Setting::where('key', 'maintenance_template_style')->value('value') ?? 'original';
        $siteName = Setting::where('key', 'maintenance_site_name')->value('value') ?? 'MyWebsite';
        $message = Setting::where('key', 'maintenance_message')->value('value') ?? "We're currently performing scheduled maintenance.";
        $time = Setting::where('key', 'maintenance_time')->value('value') ?? "We'll be back shortly!";
        $email = Setting::where('key', 'maintenance_email')->value('value') ?? 'support@example.com';

        return view('admin.maintenance.index', compact(
            'maintenanceModules',
            'templateStyle',
            'siteName',
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
        abort_unless(
            auth()->user()->hasAnyRole(['super-admin', 'admin-digital']),
            403,
            'Access restricted to admins.'
        );

        $validated = $request->validate([
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string'],
            'template_style' => ['required', 'in:original,custom'],
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

        Setting::updateOrCreate(['key' => 'maintenance_template_style'], ['value' => $validated['template_style']]);
        Setting::updateOrCreate(['key' => 'maintenance_site_name'], ['value' => $validated['site_name'] ?? '']);
        Setting::updateOrCreate(['key' => 'maintenance_message'], ['value' => $validated['message'] ?? '']);
        Setting::updateOrCreate(['key' => 'maintenance_time'], ['value' => $validated['time'] ?? '']);
        Setting::updateOrCreate(['key' => 'maintenance_email'], ['value' => $validated['email'] ?? '']);

        return redirect()->route('admin.maintenance.index')->with('success', 'Maintenance settings updated successfully.');
    }
}
