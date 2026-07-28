<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $classes = DB::table('social_media_classes')->get();
        $now = Carbon::now();

        foreach ($classes as $class) {
            // Check if Tumblr already exists for this class
            $exists = DB::table('social_media_items')
                ->where('social_media_class_id', $class->id)
                ->where('name', 'Tumblr')
                ->exists();

            if (!$exists) {
                // Get the max sort_order for this class
                $maxOrder = DB::table('social_media_items')
                    ->where('social_media_class_id', $class->id)
                    ->max('sort_order') ?? 0;

                DB::table('social_media_items')->insert([
                    'social_media_class_id' => $class->id,
                    'name' => 'Tumblr',
                    'icon' => 'https://cdn-icons-png.flaticon.com/512/1409/1409942.png',
                    'status' => 'active',
                    'sort_order' => $maxOrder + 10,
                    'created_by' => $class->created_by,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('social_media_items')
            ->where('name', 'Tumblr')
            ->where('icon', 'https://cdn-icons-png.flaticon.com/512/1409/1409942.png')
            ->delete();
    }
};
