<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardAutomation;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardChecklist;
use App\Models\CardChecklistItem;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ChecklistRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    }

    public function test_card_with_incomplete_checklist_cannot_comment_ready(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true, 'team_role' => 'Digital Team']);
        $workspace = Workspace::create(['name' => 'Marketing', 'owner_id' => $user->id, 'is_active' => true]);
        $planningBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'Team A Planning Board - Oct 2026', 'created_by' => $user->id]);
        $workflowBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board - Oct 2026', 'created_by' => $user->id]);
        
        $pList = BoardList::create(['board_id' => $planningBoard->id, 'name' => 'Week 1', 'position' => 1]);
        $wList = BoardList::create(['board_id' => $workflowBoard->id, 'name' => 'Draft', 'position' => 1]);

        $card = Card::create([
            'board_id' => $planningBoard->id,
            'board_list_id' => $pList->id,
            'title' => 'Social Post Card',
            'created_by' => $user->id,
            'status' => 'todo',
            'position' => 0,
        ]);
        $card->assignees()->attach($user->id);

        // Add a checklist with 2 items, only 1 completed (50%)
        $checklist = CardChecklist::create([
            'card_id' => $card->id,
            'title' => 'Task Checklist',
            'position' => 1,
        ]);
        CardChecklistItem::create([
            'checklist_id' => $checklist->id,
            'content' => 'Write caption',
            'is_completed' => true,
            'position' => 1,
        ]);
        CardChecklistItem::create([
            'checklist_id' => $checklist->id,
            'content' => 'Review graphics',
            'is_completed' => false,
            'position' => 2,
        ]);

        // Attempt to comment "Ready"
        $response = $this->actingAs($user)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'Ready',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['checklist_incomplete' => true]);

        // Ensure card was NOT copied to workflow board
        $copiesInWorkflow = Card::where('board_id', $workflowBoard->id)->count();
        $this->assertEquals(0, $copiesInWorkflow, 'Card must NOT be copied to workflow board when checklist is not 100%');
    }

    public function test_card_with_incomplete_checklist_cannot_be_moved_to_another_list(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true, 'team_role' => 'Digital Team']);
        $workspace = Workspace::create(['name' => 'Marketing', 'owner_id' => $user->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Team A Planning Board', 'created_by' => $user->id]);
        $list1 = BoardList::create(['board_id' => $board->id, 'name' => 'Week 1', 'position' => 1]);
        $list2 = BoardList::create(['board_id' => $board->id, 'name' => 'Week 2', 'position' => 2]);

        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list1->id,
            'title' => 'Moving Test Card',
            'created_by' => $user->id,
            'status' => 'todo',
            'position' => 0,
        ]);
        $card->assignees()->attach($user->id);

        $checklist = CardChecklist::create([
            'card_id' => $card->id,
            'title' => 'Tasks',
            'position' => 1,
        ]);
        CardChecklistItem::create([
            'checklist_id' => $checklist->id,
            'content' => 'Incomplete task',
            'is_completed' => false,
            'position' => 1,
        ]);

        // Attempt move
        $response = $this->actingAs($user)->postJson(route('boards.cards.move', $card), [
            'board_list_id' => $list2->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['checklist_incomplete' => true]);

        $card->refresh();
        $this->assertEquals($list1->id, $card->board_list_id, 'Card list must remain list1');
    }

    public function test_card_with_incomplete_checklist_cannot_be_copied(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true, 'team_role' => 'Digital Team']);
        $workspace = Workspace::create(['name' => 'Marketing', 'owner_id' => $user->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Team A Planning Board', 'created_by' => $user->id]);
        $list1 = BoardList::create(['board_id' => $board->id, 'name' => 'Week 1', 'position' => 1]);

        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list1->id,
            'title' => 'Copying Test Card',
            'created_by' => $user->id,
            'status' => 'todo',
            'position' => 0,
        ]);

        $checklist = CardChecklist::create([
            'card_id' => $card->id,
            'title' => 'Tasks',
            'position' => 1,
        ]);
        CardChecklistItem::create([
            'checklist_id' => $checklist->id,
            'content' => 'Incomplete task',
            'is_completed' => false,
            'position' => 1,
        ]);

        // Attempt copy
        $response = $this->actingAs($user)->postJson(route('boards.cards.copy', $card), [
            'title' => 'Copy of Card',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['checklist_incomplete' => true]);

        $this->assertEquals(1, Card::count(), 'No new copied card should be created');
    }

    public function test_card_with_100_percent_checklist_can_be_commented_ready_and_copied_to_workflow(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true, 'team_role' => 'Digital Team']);
        $workspace = Workspace::create(['name' => 'Marketing', 'owner_id' => $user->id, 'is_active' => true]);
        $planningBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'Team A Planning Board - Oct 2026', 'created_by' => $user->id]);
        $workflowBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board - Oct 2026', 'created_by' => $user->id]);
        
        $pList = BoardList::create(['board_id' => $planningBoard->id, 'name' => 'Week 1', 'position' => 1]);
        $wList = BoardList::create(['board_id' => $workflowBoard->id, 'name' => 'Draft', 'position' => 1]);

        $card = Card::create([
            'board_id' => $planningBoard->id,
            'board_list_id' => $pList->id,
            'title' => '100% Complete Card',
            'created_by' => $user->id,
            'status' => 'todo',
            'position' => 0,
        ]);
        $card->assignees()->attach($user->id);

        $checklist = CardChecklist::create([
            'card_id' => $card->id,
            'title' => 'Complete Checklist',
            'position' => 1,
        ]);
        CardChecklistItem::create([
            'checklist_id' => $checklist->id,
            'content' => 'Finished task 1',
            'is_completed' => true,
            'position' => 1,
        ]);
        CardChecklistItem::create([
            'checklist_id' => $checklist->id,
            'content' => 'Finished task 2',
            'is_completed' => true,
            'position' => 2,
        ]);

        // Comment "Ready"
        $response = $this->actingAs($user)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'Ready',
        ]);

        $response->assertCreated();

        // Card should be replicated to the workflow board
        $copiesInWorkflow = Card::where('board_id', $workflowBoard->id)->count();
        $this->assertEquals(1, $copiesInWorkflow, 'Card with 100% checklist SHOULD be copied to workflow board upon Ready comment');
    }

    public function test_card_with_100_percent_checklist_can_be_moved_and_copied(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true, 'team_role' => 'Digital Team']);
        $workspace = Workspace::create(['name' => 'Marketing', 'owner_id' => $user->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Team A Planning Board', 'created_by' => $user->id]);
        $list1 = BoardList::create(['board_id' => $board->id, 'name' => 'Week 1', 'position' => 1]);
        $list2 = BoardList::create(['board_id' => $board->id, 'name' => 'Week 2', 'position' => 2]);

        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list1->id,
            'title' => 'Done Card',
            'created_by' => $user->id,
            'status' => 'todo',
            'position' => 0,
        ]);
        $card->assignees()->attach($user->id);

        $checklist = CardChecklist::create([
            'card_id' => $card->id,
            'title' => 'Checklist',
            'position' => 1,
        ]);
        CardChecklistItem::create([
            'checklist_id' => $checklist->id,
            'content' => 'Done item',
            'is_completed' => true,
            'position' => 1,
        ]);

        // Move
        $moveResponse = $this->actingAs($user)->postJson(route('boards.cards.move', $card), [
            'board_list_id' => $list2->id,
        ]);
        $moveResponse->assertOk();
        $card->refresh();
        $this->assertEquals($list2->id, $card->board_list_id);

        // Copy
        $copyResponse = $this->actingAs($user)->postJson(route('boards.cards.copy', $card), [
            'title' => 'Copied Done Card',
        ]);
        $copyResponse->assertSuccessful();
        $this->assertEquals(2, Card::count());
    }

    public function test_card_with_incomplete_checklist_cannot_use_comment_automations(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true, 'team_role' => 'supervisor']);
        $workspace = Workspace::create(['name' => 'Marketing', 'owner_id' => $user->id, 'is_active' => true]);
        $workflowBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board - Oct 2026', 'created_by' => $user->id]);
        
        $draftList = BoardList::create(['board_id' => $workflowBoard->id, 'name' => 'Draft', 'position' => 1]);
        $prodList = BoardList::create(['board_id' => $workflowBoard->id, 'name' => 'Production Team', 'position' => 2]);
        $approvedList = BoardList::create(['board_id' => $workflowBoard->id, 'name' => 'Approved', 'position' => 3]);

        $card = Card::create([
            'board_id' => $workflowBoard->id,
            'board_list_id' => $draftList->id,
            'title' => 'Workflow Card',
            'created_by' => $user->id,
            'status' => 'todo',
            'position' => 0,
        ]);
        $card->assignees()->attach($user->id);

        $checklist = CardChecklist::create([
            'card_id' => $card->id,
            'title' => 'Tasks',
            'position' => 1,
        ]);
        CardChecklistItem::create([
            'checklist_id' => $checklist->id,
            'content' => 'Item 1',
            'is_completed' => false,
            'position' => 1,
        ]);

        // Attempt commenting "Team approved" (which normally moves from Draft to Production Team)
        $resp1 = $this->actingAs($user)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'Team approved',
        ]);
        $resp1->assertStatus(422);
        $resp1->assertJsonFragment(['checklist_incomplete' => true]);

        // Ensure card did not move
        $card->refresh();
        $this->assertEquals($draftList->id, $card->board_list_id);

        // Attempt commenting "Blocked"
        $resp2 = $this->actingAs($user)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'Blocked',
        ]);
        $resp2->assertStatus(422);
        $resp2->assertJsonFragment(['checklist_incomplete' => true]);
        $card->refresh();
        $this->assertEquals($draftList->id, $card->board_list_id);

        // Attempt commenting "Approved"
        $resp3 = $this->actingAs($user)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'Approved',
        ]);
        $resp3->assertStatus(422);
        $resp3->assertJsonFragment(['checklist_incomplete' => true]);
        $card->refresh();
        $this->assertEquals($draftList->id, $card->board_list_id);
    }

    public function test_card_with_incomplete_checklist_allows_regular_discussion_comment(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true, 'team_role' => 'Digital Team']);
        $workspace = Workspace::create(['name' => 'Marketing', 'owner_id' => $user->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Team A Planning Board', 'created_by' => $user->id]);
        $list1 = BoardList::create(['board_id' => $board->id, 'name' => 'Week 1', 'position' => 1]);

        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list1->id,
            'title' => 'Discussion Card',
            'created_by' => $user->id,
            'status' => 'todo',
            'position' => 0,
        ]);

        $checklist = CardChecklist::create([
            'card_id' => $card->id,
            'title' => 'Tasks',
            'position' => 1,
        ]);
        CardChecklistItem::create([
            'checklist_id' => $checklist->id,
            'content' => 'Incomplete task',
            'is_completed' => false,
            'position' => 1,
        ]);

        // Regular comment without automation keyword should be posted successfully
        $response = $this->actingAs($user)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'Please update the client logos in the final frame.',
        ]);

        $response->assertCreated();
        $this->assertEquals(1, $card->comments()->count());
        $card->refresh();
        $this->assertEquals($list1->id, $card->board_list_id);
    }

    public function test_toggling_checklist_to_100_percent_does_not_auto_copy_to_workflow(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true, 'team_role' => 'Digital Team']);
        $workspace = Workspace::create(['name' => 'Marketing', 'owner_id' => $user->id, 'is_active' => true]);
        $planningBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'Team A Planning Board - Oct 2026', 'created_by' => $user->id]);
        $workflowBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board - Oct 2026', 'created_by' => $user->id]);
        
        $pList = BoardList::create(['board_id' => $planningBoard->id, 'name' => 'Week 1', 'position' => 1]);
        $wList = BoardList::create(['board_id' => $workflowBoard->id, 'name' => 'Draft', 'position' => 1]);

        $card = Card::create([
            'board_id' => $planningBoard->id,
            'board_list_id' => $pList->id,
            'title' => 'Auto-copy prevention test card',
            'created_by' => $user->id,
            'status' => 'todo',
            'position' => 0,
        ]);
        $card->assignees()->attach($user->id);

        $checklist = CardChecklist::create([
            'card_id' => $card->id,
            'title' => 'Checklist',
            'position' => 1,
        ]);
        $item = CardChecklistItem::create([
            'checklist_id' => $checklist->id,
            'content' => 'Final task',
            'is_completed' => false,
            'position' => 1,
        ]);

        // Toggle the item to completed (now 100% full)
        $toggleResp = $this->actingAs($user)->patchJson(route('boards.cards.checklists.items.toggle', [$card, $checklist, $item]));
        $toggleResp->assertOk();
        $toggleResp->assertJson(['percent' => 100]);

        // It must NOT auto-copy to the workflow board
        $this->assertEquals(0, Card::where('board_id', $workflowBoard->id)->count(), 'Card must NOT auto-copy to workflow board when checklist reaches 100%');
        $card->refresh();
        $this->assertEquals($pList->id, $card->board_list_id);

        // Now that it is 100% full, the user is ALLOWED to comment "Ready" to copy the card to workflow board
        $commentResp = $this->actingAs($user)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'Ready',
        ]);
        $commentResp->assertCreated();

        // Now it SHOULD be copied to the workflow board
        $this->assertEquals(1, Card::where('board_id', $workflowBoard->id)->count(), 'Card should now be copied to workflow board upon Ready comment');
    }
}
