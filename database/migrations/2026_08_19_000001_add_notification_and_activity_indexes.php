<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function safeAddIndex(string $table, string|array $columns, string $indexName): void
    {
        try {
            Schema::table($table, function (Blueprint $t) use ($columns, $indexName) {
                $t->index($columns, $indexName);
            });
        } catch (\Throwable) {}
    }

    private function safeDropIndex(string $table, string $indexName): void
    {
        try {
            Schema::table($table, function (Blueprint $t) use ($indexName) {
                $t->dropIndex($indexName);
            });
        } catch (\Throwable) {}
    }

    public function up(): void
    {
        $this->safeAddIndex("notifications", ["notifiable_id", "read_at", "created_at"], "notifications_notifiable_read_created_idx");
        $this->safeAddIndex("activity_logs", ["user_id", "created_at"], "activity_logs_user_created_idx");
        $this->safeAddIndex("activity_logs", ["module", "created_at"], "activity_logs_module_created_idx");
    }

    public function down(): void
    {
        $this->safeDropIndex("notifications", "notifications_notifiable_read_created_idx");
        $this->safeDropIndex("activity_logs", "activity_logs_user_created_idx");
        $this->safeDropIndex("activity_logs", "activity_logs_module_created_idx");
    }
};
