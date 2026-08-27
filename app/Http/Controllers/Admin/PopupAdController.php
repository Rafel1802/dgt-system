<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PopupAd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PopupAdController extends Controller
{
    public function index()
    {
        $ads = PopupAd::latest()->paginate(20);
        return view('admin.popup-ads.index', compact('ads'));
    }

    public function create()
    {
        return view('admin.popup-ads.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'nullable|image|max:2048',
            'body_text' => 'nullable|string',
            'button_text' => 'nullable|string|max:255',
            'button_link' => 'nullable|url',
            'notification_text' => 'nullable|string|max:255',
            'notification_icon' => 'nullable|string|max:255',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
            'interval_minutes' => 'required|integer|min:1',
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('popup_ads', 'public');
        }

        $validated['is_active'] = true;

        PopupAd::create($validated);

        return redirect()->route('admin.popup-ads.index')->with('success', 'Popup Ad created successfully.');
    }

    public function edit(PopupAd $popupAd)
    {
        return view('admin.popup-ads.edit', compact('popupAd'));
    }

    public function update(Request $request, PopupAd $popupAd)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'nullable|image|max:2048',
            'body_text' => 'nullable|string',
            'button_text' => 'nullable|string|max:255',
            'button_link' => 'nullable|url',
            'notification_text' => 'nullable|string|max:255',
            'notification_icon' => 'nullable|string|max:255',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
            'interval_minutes' => 'required|integer|min:1',
        ]);

        if ($request->hasFile('image')) {
            if ($popupAd->image_path) {
                Storage::disk('public')->delete($popupAd->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('popup_ads', 'public');
        }

        $popupAd->update($validated);

        return redirect()->route('admin.popup-ads.index')->with('success', 'Popup Ad updated successfully.');
    }

    public function destroy(PopupAd $popupAd)
    {
        if ($popupAd->image_path) {
            Storage::disk('public')->delete($popupAd->image_path);
        }
        $popupAd->delete();

        return redirect()->route('admin.popup-ads.index')->with('success', 'Popup Ad deleted successfully.');
    }

    public function toggleActive(PopupAd $popupAd)
    {
        $newStatus = !$popupAd->is_active;
        $popupAd->update(['is_active' => $newStatus]);
        
        // If turning it ON, reset tracking so it shows to everyone again
        if ($newStatus) {
            \Illuminate\Support\Facades\DB::table('popup_ad_user')->where('popup_ad_id', $popupAd->id)->delete();
            return redirect()->back()->with('success', 'Popup Ad activated and tracking reset. It will now show to all users again.');
        }

        return redirect()->back()->with('success', 'Popup Ad deactivated.');
    }

    public function show(PopupAd $popupAd)
    {
        $totalUsers = \App\Models\User::count();
        $seenUsersCount = \Illuminate\Support\Facades\DB::table('popup_ad_user')->where('popup_ad_id', $popupAd->id)->count();
        $clickedUsersCount = \Illuminate\Support\Facades\DB::table('popup_ad_user')->where('popup_ad_id', $popupAd->id)->where('is_clicked', true)->count();
        
        $interactions = \Illuminate\Support\Facades\DB::table('popup_ad_user')
            ->join('users', 'popup_ad_user.user_id', '=', 'users.id')
            ->where('popup_ad_id', $popupAd->id)
            ->select('users.name', 'users.email', 'popup_ad_user.last_shown_at', 'popup_ad_user.is_clicked')
            ->orderBy('popup_ad_user.last_shown_at', 'desc')
            ->get();

        return view('admin.popup-ads.show', compact('popupAd', 'totalUsers', 'seenUsersCount', 'clickedUsersCount', 'interactions'));
    }
}
