<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BoardTrashTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;
    protected Board $board;
    protected BoardList $list;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole('super-admin');

        $this->workspace = Workspace::create([
            'name' => 'Planning Workspace',
            'owner_id' => $this->user->id,
            'is_active' => true,
        ]);

        $this->board = Board::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Planning board – September 2026',
            'slug' => 'planning-board-september-2026',
            'created_by' => $this->user->id,
        ]);

        $this->list = BoardList::create([
            'board_id' => $this->board->id,
            'name' => 'Week 3',
            'position' => 0,
        ]);
    }

    public function test_single_card_deletion_moves_card_to_trash(): void
    {
        $card = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Single Deleted Card',
            'position' => 0,
            'created_by' => $this->user->id,
        ]);

        $res = $this->actingAs($this->user)->deleteJson("/boards/cards/{$card->id}");
        $res->assertOk();
        $res->assertJson(['message' => 'Card moved to Trash.']);

        $this->assertSoftDeleted('cards', ['id' => $card->id]);

        // Check trash API returns the card
        $trashRes = $this->actingAs($this->user)->getJson("/boards/{$this->board->slug}/trash");
        $trashRes->assertOk();
        $items = $trashRes->json('items');
        $this->assertCount(1, $items);
        $this->assertEquals('card', $items[0]['type']);
        $this->assertEquals($card->id, $items[0]['id']);
        $this->assertEquals('Single Deleted Card', $items[0]['title']);
    }

    public function test_bulk_card_deletion_moves_cards_to_trash(): void
    {
        $c1 = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Bulk Card 1',
            'position' => 0,
            'created_by' => $this->user->id,
        ]);
        $c2 = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Bulk Card 2',
            'position' => 1,
            'created_by' => $this->user->id,
        ]);

        $res = $this->actingAs($this->user)->postJson("/boards/{$this->board->slug}/cards/bulk", [
            'card_ids' => [$c1->id, $c2->id],
            'action' => 'delete',
        ]);
        $res->assertOk();

        $this->assertSoftDeleted('cards', ['id' => $c1->id]);
        $this->assertSoftDeleted('cards', ['id' => $c2->id]);

        $trashRes = $this->actingAs($this->user)->getJson("/boards/{$this->board->slug}/trash");
        $trashRes->assertOk();
        $items = $trashRes->json('items');
        $this->assertCount(2, $items);
    }

    public function test_card_with_null_board_id_still_appears_in_board_trash(): void
    {
        $card = Card::create([
            'board_id' => null, // e.g. legacy or partially filled card
            'board_list_id' => $this->list->id,
            'title' => 'Card with null board_id',
            'position' => 0,
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)->deleteJson("/boards/cards/{$card->id}");

        $trashRes = $this->actingAs($this->user)->getJson("/boards/{$this->board->slug}/trash");
        $trashRes->assertOk();
        $items = $trashRes->json('items');
        $this->assertCount(1, $items);
        $this->assertEquals('Card with null board_id', $items[0]['title']);
    }

    public function test_cleanup_trash_command_preserves_recent_cards_and_purges_older_than_7_days(): void
    {
        // Card deleted yesterday (should NOT be purged)
        $yesterdayCard = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Deleted Yesterday',
            'position' => 0,
            'created_by' => $this->user->id,
        ]);
        $yesterdayCard->delete();
        Card::withTrashed()->where('id', $yesterdayCard->id)->update(['deleted_at' => Carbon::now()->subDays(1)]);

        // Card deleted 8 days ago (SHOULD be purged)
        $oldCard = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Deleted 8 Days Ago',
            'position' => 1,
            'created_by' => $this->user->id,
        ]);
        $oldCard->delete();
        Card::withTrashed()->where('id', $oldCard->id)->update(['deleted_at' => Carbon::now()->subDays(8)]);

        Artisan::call('app:cleanup-trash');

        // Yesterday's card must still exist in trash
        $this->assertDatabaseHas('cards', [
            'id' => $yesterdayCard->id,
            'title' => 'Deleted Yesterday',
        ]);
        $this->assertNotNull(Card::withTrashed()->find($yesterdayCard->id)->deleted_at);

        // 8-day old card must be permanently removed
        $this->assertDatabaseMissing('cards', [
            'id' => $oldCard->id,
        ]);
    }
}
