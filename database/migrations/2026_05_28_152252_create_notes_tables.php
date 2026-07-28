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
        Schema::create('note_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('team')->nullable()->index(); // digital, crm, logistic, admin
            $table->enum('type', ['private', 'team'])->default('private')->index();
            $table->string('name');
            $table->string('color')->nullable();
            $table->string('icon')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Owner/creator
            $table->foreignId('folder_id')->nullable()->constrained('note_folders')->onDelete('set null');
            $table->enum('type', ['private', 'team'])->default('private')->index();
            $table->string('team')->nullable()->index();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->longText('plain_text')->nullable(); // For faster searching
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('position')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('note_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type')->default('file'); // file, image, link
            $table->string('title');
            $table->string('url')->nullable();
            $table->string('path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });

        Schema::create('note_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('note_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained('note_checklists')->onDelete('cascade');
            $table->string('content');
            $table->boolean('is_completed')->default(false);
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        Schema::create('note_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('action'); // created, updated, archived, etc.
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('note_activities');
        Schema::dropIfExists('note_checklist_items');
        Schema::dropIfExists('note_checklists');
        Schema::dropIfExists('note_attachments');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('note_folders');
    }
};
