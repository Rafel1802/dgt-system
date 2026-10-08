<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Label;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ensure the 5 canonical global labels exist
        $canonicalLabels = [
            'Graphic' => '#f43f5e',
            'Video'   => '#f43f5e',
            'SMM'     => '#50C878',
            'Listing' => '#f59e0b',
            'Content' => '#0ea5e9',
        ];

        $resolved = [];
        foreach ($canonicalLabels as $name => $color) {
            $label = Label::firstOrCreate(
                ['name' => $name, 'workspace_id' => null, 'board_id' => null],
                ['color' => $color]
            );
            $label->update(['color' => $color]);
            $resolved[$name] = $label;
        }

        // Merge any "Content Writing" or "Content Writing Team" into "Content"
        $cwLabels = Label::whereIn('name', ['Content Writing', 'Content Writing Team'])->get();
        foreach ($cwLabels as $cw) {
            if ($cw->id !== $resolved['Content']->id) {
                DB::statement("
                    INSERT IGNORE INTO card_labels (card_id, label_id, created_at, updated_at)
                    SELECT card_id, ?, NOW(), NOW()
                    FROM card_labels
                    WHERE label_id = ?
                ", [$resolved['Content']->id, $cw->id]);
                DB::table('card_labels')->where('label_id', $cw->id)->delete();
                $cw->delete();
            }
        }

        // 2. Migrate cards attached to 'Graphic Team' to 'Graphic'
        $graphicTeam = Label::where('name', 'Graphic Team')->first();
        if ($graphicTeam && $graphicTeam->id !== $resolved['Graphic']->id) {
            DB::statement("
                INSERT IGNORE INTO card_labels (card_id, label_id, created_at, updated_at)
                SELECT card_id, ?, NOW(), NOW()
                FROM card_labels
                WHERE label_id = ?
            ", [$resolved['Graphic']->id, $graphicTeam->id]);
            DB::table('card_labels')->where('label_id', $graphicTeam->id)->delete();
            $graphicTeam->delete();
        }

        // 3. Migrate cards attached to 'Video Team' to 'Video'
        $videoTeam = Label::where('name', 'Video Team')->first();
        if ($videoTeam && $videoTeam->id !== $resolved['Video']->id) {
            DB::statement("
                INSERT IGNORE INTO card_labels (card_id, label_id, created_at, updated_at)
                SELECT card_id, ?, NOW(), NOW()
                FROM card_labels
                WHERE label_id = ?
            ", [$resolved['Video']->id, $videoTeam->id]);
            DB::table('card_labels')->where('label_id', $videoTeam->id)->delete();
            $videoTeam->delete();
        }

        // 4. Remove 'QC Team'
        $qcTeam = Label::where('name', 'QC Team')->first();
        if ($qcTeam) {
            DB::table('card_labels')->where('label_id', $qcTeam->id)->delete();
            $qcTeam->delete();
        }

        // Set positions for clean ordering (1 to 5)
        $order = ['Graphic', 'Video', 'SMM', 'Listing', 'Content'];
        foreach ($order as $idx => $name) {
            Label::where('name', $name)->whereNull('workspace_id')->whereNull('board_id')->update(['position' => $idx + 1]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
