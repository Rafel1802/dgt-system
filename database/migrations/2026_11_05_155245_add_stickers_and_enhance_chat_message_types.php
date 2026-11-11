<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Widen chat_messages.type to VARCHAR(30) to support 'sticker', 'gif', 'call_event', etc.
        if (Schema::hasTable('chat_messages')) {
            if (DB::getDriverName() === 'mysql') {
                try {
                    DB::statement("ALTER TABLE `chat_messages` MODIFY COLUMN `type` VARCHAR(30) NOT NULL DEFAULT 'text'");
                } catch (\Throwable $e) {}
            }
        }

        // 2. Create chat_stickers table
        if (!Schema::hasTable('chat_stickers')) {
            Schema::create('chat_stickers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
                $table->string('name', 100);
                $table->text('image_path');
                $table->string('category', 50)->default('custom'); // preset, custom, meme, reaction
                $table->boolean('is_public')->default(true);
                $table->timestamps();

                $table->index(['user_id', 'category']);
                $table->index('is_public');
            });
        }

        // 3. Add status_text (WhatsApp bio/status) to chat_user_settings
        if (Schema::hasTable('chat_user_settings')) {
            Schema::table('chat_user_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('chat_user_settings', 'status_text')) {
                    $table->string('status_text', 255)->default('Hey there! I am using DGT Chat.')->after('font_size');
                }
            });
        }

        // 4. Seed initial default stickers
        $this->seedInitialStickers();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_stickers');

        if (Schema::hasTable('chat_user_settings')) {
            Schema::table('chat_user_settings', function (Blueprint $table) {
                if (Schema::hasColumn('chat_user_settings', 'status_text')) {
                    $table->dropColumn('status_text');
                }
            });
        }
    }

    /**
     * Seed initial stickers for team use.
     */
    protected function seedInitialStickers(): void
    {
        $stickers = [
            ['name' => 'Thank... Kim', 'category' => 'team', 'image_path' => '/images/stickers/thank_kim.svg'],
            ['name' => 'Jo hah', 'category' => 'reaction', 'image_path' => '/images/stickers/jo_hah.svg'],
            ['name' => 'JoX Sunglasses', 'category' => 'reaction', 'image_path' => '/images/stickers/jox.svg'],
            ['name' => 'FU YAK', 'category' => 'meme', 'image_path' => '/images/stickers/fu_yak.svg'],
            ['name' => 'Hart mee???', 'category' => 'meme', 'image_path' => '/images/stickers/hart_mee.svg'],
            ['name' => 'Blocked Doggo', 'category' => 'meme', 'image_path' => '/images/stickers/blocked.svg'],
            ['name' => 'Plom? Cat', 'category' => 'meme', 'image_path' => '/images/stickers/plom_cat.svg'],
            ['name' => 'Nis Caption!', 'category' => 'team', 'image_path' => '/images/stickers/nis_caption.svg'],
            ['name' => 'Qi bu dui', 'category' => 'reaction', 'image_path' => '/images/stickers/qi_bu_dui.svg'],
            ['name' => 'Fire Explosion', 'category' => 'reaction', 'image_path' => '/images/stickers/fire_exp.svg'],
        ];

        foreach ($stickers as $item) {
            DB::table('chat_stickers')->insertOrIgnore([
                'user_id'    => null,
                'name'       => $item['name'],
                'image_path' => $item['image_path'],
                'category'   => $item['category'],
                'is_public'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
