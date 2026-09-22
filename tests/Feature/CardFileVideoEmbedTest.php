<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardFile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardFileVideoEmbedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
    }

    public function test_google_drive_link_generates_preview_embed_url_and_is_video_flag(): void
    {
        [$user, $board, $list, $card] = $this->cardFixture();

        $file1 = CardFile::create([
            'card_id'       => $card->id,
            'uploaded_by'   => $user->id,
            'original_name' => 'Demo Video Review',
            'stored_name'   => 'https://drive.google.com/file/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OIvE2e144/view?usp=sharing',
            'disk'          => 'url',
            'path'          => 'https://drive.google.com/file/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OIvE2e144/view?usp=sharing',
            'mime_type'     => 'link',
            'size'          => 0,
        ]);

        $this->assertTrue($file1->is_video);
        $this->assertSame(
            'https://drive.google.com/file/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OIvE2e144/preview',
            $file1->embed_url
        );
        $this->assertSame(
            'https://drive.google.com/thumbnail?id=1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OIvE2e144&sz=w320',
            $file1->thumbnail_url
        );

        $file2 = CardFile::create([
            'card_id'       => $card->id,
            'uploaded_by'   => $user->id,
            'original_name' => 'Company Drive Video',
            'stored_name'   => 'https://drive.google.com/open?id=1AbCdEfGhIjKlMnOpQrStUvWxYz',
            'disk'          => 'url',
            'path'          => 'https://drive.google.com/open?id=1AbCdEfGhIjKlMnOpQrStUvWxYz',
            'mime_type'     => 'link',
            'size'          => 0,
        ]);

        $this->assertTrue($file2->is_video);
        $this->assertSame(
            'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz/preview',
            $file2->embed_url
        );
        $this->assertSame(
            'https://drive.google.com/thumbnail?id=1AbCdEfGhIjKlMnOpQrStUvWxYz&sz=w320',
            $file2->thumbnail_url
        );
    }

    public function test_non_video_google_drive_link_is_not_marked_as_video(): void
    {
        [$user, $board, $list, $card] = $this->cardFixture();

        $file = CardFile::create([
            'card_id'       => $card->id,
            'uploaded_by'   => $user->id,
            'original_name' => 'Q3_Financial_Report.pdf',
            'stored_name'   => 'https://drive.google.com/file/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OIvE2e144/view',
            'disk'          => 'url',
            'path'          => 'https://drive.google.com/file/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OIvE2e144/view',
            'mime_type'     => 'link',
            'size'          => 0,
        ]);

        $this->assertFalse($file->is_video);
    }

    public function test_youtube_and_loom_links_embed_url(): void
    {
        [$user, $board, $list, $card] = $this->cardFixture();

        $youtubeFile = CardFile::create([
            'card_id'       => $card->id,
            'uploaded_by'   => $user->id,
            'original_name' => 'Tutorial Video',
            'stored_name'   => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'disk'          => 'url',
            'path'          => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'mime_type'     => 'link',
            'size'          => 0,
        ]);

        $this->assertTrue($youtubeFile->is_video);
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $youtubeFile->embed_url);

        $loomFile = CardFile::create([
            'card_id'       => $card->id,
            'uploaded_by'   => $user->id,
            'original_name' => 'Bug Repro',
            'stored_name'   => 'https://www.loom.com/share/abcdef123456',
            'disk'          => 'url',
            'path'          => 'https://www.loom.com/share/abcdef123456',
            'mime_type'     => 'link',
            'size'          => 0,
        ]);

        $this->assertTrue($loomFile->is_video);
        $this->assertSame('https://www.loom.com/embed/abcdef123456', $loomFile->embed_url);
    }

    public function test_card_controller_attach_link_endpoint_returns_video_metadata(): void
    {
        [$user, $board, $list, $card] = $this->cardFixture();

        $response = $this->actingAs($user)->postJson(route('boards.cards.files.store', $card), [
            'link_name' => 'Client Final Cut Video',
            'link_url'  => 'https://drive.google.com/file/d/1XyZ9876543210/view?usp=sharing',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('file.is_video', true);
        $response->assertJsonPath('file.embed_url', 'https://drive.google.com/file/d/1XyZ9876543210/preview');
    }

    public function test_card_controller_show_endpoint_returns_files_with_video_metadata(): void
    {
        [$user, $board, $list, $card] = $this->cardFixture();

        CardFile::create([
            'card_id'       => $card->id,
            'uploaded_by'   => $user->id,
            'original_name' => 'Sprint Demo Video',
            'stored_name'   => 'https://drive.google.com/file/d/1TestDriveId123/view',
            'disk'          => 'url',
            'path'          => 'https://drive.google.com/file/d/1TestDriveId123/view',
            'mime_type'     => 'link',
            'size'          => 0,
        ]);

        $response = $this->actingAs($user)->getJson(route('boards.cards.show', $card));

        $response->assertOk();
        $response->assertJsonPath('card.files.0.is_video', true);
        $response->assertJsonPath('card.files.0.embed_url', 'https://drive.google.com/file/d/1TestDriveId123/preview');
    }

    public function test_canva_link_generates_embed_url_and_is_canva_flag(): void
    {
        [$user, $board, $list, $card] = $this->cardFixture();

        $canvaFile = CardFile::create([
            'card_id'       => $card->id,
            'uploaded_by'   => $user->id,
            'original_name' => 'Canva Marketing Presentation',
            'stored_name'   => 'https://www.canva.com/design/DAGtest123/marketing-post/edit?utm_source=share',
            'disk'          => 'url',
            'path'          => 'https://www.canva.com/design/DAGtest123/marketing-post/edit?utm_source=share',
            'mime_type'     => 'link',
            'size'          => 0,
        ]);

        $this->assertTrue($canvaFile->is_canva);
        $this->assertFalse($canvaFile->is_video);
        $this->assertSame(
            'https://www.canva.com/design/DAGtest123/marketing-post/view?embed',
            $canvaFile->embed_url
        );
    }

    public function test_canva_link_via_endpoint_returns_canva_metadata(): void
    {
        [$user, $board, $list, $card] = $this->cardFixture();

        $response = $this->actingAs($user)->postJson(route('boards.cards.files.store', $card), [
            'link_name' => 'Social Media Canva Design',
            'link_url'  => 'https://www.canva.com/design/DAG999abc/view',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('file.is_canva', true);
        $response->assertJsonPath('file.embed_url', 'https://www.canva.com/design/DAG999abc/view?embed');
    }

    public function test_canva_resolve_endpoint(): void
    {
        [$user, $board, $list, $card] = $this->cardFixture();

        $response = $this->actingAs($user)->getJson(route('boards.cards.canva.resolve', [
            'url' => 'https://www.canva.com/design/DAHVU6Ka_wI/9hwKr0ABZZL84onQd_EPSQ/edit',
        ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('embed_url', 'https://www.canva.com/design/DAHVU6Ka_wI/9hwKr0ABZZL84onQd_EPSQ/view?embed');
    }

    private function cardFixture(): array
    {
        $user = User::factory()->create(['is_active' => true]);
        $workspace = Workspace::create([
            'name' => 'Video Workspace',
            'owner_id' => $user->id,
            'is_active' => true,
        ]);
        $board = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'Video Board',
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
            'title' => 'Video Card Review',
            'status' => 'todo',
            'priority' => 'medium',
            'position' => 0,
            'created_by' => $user->id,
        ]);
        $card->assignees()->attach($user->id);

        return [$user, $board, $list, $card];
    }
}
