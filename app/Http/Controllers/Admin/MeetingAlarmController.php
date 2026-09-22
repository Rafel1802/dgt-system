<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeetingAlarm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class MeetingAlarmController extends Controller
{
    private function checkPermission(): void
    {
        $user = auth()->user();
        if (!$user || !$user->canManageClockSounds()) {
            abort(403, 'Unauthorized access. Only Super Admin, QC, and Supervisors can manage meeting alarms.');
        }
    }

    public function index(): View
    {
        $this->checkPermission();

        $meetingAlarms = MeetingAlarm::with('creator')
            ->orderBy('meeting_time', 'desc')
            ->paginate(15);

        $clockSounds = collect();
        if (is_dir(public_path('clocksound'))) {
            $clockFiles = File::files(public_path('clocksound'));
            $clockSounds = collect($clockFiles)->map(fn($file) => $file->getFilename())->sort(function ($a, $b) {
                if ($a === 'funny.wav') return -1;
                if ($b === 'funny.wav') return 1;
                if ($a === '02.wav') return -1;
                if ($b === '02.wav') return 1;
                return strnatcasecmp($a, $b);
            })->values();
        }

        return view('admin.meeting-alarms.index', compact('meetingAlarms', 'clockSounds'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        if (!$user || !$user->canManageClockSounds()) {
            return back()->with('error', 'Unauthorized action.');
        }

        $validated = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string', 'max:1000'],
            'meeting_time'  => ['required', 'date'],
            'meeting_link'  => ['nullable', 'url', 'max:500'],
            'sound'         => ['nullable', 'string', 'max:255'],
            'ring_duration' => ['nullable', 'integer', 'min:5', 'max:60'],
        ]);

        $validated['sound'] = $validated['sound'] ?: 'funny.wav';
        $validated['ring_duration'] = $validated['ring_duration'] ?? 10;
        $validated['created_by'] = auth()->id();
        $validated['is_active'] = $request->boolean('is_active', true);

        MeetingAlarm::create($validated);

        return redirect()->route('admin.meeting-alarms.index')->with('success', 'Meeting alarm scheduled successfully!');
    }

    public function update(Request $request, MeetingAlarm $meetingAlarm): RedirectResponse
    {
        $this->checkPermission();

        $validated = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string', 'max:1000'],
            'meeting_time'  => ['required', 'date'],
            'meeting_link'  => ['nullable', 'string', 'max:500'],
            'sound'         => ['nullable', 'string', 'max:255'],
            'ring_duration' => ['nullable', 'integer', 'min:3', 'max:60'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $meetingAlarm->update($validated);

        return redirect()->route('admin.meeting-alarms.index')->with('success', 'Meeting alarm updated successfully!');
    }

    public function destroy(MeetingAlarm $meetingAlarm): RedirectResponse
    {
        $this->checkPermission();

        $meetingAlarm->delete();

        return redirect()->route('admin.meeting-alarms.index')->with('success', 'Meeting alarm deleted successfully.');
    }

    public function toggleActive(MeetingAlarm $meetingAlarm): JsonResponse
    {
        $this->checkPermission();

        $meetingAlarm->is_active = !$meetingAlarm->is_active;
        $meetingAlarm->save();

        return response()->json([
            'success' => true,
            'is_active' => $meetingAlarm->is_active,
            'message' => $meetingAlarm->is_active ? 'Alarm activated' : 'Alarm paused',
        ]);
    }
}
