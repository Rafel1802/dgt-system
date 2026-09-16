<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardContentTypeTest extends TestCase
{
    use RefreshDatabase;

    private function createFixture(): array
    {
        $user = User::factory()->create(['is_active' => true]);
        $workspace = Workspace::create([
            'name' => 'Marketing Workspace',
            'owner_id' => $user->id,
            'is_active' => true,
        ]);
        $board = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'SMM Planning Board',
            'created_by' => $user->id,
            'type' => 'smm',
        ]);
        $list = BoardList::create([
            'board_id' => $board->id,
            'name' => 'Week 1',
            'position' => 1,
        ]);
        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Sample SMM Post',
            'position' => 1,
            'created_by' => $user->id,
        ]);

        return [$user, $board, $list, $card];
    }

    public function test_can_update_card_content_type(): void
    {
        [$user, $board, $list, $card] = $this->createFixture();

        $response = $this->actingAs($user)->patchJson(route('boards.cards.update', $card), [
            'smm_cluster_label' => 'Short Reel',
        ]);

        $response->assertOk();
        $this->assertSame('Short Reel', $card->fresh()->smm_cluster_label);

        $activity = ActivityLog::where('action', 'card.smm_cluster_changed')->latest('id')->first();
        $this->assertNotNull($activity);
        $this->assertStringContainsString('changed Content Type to **Short Reel**', $activity->description);
    }

    public function test_can_clear_card_content_type(): void
    {
        [$user, $board, $list, $card] = $this->createFixture();
        $card->update(['smm_cluster_label' => 'Short Reel']);

        $response = $this->actingAs($user)->patchJson(route('boards.cards.update', $card), [
            'smm_cluster_label' => null,
        ]);

        $response->assertOk();
        $this->assertNull($card->fresh()->smm_cluster_label);

        $activity = ActivityLog::where('action', 'card.smm_cluster_changed')->latest('id')->first();
        $this->assertNotNull($activity);
        $this->assertStringContainsString('cleared Content Type', $activity->description);
    }

    public function test_can_create_card_with_content_type(): void
    {
        [$user, $board, $list] = $this->createFixture();

        $response = $this->actingAs($user)->postJson(route('boards.cards.store', $board), [
            'board_list_id' => $list->id,
            'title' => 'New Card with Content Type',
            'smm_cluster_label' => 'Poster Design',
        ]);

        $response->assertStatus(201);
        $newCard = Card::where('title', 'New Card with Content Type')->first();
        $this->assertNotNull($newCard);
        $this->assertSame('Poster Design', $newCard->smm_cluster_label);
    }
}
