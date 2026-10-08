<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardTrashTest extends TestCase
{
    use RefreshDatabase;

    public function test_trashed_cards_appear_in_board_trash_api()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $workspace = Workspace::create(['name' => 'Test Workspace', 'owner_id' => $user->id]);
        $board = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'Planning Board Test',
            'slug' => 'planning-board-test',
            'created_by' => $user->id,
        ]);
        $boardList = BoardList::create([
            'board_id' => $board->id,
            'name' => 'To Do',
            'position' => 1,
        ]);
        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $boardList->id,
            'title' => 'Test Card for Trash',
            'position' => 1,
            'created_by' => $user->id,
        ]);

        // Soft delete card
        $card->delete();

        // Query trash
        $response = $this->getJson("/boards/{$board->slug}/trash");
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $card->id,
            'title' => 'Test Card for Trash',
            'type' => 'card',
        ]);

        // Restore card
        $restoreResponse = $this->postJson("/boards/{$board->slug}/trash/restore", [
            'id' => $card->id,
            'type' => 'card',
        ]);
        $restoreResponse->assertStatus(200);
        $this->assertDatabaseHas('cards', [
            'id' => $card->id,
            'deleted_at' => null,
        ]);

        // Soft delete again and force delete
        $card->delete();
        $forceResponse = $this->deleteJson("/boards/{$board->slug}/trash/force", [
            'id' => $card->id,
            'type' => 'card',
        ]);
        $forceResponse->assertStatus(200);
        $this->assertDatabaseMissing('cards', [
            'id' => $card->id,
        ]);
    }
}
