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

class ChecklistReviewMarkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    }

    private function makeItem(User $owner): array
    {
        $workspace = Workspace::create(['name' => 'Marketing', 'owner_id' => $owner->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow', 'created_by' => $owner->id]);
        $list = BoardList::create(['board_id' => $board->id, 'name' => 'Production Team', 'position' => 1]);
        $card = Card::create(['board_id' => $board->id, 'board_list_id' => $list->id, 'title' => 'Card', 'created_by' => $owner->id]);
        $checklist = CardChecklist::create(['card_id' => $card->id, 'title' => 'Status', 'position' => 1]);
        $item = CardChecklistItem::create(['checklist_id' => $checklist->id, 'content' => '1. Poster : 12TH-OCT', 'position' => 1]);

        return [$card, $checklist, $item];
    }

    private function url(Card $card, CardChecklist $cl, CardChecklistItem $item): string
    {
        return route('boards.cards.checklists.items.review', [$card, $cl, $item]);
    }

    public function test_only_dara_and_kim_can_mark_and_flag_issue(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $dara = User::factory()->create(['username' => 'dara', 'name' => 'Mr. Dara (Head)', 'is_active' => true]);
        $other = User::factory()->create(['username' => 'someone', 'name' => 'Someone', 'is_active' => true]);
        [$card, $cl, $item] = $this->makeItem($dara);

        $this->actingAs($other)->patchJson($this->url($card, $cl, $item), ['action' => 'mark'])->assertStatus(403);
        $this->actingAs($other)->patchJson($this->url($card, $cl, $item), ['action' => 'issue'])->assertStatus(403);

        $res = $this->actingAs($dara)->patchJson($this->url($card, $cl, $item), ['action' => 'mark'])
            ->assertOk()
            ->assertJsonPath('item.is_marked', true)
            ->assertJsonPath('item.marked_user.name', 'Mr. Dara (Head)')
            ->assertJsonPath('item.marked_user.username', 'dara');

        // No notification sent on checklist review mark
        \Illuminate\Support\Facades\Notification::assertNothingSent();

        // Untick
        $this->actingAs($dara)->patchJson($this->url($card, $cl, $item), ['action' => 'mark'])
            ->assertOk()->assertJsonPath('item.is_marked', false);

        // Flag issue clears the mark
        $this->actingAs($dara)->patchJson($this->url($card, $cl, $item), ['action' => 'mark']);
        $this->actingAs($dara)->patchJson($this->url($card, $cl, $item), ['action' => 'issue'])
            ->assertOk()
            ->assertJsonPath('item.has_issue', true)
            ->assertJsonPath('item.is_marked', false)
            ->assertJsonPath('item.issue_user.name', 'Mr. Dara (Head)');

        \Illuminate\Support\Facades\Notification::assertNothingSent();
    }

    public function test_only_admins_can_approve(): void
    {
        $kim = User::factory()->create(['username' => 'kim', 'name' => 'Kim', 'is_active' => true]);
        $admin = User::factory()->create(['username' => 'boss1', 'is_active' => true]);
        $admin->assignRole('super-admin');
        [$card, $cl, $item] = $this->makeItem($kim);

        // Cannot approve before anything is marked
        $this->actingAs($admin)->patchJson($this->url($card, $cl, $item), ['action' => 'approve'])->assertStatus(422);

        $this->actingAs($kim)->patchJson($this->url($card, $cl, $item), ['action' => 'issue'])->assertOk();

        // dara/kim are not admins -> cannot approve
        $this->actingAs($kim)->patchJson($this->url($card, $cl, $item), ['action' => 'approve'])->assertStatus(403);

        $this->actingAs($admin)->patchJson($this->url($card, $cl, $item), ['action' => 'approve'])
            ->assertOk()
            ->assertJsonPath('item.is_approved', true)
            ->assertJsonPath('item.has_issue', false)
            ->assertJsonPath('item.is_marked', true);
    }

    public function test_listing_and_content_items_auto_assign_fixed_users(): void
    {
        $chhay = User::factory()->create(['username' => 'chhay', 'name' => 'Chhay', 'is_active' => true]);
        $sreypich = User::factory()->create(['username' => 'sreypich', 'name' => 'Sreypich', 'is_active' => true]);
        [$card] = $this->makeItem($chhay);

        $this->assertEquals('listing', CardChecklistItem::detectCategory('1. Listing : 12TH-OCT'));
        $this->assertEquals('content', CardChecklistItem::detectCategory('Content : 13TH-OCT'));
        $this->assertEquals('video', CardChecklistItem::detectCategory('Video Content : 13TH-OCT'));

        // Works even though neither user is a member of the card
        $this->assertEquals($chhay->id, CardChecklistItem::detectUserIdForCard('Listing : 12TH-OCT', $card));
        $this->assertEquals($sreypich->id, CardChecklistItem::detectUserIdForCard('Content : 13TH-OCT', $card));
    }

    public function test_assigned_item_can_only_be_ticked_by_assignees_or_override_users(): void
    {
        $pich = User::factory()->create(['username' => 'pich', 'name' => 'Mr. Pich', 'is_active' => true]);
        $nalin = User::factory()->create(['username' => 'nalin', 'name' => 'Ms. Nalin', 'is_active' => true]);
        $stranger = User::factory()->create(['username' => 'stranger', 'name' => 'Stranger', 'is_active' => true]);
        $dara = User::factory()->create(['username' => 'dara', 'name' => 'Dara', 'is_active' => true]);
        $somalika = User::factory()->create(['username' => 'somalika', 'name' => 'Ms. Somalika (Supervisor)', 'is_active' => true]);
        [$card, $cl, $item] = $this->makeItem($pich);

        $url = route('boards.cards.checklists.items.toggle', [$card, $cl, $item]);

        // Assign two members at once
        $this->actingAs($pich)->patchJson($url, ['assigned_user_ids' => [$pich->id, $nalin->id]])
            ->assertOk()->assertJsonCount(2, 'item.assigned_users');

        // Stranger is blocked
        $this->actingAs($stranger)->patchJson($url)->assertStatus(403);
        $this->assertFalse($item->fresh()->is_completed);

        // Either assignee can tick
        $this->actingAs($nalin)->patchJson($url)->assertOk();
        $this->assertTrue($item->fresh()->is_completed);

        // dara & somalika can override
        $this->actingAs($dara)->patchJson($url)->assertOk();
        $this->assertFalse($item->fresh()->is_completed);
        $this->actingAs($somalika)->patchJson($url)->assertOk();
        $this->assertTrue($item->fresh()->is_completed);
    }

    public function test_unassigned_item_can_be_ticked_by_anyone(): void
    {
        $owner = User::factory()->create(['username' => 'owner', 'is_active' => true]);
        $stranger = User::factory()->create(['username' => 'stranger', 'is_active' => true]);
        [$card, $cl, $item] = $this->makeItem($owner);

        $this->actingAs($stranger)
            ->patchJson(route('boards.cards.checklists.items.toggle', [$card, $cl, $item]))
            ->assertOk();
        $this->assertTrue($item->fresh()->is_completed);
    }

    public function test_marking_checklist_item_syncs_to_twin_cards_in_same_sync_group(): void
    {
        $dara = User::factory()->create(['username' => 'dara', 'name' => 'Mr. Dara (Head)', 'is_active' => true]);
        $workspace = Workspace::create(['name' => 'Digital Dept', 'owner_id' => $dara->id, 'is_active' => true]);
        $board1 = Board::create(['workspace_id' => $workspace->id, 'name' => 'Planning Board', 'created_by' => $dara->id]);
        $board2 = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow Board', 'created_by' => $dara->id]);
        $list1 = BoardList::create(['board_id' => $board1->id, 'name' => 'Week 1', 'position' => 1]);
        $list2 = BoardList::create(['board_id' => $board2->id, 'name' => 'Production Team A', 'position' => 1]);

        $syncGroupId = (string) \Illuminate\Support\Str::uuid();
        $card1 = Card::create(['board_id' => $board1->id, 'board_list_id' => $list1->id, 'title' => 'Task Card', 'sync_group_id' => $syncGroupId, 'created_by' => $dara->id]);
        $card2 = Card::create(['board_id' => $board2->id, 'board_list_id' => $list2->id, 'title' => 'Task Card', 'sync_group_id' => $syncGroupId, 'created_by' => $dara->id]);

        $cl1 = CardChecklist::create(['card_id' => $card1->id, 'title' => 'Status', 'position' => 1]);
        $cl2 = CardChecklist::create(['card_id' => $card2->id, 'title' => 'Status', 'position' => 1]);

        $item1 = CardChecklistItem::create(['checklist_id' => $cl1->id, 'content' => '1. Poster : 12TH-OCT', 'position' => 1]);
        $item2 = CardChecklistItem::create(['checklist_id' => $cl2->id, 'content' => '1. Poster : 12TH-OCT', 'position' => 1]);

        // Dara marks item1 with green tick
        $this->actingAs($dara)->patchJson($this->url($card1, $cl1, $item1), ['action' => 'mark'])
            ->assertOk()
            ->assertJsonPath('item.is_marked', true);

        // Verify item2 on twin card was automatically updated with the exact same review mark and unified sync_id
        $item2Fresh = $item2->fresh();
        $this->assertTrue($item2Fresh->is_marked);
        $this->assertEquals($dara->id, $item2Fresh->marked_by);
        $this->assertNotNull($item2Fresh->marked_at);
        $this->assertEquals($item1->fresh()->sync_id, $item2Fresh->sync_id);

        // Now Dara flags issue on item1
        $this->actingAs($dara)->patchJson($this->url($card1, $cl1, $item1), ['action' => 'issue'])
            ->assertOk()
            ->assertJsonPath('item.has_issue', true);

        $item2Fresh = $item2->fresh();
        $this->assertTrue($item2Fresh->has_issue);
        $this->assertFalse($item2Fresh->is_marked);
        $this->assertEquals($dara->id, $item2Fresh->issue_by);
    }

    public function test_unrelated_update_to_item_does_not_wipe_out_review_marks(): void
    {
        $dara = User::factory()->create(['username' => 'dara', 'name' => 'Mr. Dara (Head)', 'is_active' => true]);
        $chhay = User::factory()->create(['username' => 'chhay', 'name' => 'Chhay', 'is_active' => true]);
        $workspace = Workspace::create(['name' => 'Digital Dept', 'owner_id' => $dara->id, 'is_active' => true]);
        $board1 = Board::create(['workspace_id' => $workspace->id, 'name' => 'Planning Board', 'created_by' => $dara->id]);
        $board2 = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow Board', 'created_by' => $dara->id]);
        $list1 = BoardList::create(['board_id' => $board1->id, 'name' => 'Week 1', 'position' => 1]);
        $list2 = BoardList::create(['board_id' => $board2->id, 'name' => 'Production Team A', 'position' => 1]);

        $syncGroupId = (string) \Illuminate\Support\Str::uuid();
        $card1 = Card::create(['board_id' => $board1->id, 'board_list_id' => $list1->id, 'title' => 'Task Card', 'sync_group_id' => $syncGroupId, 'created_by' => $dara->id]);
        $card2 = Card::create(['board_id' => $board2->id, 'board_list_id' => $list2->id, 'title' => 'Task Card', 'sync_group_id' => $syncGroupId, 'created_by' => $dara->id]);

        $cl1 = CardChecklist::create(['card_id' => $card1->id, 'title' => 'Status', 'position' => 1]);
        $cl2 = CardChecklist::create(['card_id' => $card2->id, 'title' => 'Status', 'position' => 1]);

        $sharedSyncId = (string) \Illuminate\Support\Str::uuid();
        $item1 = CardChecklistItem::create([
            'checklist_id' => $cl1->id,
            'content' => '1. Poster : 12TH-OCT',
            'position' => 1,
            'sync_id' => $sharedSyncId,
            'is_marked' => true,
            'marked_by' => $dara->id,
            'marked_at' => now(),
        ]);
        $item2 = CardChecklistItem::create([
            'checklist_id' => $cl2->id,
            'content' => '1. Poster : 12TH-OCT',
            'position' => 1,
            'sync_id' => $sharedSyncId,
            'is_marked' => true,
            'marked_by' => $dara->id,
            'marked_at' => now(),
        ]);

        // Simulating upload_and_clear.py deploy updating assigned_user_id
        $item1->update(['assigned_user_id' => $chhay->id, 'assigned_user_ids' => [$chhay->id]]);

        $item2Fresh = $item2->fresh();
        $this->assertEquals($chhay->id, $item2Fresh->assigned_user_id);
        // Review mark MUST still be true and not wiped back to false!
        $this->assertTrue($item2Fresh->is_marked, 'Review mark was wiped out after unrelated update!');
        $this->assertEquals($dara->id, $item2Fresh->marked_by);
    }

    public function test_card_show_auto_heals_missing_review_marks_from_twin_card(): void
    {
        $dara = User::factory()->create(['username' => 'dara', 'name' => 'Mr. Dara (Head)', 'is_active' => true]);
        $workspace = Workspace::create(['name' => 'Digital Dept', 'owner_id' => $dara->id, 'is_active' => true]);
        $board1 = Board::create(['workspace_id' => $workspace->id, 'name' => 'Planning Board', 'created_by' => $dara->id]);
        $board2 = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow Board', 'created_by' => $dara->id]);
        $list1 = BoardList::create(['board_id' => $board1->id, 'name' => 'Week 1', 'position' => 1]);
        $list2 = BoardList::create(['board_id' => $board2->id, 'name' => 'Production Team A', 'position' => 1]);

        $syncGroupId = (string) \Illuminate\Support\Str::uuid();
        $card1 = Card::create(['board_id' => $board1->id, 'board_list_id' => $list1->id, 'title' => 'Card 1', 'sync_group_id' => $syncGroupId, 'created_by' => $dara->id]);
        $card2 = Card::create(['board_id' => $board2->id, 'board_list_id' => $list2->id, 'title' => 'Card 2', 'sync_group_id' => $syncGroupId, 'created_by' => $dara->id]);

        $cl1 = CardChecklist::create(['card_id' => $card1->id, 'title' => 'Status', 'position' => 1]);
        $cl2 = CardChecklist::create(['card_id' => $card2->id, 'title' => 'Status', 'position' => 1]);

        // item1 was marked
        $item1 = CardChecklistItem::create([
            'checklist_id' => $cl1->id,
            'content' => '2. Poster : 13TH-OCT',
            'position' => 1,
            'is_marked' => true,
            'marked_by' => $dara->id,
            'marked_at' => now(),
        ]);
        // item2 had not been marked yet (e.g. from separate import)
        $item2 = CardChecklistItem::create([
            'checklist_id' => $cl2->id,
            'content' => '2.Poster: 13TH-OCT',
            'position' => 1,
            'is_marked' => false,
        ]);

        // When card2 is loaded via show(), it must auto-heal and receive the mark
        $response = $this->actingAs($dara)->getJson("/boards/cards/{$card2->id}");
        $response->assertOk();

        $item2Fresh = $item2->fresh();
        $this->assertTrue($item2Fresh->is_marked);
        $this->assertEquals($dara->id, $item2Fresh->marked_by);
        $this->assertEquals($item1->fresh()->sync_id, $item2Fresh->sync_id);
    }

    public function test_replicate_relationally_preserves_review_marks(): void
    {
        $dara = User::factory()->create(['username' => 'dara', 'name' => 'Mr. Dara (Head)', 'is_active' => true]);
        [$card, $cl, $item] = $this->makeItem($dara);

        $item->update([
            'is_marked' => true,
            'marked_by' => $dara->id,
            'marked_at' => now(),
        ]);

        $replica = $card->replicateRelationally(true, $dara->id);
        $replicaItem = $replica->checklists->first()->items->first();

        $this->assertTrue($replicaItem->is_marked);
        $this->assertEquals($dara->id, $replicaItem->marked_by);
        $this->assertEquals($item->fresh()->sync_id, $replicaItem->sync_id);
    }

    public function test_cards_sync_checklists_command_runs_successfully(): void
    {
        $this->artisan('cards:sync-checklists')->assertExitCode(0);
    }

    public function test_show_card_auto_heals_missing_twin_checklist_and_completed_items(): void
    {
        $dara = User::factory()->create(['username' => 'dara_sync', 'name' => 'Dara Sync', 'is_active' => true]);
        $ws = Workspace::create(['name' => 'Digital WS', 'owner_id' => $dara->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $ws->id, 'name' => 'Workflow A', 'created_by' => $dara->id]);
        $list = BoardList::create(['board_id' => $board->id, 'name' => 'Production Team', 'position' => 1]);
        $syncGroupId = (string) \Illuminate\Support\Str::uuid();

        $card1 = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Master Card A',
            'status' => 'todo',
            'sync_group_id' => $syncGroupId,
            'created_by' => $dara->id,
        ]);
        $card2 = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Twin Card B',
            'status' => 'todo',
            'sync_group_id' => $syncGroupId,
            'created_by' => $dara->id,
        ]);

        Card::$isSyncing = true;
        try {
            $cl1 = CardChecklist::create(['card_id' => $card1->id, 'title' => 'Status', 'position' => 1]);
            $item1 = CardChecklistItem::create([
                'checklist_id' => $cl1->id,
                'content' => 'Final Graphic Review',
                'position' => 1,
                'is_completed' => true,
                'completed_by' => $dara->id,
                'completed_at' => now(),
                'is_marked' => true,
                'marked_by' => $dara->id,
                'marked_at' => now(),
            ]);
        } finally {
            Card::$isSyncing = false;
        }

        // Card 2 initially has no checklists at all
        $this->assertCount(0, $card2->fresh()->checklists);

        // Loading card 2 modal via show() must auto-heal: create checklist, item, completion and review mark
        $response = $this->actingAs($dara)->getJson("/boards/cards/{$card2->id}");
        $response->assertOk();

        $card2Fresh = $card2->fresh(['checklists.items']);
        $this->assertCount(1, $card2Fresh->checklists);
        $cl2 = $card2Fresh->checklists->first();
        $this->assertSame('Status', $cl2->title);
        $this->assertCount(1, $cl2->items);

        $item2 = $cl2->items->first();
        $this->assertSame('Final Graphic Review', $item2->content);
        $this->assertTrue($item2->is_completed);
        $this->assertTrue($item2->is_marked);
        $this->assertEquals($item1->fresh()->sync_id, $item2->sync_id);

        // Run sync-checklists artisan command to ensure it never reverts completed status or review marks
        $this->artisan('cards:sync-checklists')->assertExitCode(0);

        $item1After = $item1->fresh();
        $item2After = $item2->fresh();
        $this->assertTrue($item1After->is_completed);
        $this->assertTrue($item2After->is_completed);
        $this->assertTrue($item1After->is_marked);
        $this->assertTrue($item2After->is_marked);
    }

    public function test_toggle_and_review_mark_propagate_immediately_to_twins_and_heal(): void
    {
        $dara = User::factory()->create(['username' => 'dara_prop', 'name' => 'Dara Prop', 'is_active' => true]);
        $ws = Workspace::create(['name' => 'Digital WS 2', 'owner_id' => $dara->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $ws->id, 'name' => 'Workflow B', 'created_by' => $dara->id]);
        $list = BoardList::create(['board_id' => $board->id, 'name' => 'Production Team', 'position' => 1]);
        $syncGroupId = (string) \Illuminate\Support\Str::uuid();

        $card1 = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Card 1',
            'status' => 'todo',
            'sync_group_id' => $syncGroupId,
            'created_by' => $dara->id,
        ]);
        $card2 = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Card 2',
            'status' => 'todo',
            'sync_group_id' => $syncGroupId,
            'created_by' => $dara->id,
        ]);

        $cl1 = CardChecklist::create(['card_id' => $card1->id, 'title' => 'Status', 'position' => 1]);
        $cl2 = CardChecklist::create(['card_id' => $card2->id, 'title' => 'Status', 'position' => 1]);

        $item1 = CardChecklistItem::create([
            'checklist_id' => $cl1->id,
            'content' => 'Deploy Checklist Item',
            'position' => 1,
            'is_completed' => false,
        ]);
        $item2 = CardChecklistItem::create([
            'checklist_id' => $cl2->id,
            'content' => 'Deploy Checklist Item',
            'position' => 1,
            'is_completed' => false,
        ]);

        // Toggle item 1 to completed
        $toggleRes = $this->actingAs($dara)->patchJson(route('boards.cards.checklists.items.toggle', [$card1, $cl1, $item1]));
        $toggleRes->assertOk();
        $this->assertTrue($item1->fresh()->is_completed);
        $this->assertTrue($item2->fresh()->is_completed);

        // Mark item 1 with green tick
        $markRes = $this->actingAs($dara)->patchJson($this->url($card1, $cl1, $item1), [
            'action' => 'mark',
        ]);
        $markRes->assertOk();
        $this->assertTrue($item1->fresh()->is_marked);
        $this->assertTrue($item2->fresh()->is_marked);

        // Open card 2 modal and ensure everything stays completed and marked
        $showRes = $this->actingAs($dara)->getJson("/boards/cards/{$card2->id}");
        $showRes->assertOk();
        $this->assertTrue($item1->fresh()->is_completed);
        $this->assertTrue($item2->fresh()->is_completed);
        $this->assertTrue($item1->fresh()->is_marked);
        $this->assertTrue($item2->fresh()->is_marked);
    }
}
