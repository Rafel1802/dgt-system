<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\SocialMediaClass;
use App\Models\Card;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Locate canonical classes
        $mbCanonical = SocialMediaClass::where('name', 'MachineryBargains')->first();
        $ssCanonical = SocialMediaClass::where('name', 'SkidSteers')->first();

        // 2. Locate duplicate classes
        $mbDuplicates = SocialMediaClass::whereIn('name', ['Machinery.Bargains', 'Machinery Bargains'])->get();
        $ssDuplicates = SocialMediaClass::whereIn('name', ['SkidSteer', 'Skid Steer'])->get();

        // 3. Merge duplicate Machinery.Bargains
        if ($mbCanonical) {
            foreach ($mbDuplicates as $dup) {
                $this->mergeClasses($dup->id, $mbCanonical->id);
                $dup->delete();
            }
        } elseif ($mbDuplicates->isNotEmpty()) {
            // If canonical was missing for some reason, rename the first duplicate
            $first = $mbDuplicates->first();
            $first->update(['name' => 'MachineryBargains']);
            foreach ($mbDuplicates->skip(1) as $dup) {
                $this->mergeClasses($dup->id, $first->id);
                $dup->delete();
            }
        }

        // 4. Merge duplicate SkidSteer
        if ($ssCanonical) {
            foreach ($ssDuplicates as $dup) {
                $this->mergeClasses($dup->id, $ssCanonical->id);
                $dup->delete();
            }
        } elseif ($ssDuplicates->isNotEmpty()) {
            $first = $ssDuplicates->first();
            $first->update(['name' => 'SkidSteers']);
            foreach ($ssDuplicates->skip(1) as $dup) {
                $this->mergeClasses($dup->id, $first->id);
                $dup->delete();
            }
        }

        // 5. Update Card smm_class_label and smm_cluster_label values directly
        Card::whereIn('smm_class_label', ['Machinery.Bargains', 'Machinery Bargains', 'machinery.bargains'])
            ->update(['smm_class_label' => 'MachineryBargains']);

        Card::whereIn('smm_class_label', ['SkidSteer', 'Skid Steer', 'skidsteer'])
            ->update(['smm_class_label' => 'SkidSteers']);

        Card::whereIn('smm_cluster_label', ['Machinery.Bargains', 'Machinery Bargains', 'machinery.bargains'])
            ->update(['smm_cluster_label' => 'MachineryBargains']);

        Card::whereIn('smm_cluster_label', ['SkidSteer', 'Skid Steer', 'skidsteer'])
            ->update(['smm_cluster_label' => 'SkidSteers']);
    }

    private function mergeClasses(int $sourceId, int $targetId): void
    {
        if ($sourceId === $targetId) {
            return;
        }

        // Re-link or remove duplicates in social_media_analytic_class
        if (Schema::hasTable('social_media_analytic_class')) {
            $records = DB::table('social_media_analytic_class')
                ->where('social_media_class_id', $sourceId)
                ->get();

            foreach ($records as $rec) {
                $exists = DB::table('social_media_analytic_class')
                    ->where('social_media_analytic_id', $rec->social_media_analytic_id)
                    ->where('social_media_class_id', $targetId)
                    ->exists();

                if ($exists) {
                    DB::table('social_media_analytic_class')
                        ->where('id', $rec->id)
                        ->delete();
                } else {
                    DB::table('social_media_analytic_class')
                        ->where('id', $rec->id)
                        ->update(['social_media_class_id' => $targetId]);
                }
            }
        }

        // Re-link or remove duplicates in social_media_class_user
        if (Schema::hasTable('social_media_class_user')) {
            $records = DB::table('social_media_class_user')
                ->where('social_media_class_id', $sourceId)
                ->get();

            foreach ($records as $rec) {
                $exists = DB::table('social_media_class_user')
                    ->where('user_id', $rec->user_id)
                    ->where('social_media_class_id', $targetId)
                    ->exists();

                if ($exists) {
                    DB::table('social_media_class_user')
                        ->where('id', $rec->id)
                        ->delete();
                } else {
                    DB::table('social_media_class_user')
                        ->where('id', $rec->id)
                        ->update(['social_media_class_id' => $targetId]);
                }
            }
        }

        // Re-link social_media_posts
        if (Schema::hasTable('social_media_posts')) {
            DB::table('social_media_posts')
                ->where('social_media_class_id', $sourceId)
                ->update(['social_media_class_id' => $targetId]);
        }

        // Re-link social_media_items
        if (Schema::hasTable('social_media_items')) {
            $items = DB::table('social_media_items')
                ->where('social_media_class_id', $sourceId)
                ->get();

            foreach ($items as $item) {
                $exists = DB::table('social_media_items')
                    ->where('social_media_class_id', $targetId)
                    ->where('name', $item->name)
                    ->exists();

                if ($exists) {
                    DB::table('social_media_items')
                        ->where('id', $item->id)
                        ->delete();
                } else {
                    DB::table('social_media_items')
                        ->where('id', $item->id)
                        ->update(['social_media_class_id' => $targetId]);
                }
            }
        }
    }

    public function down(): void
    {
        // No reverse needed
    }
};
