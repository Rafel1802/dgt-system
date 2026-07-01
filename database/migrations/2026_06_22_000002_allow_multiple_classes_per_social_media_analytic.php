<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_media_analytic_class')) {
            Schema::create('social_media_analytic_class', function (Blueprint $table) {
                $table->id();
                $table->foreignId('social_media_analytic_id')->constrained('social_media_analytics')->cascadeOnDelete();
                $table->foreignId('social_media_class_id')->constrained('social_media_classes')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['social_media_analytic_id', 'social_media_class_id'], 'sma_analytic_class_unique');
            });

            DB::table('social_media_analytics')
                ->select(['id', 'social_media_class_id', 'created_at', 'updated_at'])
                ->orderBy('id')
                ->each(function ($analytic) {
                    DB::table('social_media_analytic_class')->insert([
                        'social_media_analytic_id' => $analytic->id,
                        'social_media_class_id' => $analytic->social_media_class_id,
                        'created_at' => $analytic->created_at,
                        'updated_at' => $analytic->updated_at,
                    ]);
                });
        }

        if (Schema::hasColumn('social_media_analytics', 'social_media_class_id')) {
            Schema::table('social_media_analytics', function (Blueprint $table) {
                $table->dropForeign(['social_media_class_id']);
            });
            Schema::table('social_media_analytics', function (Blueprint $table) {
                $table->dropUnique('sma_class_dates_unique');
                $table->dropColumn('social_media_class_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('social_media_analytics', function (Blueprint $table) {
            $table->foreignId('social_media_class_id')->nullable()->after('id');
        });

        DB::table('social_media_analytics')->orderBy('id')->each(function ($analytic) {
            $classId = DB::table('social_media_analytic_class')
                ->where('social_media_analytic_id', $analytic->id)
                ->value('social_media_class_id');
            DB::table('social_media_analytics')->where('id', $analytic->id)->update([
                'social_media_class_id' => $classId,
            ]);
        });

        Schema::table('social_media_analytics', function (Blueprint $table) {
            $table->foreign('social_media_class_id')->references('id')->on('social_media_classes')->cascadeOnDelete();
            $table->unique(['social_media_class_id', 'date_from', 'date_to'], 'sma_class_dates_unique');
        });

        Schema::dropIfExists('social_media_analytic_class');
    }
};
