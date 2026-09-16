<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MeetingAlarm;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class MeetingAlarmApiController extends Controller
{
    /**
     * Get active upcoming meeting alarms for real-time alerting.
     * Accessible by all authenticated users.
     */
    public function upcoming(): JsonResponse
    {
        // Asia/Phnom_Penh timezone
        $now = Carbon::now('Asia/Phnom_Penh');
        $from = $now->copy()->subMinutes(15);
        $to = $now->copy()->addHours(24);

        $alarms = MeetingAlarm::where('is_active', true)
            ->where('meeting_time', '>=', $from)
            ->where('meeting_time', '<=', $to)
            ->orderBy('meeting_time', 'asc')
            ->get()
            ->map(function ($alarm) {
                $ppTime = $alarm->meeting_time->timezone('Asia/Phnom_Penh');
                return [
                    'id'                     => $alarm->id,
                    'title'                  => $alarm->title,
                    'description'            => $alarm->description,
                    'meeting_time'           => $ppTime->format('Y-m-d H:i:s'),
                    'meeting_time_formatted' => $ppTime->format('h:i A'),
                    'meeting_link'           => $alarm->meeting_link,
                    'sound'                  => $alarm->sound ?: 'melodic-chime.wav',
                    'sound_url'              => $alarm->sound_url,
                    'ring_duration'          => $alarm->ring_duration ?? 10,
                ];
            });

        return response()->json([
            'success' => true,
            'alarms'  => $alarms,
        ]);
    }
}
