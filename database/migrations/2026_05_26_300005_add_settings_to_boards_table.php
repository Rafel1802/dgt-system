<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            if (! Schema::hasColumn('boards', 'member_permissions')) {
                $table->string('member_permissions')->default('members')->after('visibility');
            }

            if (! Schema::hasColumn('boards', 'card_covers_enabled')) {
                $table->boolean('card_covers_enabled')->default(true)->after('member_permissions');
            }

            if (! Schema::hasColumn('boards', 'notifications_enabled')) {
                $table->boolean('notifications_enabled')->default(true)->after('card_covers_enabled');
            }

            if (! Schema::hasColumn('boards', 'browser_notifications_enabled')) {
                $table->boolean('browser_notifications_enabled')->default(false)->after('notifications_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            foreach ([
                'browser_notifications_enabled',
                'notifications_enabled',
                'card_covers_enabled',
                'member_permissions',
            ] as $column) {
                if (Schema::hasColumn('boards', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
