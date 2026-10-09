<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardFile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CardFolderUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        Storage::fake('public');
    }

    private function cardFixture(): array
    {
        $user = User::factory()->create(['is_active' => true]);

        $workspace = Workspace::create([
            'name' => 'Design Workspace',
            'owner_id' => $user->id,
            'is_active' => true,
        ]);

        $board = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'Design Production',
            'created_by' => $user->id,
            'notifications_enabled' => true,
        ]);

        $list = BoardList::create([
            'board_id' => $board->id,
            'name' => 'In Progress',
            'position' => 0,
        ]);

        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Poster Campaign 2026',
            'status' => 'todo',
            'priority' => 'medium',
            'position' => 0,
            'created_by' => $user->id,
        ]);
        $card->assignees()->attach($user->id);

        return [$user, $board, $list, $card];
    }

    public function test_can_upload_multiple_files_with_folder_name(): void
    {
        [$user, $board, $list, $card] = $this->cardFixture();

        $file1 = UploadedFile::fake()->image('banner_1.jpg', 600, 400);
        $file2 = UploadedFile::fake()->image('banner_2.png', 800, 600);

        $response = $this->actingAs($user)->postJson("/boards/cards/{$card->id}/files", [
            'files' => [$file1, $file2],
            'folder_name' => 'Campaign Banners',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'files' => [
                '*' => ['id', 'original_name', 'folder_name', 'display_name', 'url', 'is_image'],
            ],
        ]);

        $this->assertCount(2, $card->fresh()->files);
        $uploaded = $card->fresh()->files;

        foreach ($uploaded as $f) {
            $this->assertSame('Campaign Banners', $f->folder_name);
            $this->assertTrue($f->is_image);
            $this->assertStringNotContainsString('Campaign Banners/', $f->display_name);
        }
    }

    public function test_can_upload_folder_with_relative_paths(): void
    {
        [$user, $board, $list, $card] = $this->cardFixture();

        $file1 = UploadedFile::fake()->image('photo1.jpg');
        $file2 = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

        $response = $this->actingAs($user)->postJson("/boards/cards/{$card->id}/files", [
            'files' => [$file1, $file2],
            'relative_paths' => [
                'EventPhotos/photo1.jpg',
                'EventPhotos/notes.txt',
            ],
        ]);

        $response->assertStatus(201);
        $uploaded = $card->fresh()->files;
        $this->assertCount(2, $uploaded);

        foreach ($uploaded as $f) {
            $this->assertSame('EventPhotos', $f->folder_name);
        }
    }

    public function test_card_file_accessors_extract_folder_and_display_name_from_prefixed_original_name(): void
    {
        [$user, $board, $list, $card] = $this->cardFixture();

        $file = CardFile::create([
            'card_id' => $card->id,
            'uploaded_by' => $user->id,
            'original_name' => 'Project Assets/logo-white.png',
            'stored_name' => 'cards/1/fake_stored.png',
            'disk' => 'public',
            'path' => 'cards/1/fake_stored.png',
            'mime_type' => 'image/png',
            'size' => 12345,
        ]);

        $this->assertSame('Project Assets', $file->folder_name);
        $this->assertSame('logo-white.png', $file->display_name);
    }

    public function test_can_download_folder_as_zip(): void
    {
        [$user, $board, $list, $card] = $this->cardFixture();

        $path1 = 'cards/1/file1.png';
        $path2 = 'cards/1/file2.png';
        Storage::disk('public')->put($path1, 'image-bytes-1');
        Storage::disk('public')->put($path2, 'image-bytes-2');

        CardFile::create([
            'card_id' => $card->id,
            'uploaded_by' => $user->id,
            'original_name' => 'Mockups/file1.png',
            'stored_name' => $path1,
            'disk' => 'public',
            'path' => $path1,
            'mime_type' => 'image/png',
            'size' => 100,
        ]);

        CardFile::create([
            'card_id' => $card->id,
            'uploaded_by' => $user->id,
            'original_name' => 'Mockups/file2.png',
            'stored_name' => $path2,
            'disk' => 'public',
            'path' => $path2,
            'mime_type' => 'image/png',
            'size' => 100,
        ]);

        $response = $this->actingAs($user)->get("/boards/cards/{$card->id}/folders/Mockups/download");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/zip');
        $this->assertStringContainsString('Mockups.zip', $response->headers->get('content-disposition'));
    }

    public function test_can_delete_folder_and_all_its_files(): void
    {
        [$user, $board, $list, $card] = $this->cardFixture();

        $path = 'cards/1/del.png';
        Storage::disk('public')->put($path, 'image-bytes');

        CardFile::create([
            'card_id' => $card->id,
            'uploaded_by' => $user->id,
            'original_name' => 'TrashFolder/del.png',
            'stored_name' => $path,
            'disk' => 'public',
            'path' => $path,
            'mime_type' => 'image/png',
            'size' => 100,
        ]);

        // Standalone file that should NOT be deleted
        $standalone = CardFile::create([
            'card_id' => $card->id,
            'uploaded_by' => $user->id,
            'original_name' => 'keep_me.png',
            'stored_name' => 'cards/1/keep.png',
            'disk' => 'public',
            'path' => 'cards/1/keep.png',
            'mime_type' => 'image/png',
            'size' => 100,
        ]);

        $response = $this->actingAs($user)->deleteJson("/boards/cards/{$card->id}/folders/TrashFolder");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $remainingFiles = $card->fresh()->files;
        $this->assertCount(1, $remainingFiles);
        $this->assertSame($standalone->id, $remainingFiles->first()->id);
    }

    public function test_can_assign_existing_files_to_folder(): void
    {
        [$user, $board, $list, $card] = $this->cardFixture();

        $file1 = CardFile::create([
            'card_id' => $card->id,
            'uploaded_by' => $user->id,
            'original_name' => '7.jpg',
            'stored_name' => 'cards/1/fake_7.jpg',
            'disk' => 'public',
            'path' => 'cards/1/fake_7.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1234,
        ]);

        $file2 = CardFile::create([
            'card_id' => $card->id,
            'uploaded_by' => $user->id,
            'original_name' => '9.jpg',
            'stored_name' => 'cards/1/fake_9.jpg',
            'disk' => 'public',
            'path' => 'cards/1/fake_9.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1234,
        ]);

        $response = $this->actingAs($user)->postJson("/boards/cards/{$card->id}/folders/assign", [
            'folder_name' => 'My Photos',
            'file_ids' => [$file1->id, $file2->id],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'folder_name' => 'My Photos', 'count' => 2]);

        $file1->refresh();
        $file2->refresh();

        $this->assertSame('My Photos', $file1->folder_name);
        $this->assertSame('7.jpg', $file1->display_name);
        $this->assertSame('My Photos', $file2->folder_name);
        $this->assertSame('9.jpg', $file2->display_name);

        $response->assertJsonPath('files.0.size', 1234);
        $response->assertJsonPath('files.1.size', 1234);
    }

    public function test_assign_folder_syncs_to_twin_card_in_same_sync_group(): void
    {
        [$user, $board, $list, $card1] = $this->cardFixture();
        $syncGroupId = (string) \Illuminate\Support\Str::uuid();
        $card1->update(['sync_group_id' => $syncGroupId]);

        $card2 = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Twin Poster Campaign',
            'status' => 'todo',
            'sync_group_id' => $syncGroupId,
            'created_by' => $user->id,
        ]);
        $card2->assignees()->attach($user->id);

        $fileSyncId1 = (string) \Illuminate\Support\Str::uuid();
        $fileSyncId2 = (string) \Illuminate\Support\Str::uuid();

        $c1File1 = CardFile::create([
            'card_id' => $card1->id,
            'uploaded_by' => $user->id,
            'original_name' => '12th.jpg',
            'stored_name' => 'cards/1/fake_12th.jpg',
            'disk' => 'public',
            'path' => 'cards/1/fake_12th.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1200,
            'sync_id' => $fileSyncId1,
        ]);
        $c1File2 = CardFile::create([
            'card_id' => $card1->id,
            'uploaded_by' => $user->id,
            'original_name' => '14th.jpg',
            'stored_name' => 'cards/1/fake_14th.jpg',
            'disk' => 'public',
            'path' => 'cards/1/fake_14th.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1400,
            'sync_id' => $fileSyncId2,
        ]);

        $c2File1 = CardFile::create([
            'card_id' => $card2->id,
            'uploaded_by' => $user->id,
            'original_name' => '12th.jpg',
            'stored_name' => 'cards/2/fake_12th.jpg',
            'disk' => 'public',
            'path' => 'cards/2/fake_12th.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1200,
            'sync_id' => $fileSyncId1,
        ]);
        $c2File2 = CardFile::create([
            'card_id' => $card2->id,
            'uploaded_by' => $user->id,
            'original_name' => '14th.jpg',
            'stored_name' => 'cards/2/fake_14th.jpg',
            'disk' => 'public',
            'path' => 'cards/2/fake_14th.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1400,
            'sync_id' => $fileSyncId2,
        ]);

        $response = $this->actingAs($user)->postJson("/boards/cards/{$card1->id}/folders/assign", [
            'folder_name' => 'Photos',
            'file_ids' => [$c1File1->id, $c1File2->id],
        ]);

        $response->assertStatus(200);

        $c1File1->refresh();
        $c1File2->refresh();
        $c2File1->refresh();
        $c2File2->refresh();

        $this->assertSame('Photos', $c1File1->folder_name);
        $this->assertSame('Photos/12th.jpg', $c1File1->original_name);
        $this->assertSame('Photos', $c2File1->folder_name);
        $this->assertSame('Photos/12th.jpg', $c2File1->original_name);

        $this->assertSame('Photos', $c1File2->folder_name);
        $this->assertSame('Photos/14th.jpg', $c1File2->original_name);
        $this->assertSame('Photos', $c2File2->folder_name);
        $this->assertSame('Photos/14th.jpg', $c2File2->original_name);
    }

    public function test_show_card_auto_heals_folder_from_twin_card(): void
    {
        [$user, $board, $list, $card1] = $this->cardFixture();
        $syncGroupId = (string) \Illuminate\Support\Str::uuid();
        $card1->update(['sync_group_id' => $syncGroupId]);

        $card2 = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Twin Poster Campaign',
            'status' => 'todo',
            'sync_group_id' => $syncGroupId,
            'created_by' => $user->id,
        ]);
        $card2->assignees()->attach($user->id);

        $fileSyncId = (string) \Illuminate\Support\Str::uuid();

        Card::$isSyncing = true;
        try {
            // Card 1 has folder 'Photos'
            CardFile::create([
                'card_id' => $card1->id,
                'uploaded_by' => $user->id,
                'original_name' => 'Photos/16th.jpg',
                'folder_name' => 'Photos',
                'stored_name' => 'cards/1/fake_16th.jpg',
                'disk' => 'public',
                'path' => 'cards/1/fake_16th.jpg',
                'mime_type' => 'image/jpeg',
                'size' => 1600,
                'sync_id' => $fileSyncId,
            ]);

            // Card 2 lost folder_name and prefix
            $c2File = CardFile::create([
                'card_id' => $card2->id,
                'uploaded_by' => $user->id,
                'original_name' => '16th.jpg',
                'folder_name' => null,
                'stored_name' => 'cards/2/fake_16th.jpg',
                'disk' => 'public',
                'path' => 'cards/2/fake_16th.jpg',
                'mime_type' => 'image/jpeg',
                'size' => 1600,
                'sync_id' => $fileSyncId,
            ]);
        } finally {
            Card::$isSyncing = false;
        }

        $response = $this->actingAs($user)->getJson("/boards/cards/{$card2->id}");
        $response->assertStatus(200);

        $response->assertJsonPath('card.files.0.folder_name', 'Photos');
        $response->assertJsonPath('card.files.0.display_name', '16th.jpg');

        $c2File->refresh();
        $this->assertSame('Photos', $c2File->folder_name);
        $this->assertSame('Photos/16th.jpg', $c2File->original_name);
    }

    public function test_show_card_auto_heals_standalone_files_from_activity_log(): void
    {
        [$user, $board, $list, $card] = $this->cardFixture();

        $file = CardFile::create([
            'card_id' => $card->id,
            'uploaded_by' => $user->id,
            'original_name' => '18th.jpg',
            'folder_name' => null,
            'stored_name' => 'cards/1/fake_18th.jpg',
            'disk' => 'public',
            'path' => 'cards/1/fake_18th.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1800,
        ]);

        \App\Models\ActivityLog::create([
            'user_id' => $user->id,
            'subject_type' => Card::class,
            'subject_id' => $card->id,
            'action' => 'folder_assigned',
            'description' => 'grouped 1 files into folder **Photos**',
        ]);

        $response = $this->actingAs($user)->getJson("/boards/cards/{$card->id}");
        $response->assertStatus(200);

        $response->assertJsonPath('card.files.0.folder_name', 'Photos');
        $response->assertJsonPath('card.files.0.display_name', '18th.jpg');

        $file->refresh();
        $this->assertSame('Photos', $file->folder_name);
        $this->assertSame('Photos/18th.jpg', $file->original_name);
    }

    public function test_delete_folder_syncs_deletion_across_twins(): void
    {
        [$user, $board, $list, $card1] = $this->cardFixture();
        $syncGroupId = (string) \Illuminate\Support\Str::uuid();
        $card1->update(['sync_group_id' => $syncGroupId]);

        $card2 = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Twin Poster Campaign',
            'status' => 'todo',
            'sync_group_id' => $syncGroupId,
            'created_by' => $user->id,
        ]);
        $card2->assignees()->attach($user->id);

        $path1 = 'cards/1/photo.jpg';
        $path2 = 'cards/2/photo.jpg';
        Storage::disk('public')->put($path1, 'bytes1');
        Storage::disk('public')->put($path2, 'bytes2');

        CardFile::create([
            'card_id' => $card1->id,
            'uploaded_by' => $user->id,
            'original_name' => 'Photos/photo.jpg',
            'folder_name' => 'Photos',
            'stored_name' => $path1,
            'disk' => 'public',
            'path' => $path1,
            'mime_type' => 'image/jpeg',
            'size' => 100,
        ]);

        CardFile::create([
            'card_id' => $card2->id,
            'uploaded_by' => $user->id,
            'original_name' => 'Photos/photo.jpg',
            'folder_name' => 'Photos',
            'stored_name' => $path2,
            'disk' => 'public',
            'path' => $path2,
            'mime_type' => 'image/jpeg',
            'size' => 100,
        ]);

        $response = $this->actingAs($user)->deleteJson("/boards/cards/{$card1->id}/folders/Photos");
        $response->assertStatus(200);

        $this->assertCount(0, $card1->fresh()->files);
        $this->assertCount(0, $card2->fresh()->files);
    }
}
