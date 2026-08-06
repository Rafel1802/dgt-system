<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Board;
use App\Models\BoardList;

return new class extends Migration
{
    public function up(): void
    {
        // Find all boards that contain both "Meeting Schedule" and "Block/Waiting" lists
        $boards = Board::whereHas('lists', function ($query) {
            $query->where('name', 'Meeting Schedule');
        })->whereHas('lists', function ($query) {
            $query->where('name', 'Block/Waiting');
        })->get();

        foreach ($boards as $board) {
            // Check if "Urgent / Priority" already exists on this board
            $exists = $board->lists()->where('name', 'Urgent / Priority')->exists();
            if ($exists) {
                continue;
            }

            // Find the "Meeting Schedule" list position
            $meetingList = $board->lists()->where('name', 'Meeting Schedule')->first();
            if (!$meetingList) {
                continue;
            }

            $meetPos = $meetingList->position;

            // Shift positions of all lists after "Meeting Schedule"
            $board->lists()
                ->where('position', '>', $meetPos)
                ->increment('position');

            // Create the "Urgent / Priority" list in the middle
            $board->lists()->create([
                'name' => 'Urgent / Priority',
                'position' => $meetPos + 1,
            ]);
        }
    }

    public function down(): void
    {
        // Find all boards that contain both "Meeting Schedule" and "Block/Waiting" lists
        $boards = Board::whereHas('lists', function ($query) {
            $query->where('name', 'Meeting Schedule');
        })->whereHas('lists', function ($query) {
            $query->where('name', 'Block/Waiting');
        })->get();

        foreach ($boards as $board) {
            // Delete the "Urgent / Priority" list
            $urgentList = $board->lists()->where('name', 'Urgent / Priority')->first();
            if ($urgentList) {
                $urgentPos = $urgentList->position;
                $urgentList->delete();

                // Shift positions back
                $board->lists()
                    ->where('position', '>', $urgentPos)
                    ->decrement('position');
            }
        }
    }
};
