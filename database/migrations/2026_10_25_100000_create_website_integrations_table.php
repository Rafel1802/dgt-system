<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('website_integrations')) {
            Schema::create('website_integrations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained('websites')->cascadeOnDelete();
                $table->string('connector_type', 50)->default('none'); // wordpress_plugin, wordpress_rest, custom_laravel, custom_php, custom_api, none
                $table->string('api_key', 80)->unique();
                $table->text('api_secret'); // encrypted at rest
                $table->text('webhook_secret')->nullable(); // encrypted at rest
                $table->string('endpoint_url', 1000)->nullable();
                $table->boolean('is_verified')->default(false);
                $table->string('verification_token', 64)->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->string('verification_method', 50)->nullable(); // direct_handshake, meta_tag, file_upload, dns
                $table->string('cms_status', 30)->default('disconnected'); // connected, disconnected, error, unverified
                $table->string('analytics_status', 30)->default('not_configured'); // connected, disconnected, error, not_configured
                $table->string('ga4_property_id', 50)->nullable();
                $table->text('ga4_credentials_json')->nullable(); // encrypted at rest
                $table->json('tech_stack')->nullable(); // cms, cms_version, php, framework, server, etc.
                $table->string('hostinger_account', 100)->nullable(); // e.g. "Hostinger Account 1", "Hostinger Account 2", "Hostinger Account 3"
                $table->timestamp('last_sync_at')->nullable();
                $table->string('last_sync_status', 30)->nullable(); // success, failed, partial
                $table->text('last_sync_error')->nullable();
                $table->timestamp('last_ping_at')->nullable();
                $table->unsignedInteger('sync_frequency_minutes')->default(60);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique('website_id');
                $table->index(['cms_status', 'is_active']);
                $table->index('hostinger_account');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_integrations');
    }
};
