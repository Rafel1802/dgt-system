<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardChecklist;
use App\Models\CardChecklistItem;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ChecklistBulkAssignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    }

    private function createChecklistWithItems(User $owner, int $itemCount = 3): array
    {
        $workspace = Workspace::create(['name' => 'Marketing', 'owner_id' => $owner->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow', 'created_by' => $owner->id]);
        $list = BoardList::create(['board_id' => $board->id, 'name' => 'Team A', 'position' => 1]);
        $card = Card::create(['board_id' => $board->id, 'board_list_id' => $list->id, 'title' => 'Card 1', 'created_by' => $owner->id]);
        $checklist = CardChecklist::create(['card_id' => $card->id, 'title' => 'Blogs', 'position' => 1]);

        $items = [];
        for ($i = 1; $i <= $itemCount; $i++) {
            $items[] = CardChecklistItem::create([
                'checklist_id' => $checklist->id,
                'content' => "Task {$i}",
                'position' => $i,
            ]);
        }

        return [$card, $checklist, $items];
    }

    public function test_dara_can_bulk_assign_user_to_all_checklist_items(): void
    {
        $dara = User::factory()->create(['username' => 'dara', 'name' => 'Mr. Dara', 'is_active' => true]);
        $worker = User::factory()->create(['username' => 'sreypich', 'name' => 'Ms. Sreypich', 'is_active' => true]);
        [$card, $checklist, $items] = $this->createChecklistWithItems($dara, 3);

        $url = route('boards.cards.checklists.update', [$card, $checklist]);

        $response = $this->actingAs($dara)->patchJson($url, [
            'title' => 'Blogs Updated',
            'assigned_user_id' => $worker->id,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'checklist' => [
                'id' => $checklist->id,
                'title' => 'Blogs Updated',
            ],
        ]);

        $this->assertEquals('Blogs Updated', $checklist->fresh()->title);

        foreach ($checklist->items()->get() as $item) {
            $this->assertEquals($worker->id, $item->assigned_user_id);
            $this->assertEquals([$worker->id], $item->assigned_user_ids);
        }
    }

    public function test_kim_can_bulk_assign_user_to_all_checklist_items(): void
    {
        $kim = User::factory()->create(['username' => 'kim', 'name' => 'Mr. Kim', 'is_active' => true]);
        $worker = User::factory()->create(['username' => 'chhay', 'name' => 'Mr. Chhay', 'is_active' => true]);
        [$card, $checklist, $items] = $this->createChecklistWithItems($kim, 2);

        $url = route('boards.cards.checklists.update', [$card, $checklist]);

        $response = $this->actingAs($kim)->patchJson($url, [
            'title' => 'Blogs',
            'assigned_user_id' => $worker->id,
        ]);

        $response->assertOk();

        foreach ($checklist->items()->get() as $item) {
            $this->assertEquals($worker->id, $item->assigned_user_id);
            $this->assertEquals([$worker->id], $item->assigned_user_ids);
        }
    }

    public function test_dara_can_clear_all_assignees_in_checklist(): void
    {
        $dara = User::factory()->create(['username' => 'dara', 'name' => 'Mr. Dara', 'is_active' => true]);
        $worker = User::factory()->create(['username' => 'worker', 'name' => 'Worker', 'is_active' => true]);
        [$card, $checklist, $items] = $this->createChecklistWithItems($dara, 2);

        // Pre-assign
        foreach ($items as $item) {
            $item->update(['assigned_user_id' => $worker->id, 'assigned_user_ids' => [$worker->id]]);
        }

        $url = route('boards.cards.checklists.update', [$card, $checklist]);

        $response = $this->actingAs($dara)->patchJson($url, [
            'title' => 'Blogs',
            'assigned_user_id' => null,
        ]);

        $response->assertOk();

        foreach ($checklist->items()->get() as $item) {
            $this->assertNull($item->assigned_user_id);
            $this->assertNull($item->assigned_user_ids);
        }
    }

    public function test_non_head_user_cannot_bulk_assign_users(): void
    {
        $user = User::factory()->create(['username' => 'regular_user', 'name' => 'Regular User', 'is_active' => true]);
        $worker = User::factory()->create(['username' => 'worker', 'name' => 'Worker', 'is_active' => true]);
        [$card, $checklist, $items] = $this->createChecklistWithItems($user, 2);

        $url = route('boards.cards.checklists.update', [$card, $checklist]);

        $response = $this->actingAs($user)->patchJson($url, [
            'title' => 'Blogs',
            'assigned_user_id' => $worker->id,
        ]);

        $response->assertStatus(403);

        foreach ($checklist->items()->get() as $item) {
            $this->assertNull($item->assigned_user_id);
        }
    }

    public function test_non_head_user_can_still_rename_checklist_title(): void
    {
        $user = User::factory()->create(['username' => 'regular_user', 'name' => 'Regular User', 'is_active' => true]);
        [$card, $checklist, $items] = $this->createChecklistWithItems($user, 2);

        $url = route('boards.cards.checklists.update', [$card, $checklist]);

        $response = $this->actingAs($user)->patchJson($url, [
            'title' => 'Renamed Title',
        ]);

        $response->assertOk();
        $this->assertEquals('Renamed Title', $checklist->fresh()->title);
    }
}
