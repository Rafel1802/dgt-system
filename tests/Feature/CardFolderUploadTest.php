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
}
