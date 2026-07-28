<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');                        // e.g. "eBay Store AU"
            $table->string('email_address');               // display email
            $table->string('provider')->default('imap');   // imap, gmail_oauth
            $table->string('imap_host')->nullable();
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->string('imap_encryption')->default('ssl'); // ssl, tls, none
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->default(465);
            $table->string('smtp_encryption')->default('ssl');
            $table->text('username')->nullable();
            $table->text('password')->nullable();          // encrypted at app level
            $table->text('oauth_token')->nullable();       // encrypted
            $table->text('oauth_refresh_token')->nullable(); // encrypted
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_account_id')->constrained()->cascadeOnDelete();
            $table->string('message_uid')->nullable();     // IMAP UID for dedup
            $table->string('message_id')->nullable();      // Message-ID header
            $table->string('from_name')->nullable();
            $table->string('from_email');
            $table->text('to_emails');                     // JSON array
            $table->text('cc_emails')->nullable();         // JSON array
            $table->string('subject');
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->string('folder')->default('INBOX');
            $table->boolean('is_read')->default(false);
            $table->boolean('is_starred')->default(false);
            $table->boolean('has_attachments')->default(false);
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['email_account_id', 'folder']);
            $table->index(['email_account_id', 'is_read']);
            $table->index('received_at');
            $table->unique(['email_account_id', 'message_uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_messages');
        Schema::dropIfExists('email_accounts');
    }
};
