<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time backfill for notifications created before the 'module' key
 * existed on notification payloads (see CardController, BoardActivityNotification,
 * TaskApproved/RejectedNotification, TechSupportCaseService). Those rows
 * were treated as "always visible" so historical notifications wouldn't
 * vanish — but in practice almost all of them are old digital/board
 * activity, so CRM staff kept seeing a large backlog of board notifications
 * in their bell even after the module-scoping fix shipped. Classified by
 * payload shape: board_id/card_id/actor_avatar only ever appear in
 * board/card-origin payloads; case_id/call_request_id only in CRM
 * (tech-support) payloads. Anything matching neither shape (e.g. a
 * separate email-sync notification feature) is left untouched — it was
 * never module-scoped in the first place, so this doesn't change what it
 * already shows.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('notifications')
            ->whereNull('data->module')
            ->where(function ($q) {
                $q->whereNotNull('data->board_id')
                  ->orWhereNotNull('data->card_id')
                  ->orWhereNotNull('data->actor_avatar');
            })
            ->update(['data' => DB::raw("JSON_SET(data, '$.module', 'digital')")]);

        DB::table('notifications')
            ->whereNull('data->module')
            ->where(function ($q) {
                $q->whereNotNull('data->case_id')
                  ->orWhereNotNull('data->call_request_id');
            })
            ->update(['data' => DB::raw("JSON_SET(data, '$.module', 'crm')")]);
    }

    public function down(): void
    {
        // Not meaningfully reversible — the pre-backfill state (no 'module'
        // key) is indistinguishable from "we already decided not to tag it".
    }
};
