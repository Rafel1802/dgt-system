<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Board;
use App\Models\BoardList;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('boards') || !Schema::hasTable('board_lists')) {
            return;
        }

        // Find all boards that contain both "Meeting Schedule" and "Block/Waiting" lists
        $boardIds = DB::table('board_lists as l1')
            ->join('board_lists as l2', 'l1.board_id', '=', 'l2.board_id')
            ->where('l1.name', 'Meeting Schedule')
            ->where('l2.name', 'Block/Waiting')
            ->pluck('l1.board_id')
            ->unique();

        foreach ($boardIds as $boardId) {
            // Check if "Urgent / Priority" already exists on this board
            $exists = DB::table('board_lists')
                ->where('board_id', $boardId)
                ->where('name', 'Urgent / Priority')
                ->exists();
            if ($exists) {
                continue;
            }

            // Find the "Meeting Schedule" list position
            $meetingList = DB::table('board_lists')
                ->where('board_id', $boardId)
                ->where('name', 'Meeting Schedule')
                ->first();
            if (!$meetingList) {
                continue;
            }

            $meetPos = $meetingList->position;

            // Shift positions of all lists after "Meeting Schedule"
            DB::table('board_lists')
                ->where('board_id', $boardId)
                ->where('position', '>', $meetPos)
                ->increment('position');

            // Create the "Urgent / Priority" list in the middle
            $hasDeletedAt = Schema::hasColumn('board_lists', 'deleted_at');
            $now = now();
            $insertData = [
                'board_id' => $boardId,
                'name' => 'Urgent / Priority',
                'position' => $meetPos + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($hasDeletedAt) {
                $insertData['deleted_at'] = null;
            }
            DB::table('board_lists')->insert($insertData);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('boards') || !Schema::hasTable('board_lists')) {
            return;
        }

        $boardIds = DB::table('board_lists as l1')
            ->join('board_lists as l2', 'l1.board_id', '=', 'l2.board_id')
            ->where('l1.name', 'Meeting Schedule')
            ->where('l2.name', 'Block/Waiting')
            ->pluck('l1.board_id')
            ->unique();

        foreach ($boardIds as $boardId) {
            // Delete the "Urgent / Priority" list
            $urgentList = DB::table('board_lists')
                ->where('board_id', $boardId)
                ->where('name', 'Urgent / Priority')
                ->first();
            if ($urgentList) {
                $urgentPos = $urgentList->position;
                DB::table('board_lists')->where('id', $urgentList->id)->delete();

                // Shift positions back
                DB::table('board_lists')
                    ->where('board_id', $boardId)
                    ->where('position', '>', $urgentPos)
                    ->decrement('position');
            }
        }
    }
};
