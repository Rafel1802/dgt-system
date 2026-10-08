<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardAutomation;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CommentAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    }

    public function test_supervisor_rejected_comment_moves_card(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true, 'team_role' => 'Supervisor']);
        $workspace = Workspace::create(['name' => 'WS', 'owner_id' => $user->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board - Sep 2026', 'created_by' => $user->id]);
        $list1 = BoardList::create(['board_id' => $board->id, 'name' => 'Supervisor Review (Ms. Somalika)', 'position' => 1]);
        $list2 = BoardList::create(['board_id' => $board->id, 'name' => 'Block/Waiting', 'position' => 2]);
        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list1->id,
            'title' => 'Test Card',
            'created_by' => $user->id,
            'status' => 'todo',
            'position' => 0
        ]);

        BoardAutomation::create([
            'board_id' => $board->id,
            'trigger_type' => 'keyword',
            'trigger_word' => 'Rejected',
            'trigger_board_id' => $board->id,
            'trigger_list_id' => $list1->id,
            'target_board_id' => $board->id,
            'target_list_id' => $list2->id,
            'action_type' => 'move'
        ]);

        $response = $this->actingAs($user)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'Rejected'
        ]);

        $response->assertCreated();
        $card->refresh();
        $this->assertEquals($list2->id, $card->board_list_id);
    }

    public function test_supervisor_blocked_quick_reply_moves_card_even_if_rule_says_rejected(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true, 'team_role' => 'Supervisor']);
        $workspace = Workspace::create(['name' => 'WS', 'owner_id' => $user->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board - Sep 2026', 'created_by' => $user->id]);
        $list1 = BoardList::create(['board_id' => $board->id, 'name' => 'Supervisor Review (Ms. Somalika)', 'position' => 1]);
        $list2 = BoardList::create(['board_id' => $board->id, 'name' => 'Block/Waiting', 'position' => 2]);
        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list1->id,
            'title' => 'Test Card',
            'created_by' => $user->id,
            'status' => 'todo',
            'position' => 0
        ]);

        BoardAutomation::create([
            'board_id' => $board->id,
            'trigger_type' => 'keyword',
            'trigger_word' => 'Rejected',
            'trigger_board_id' => $board->id,
            'trigger_list_id' => $list1->id,
            'target_board_id' => $board->id,
            'target_list_id' => $list2->id,
            'action_type' => 'move'
        ]);

        // User clicks the UI quick reply "Blocked"
        $response = $this->actingAs($user)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'Blocked'
        ]);

        $response->assertCreated();
        $card->refresh();
        $this->assertEquals($list2->id, $card->board_list_id, 'Card should move to Block/Waiting when user comments Blocked');
    }

    public function test_caption_ready_moves_card_to_final_captions(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true]);
        $workspace = Workspace::create(['name' => 'SMM WS', 'owner_id' => $user->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'SMM Planning Board - September 2026', 'type' => 'smm', 'created_by' => $user->id]);
        $week1 = BoardList::create(['board_id' => $board->id, 'name' => 'Week 1', 'position' => 1]);
        $finalCaptions = BoardList::create(['board_id' => $board->id, 'name' => 'Final Captions', 'position' => 2]);
        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $week1->id,
            'title' => 'SMM Card 1',
            'created_by' => $user->id,
            'status' => 'todo',
            'position' => 0
        ]);
        $card->assignees()->attach($user->id);

        $response = $this->actingAs($user)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'caption ready'
        ]);

        $response->assertCreated();
        $card->refresh();
        $this->assertEquals($finalCaptions->id, $card->board_list_id, 'Card should move to Final Captions list');
    }

    public function test_caption_ready_moves_to_final_captions_even_when_ready_rule_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true]);
        $workspace = Workspace::create(['name' => 'SMM WS', 'owner_id' => $user->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'SMM Planning Board - September 2026', 'type' => 'smm', 'created_by' => $user->id]);
        $workflowBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board - September 2026', 'created_by' => $user->id]);
        $week1 = BoardList::create(['board_id' => $board->id, 'name' => 'Week 1', 'position' => 1]);
        $finalCaptions = BoardList::create(['board_id' => $board->id, 'name' => 'Final Captions', 'position' => 2]);
        $draftList = BoardList::create(['board_id' => $workflowBoard->id, 'name' => 'Draft', 'position' => 1]);

        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $week1->id,
            'title' => 'SMM Card 1',
            'created_by' => $user->id,
            'status' => 'todo',
            'position' => 0
        ]);
        $card->assignees()->attach($user->id);

        // Seeder adds a "ready" rule to copy to workflow board
        BoardAutomation::create([
            'board_id' => $board->id,
            'trigger_type' => 'keyword',
            'trigger_word' => 'ready',
            'trigger_board_id' => $board->id,
            'target_board_id' => $workflowBoard->id,
            'target_list_id' => $draftList->id,
            'action_type' => 'copy'
        ]);

        $response = $this->actingAs($user)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'caption ready'
        ]);

        $response->assertCreated();
        $card->refresh();
        $this->assertEquals($finalCaptions->id, $card->board_list_id, 'Card MUST move to Final Captions even if a ready automation rule matched and copied the card');
    }

    public function test_ready_comment_does_not_create_duplicate_cards(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true]);
        $workspace = Workspace::create(['name' => 'WS', 'owner_id' => $user->id, 'is_active' => true]);
        $planningBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'Graphic Planning - September 2026', 'created_by' => $user->id]);
        $workflowBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'Graphic Workflow - September 2026', 'created_by' => $user->id]);
        $pList = BoardList::create(['board_id' => $planningBoard->id, 'name' => 'Week 1', 'position' => 1]);
        $draftList = BoardList::create(['board_id' => $workflowBoard->id, 'name' => 'Draft', 'position' => 1]);

        $card = Card::create([
            'board_id' => $planningBoard->id,
            'board_list_id' => $pList->id,
            'title' => 'Graphic Task 1',
            'created_by' => $user->id,
            'status' => 'todo',
            'position' => 0
        ]);
        $card->assignees()->attach($user->id);

        BoardAutomation::create([
            'board_id' => $planningBoard->id,
            'trigger_type' => 'keyword',
            'trigger_word' => 'Ready',
            'trigger_board_id' => $planningBoard->id,
            'target_board_id' => $workflowBoard->id,
            'target_list_id' => $draftList->id,
            'action_type' => 'copy'
        ]);

        // First comment "Ready"
        $this->actingAs($user)->postJson(route('boards.cards.comments.store', $card), ['body' => 'Ready']);

        $copies = Card::where('board_id', $workflowBoard->id)->get();
        $this->assertCount(1, $copies);

        // Second comment "Ready" on the same planning card
        $this->actingAs($user)->postJson(route('boards.cards.comments.store', $card), ['body' => 'Ready']);

        $copiesAfter = Card::where('board_id', $workflowBoard->id)->get();
        $this->assertCount(1, $copiesAfter, 'Should not create duplicate cards in workflow board on subsequent Ready comments');
    }

    public function test_workflow_board_comment_fallback_when_name_lacks_the_word_board(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true, 'team_role' => 'QC']);
        $workspace = Workspace::create(['name' => 'WS', 'owner_id' => $user->id, 'is_active' => true]);
        // Note: Name is "Graphic Workflow - September 2026" (WITHOUT the word "board")
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Graphic Workflow - September 2026', 'created_by' => $user->id]);
        $qcList = BoardList::create(['board_id' => $board->id, 'name' => 'QC Review', 'position' => 1]);
        $supList = BoardList::create(['board_id' => $board->id, 'name' => 'Supervisor Review', 'position' => 2]);

        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $qcList->id,
            'title' => 'Banner Design',
            'created_by' => $user->id,
            'status' => 'todo',
            'position' => 0
        ]);

        // Comment "QC approved" without explicit BoardAutomation in DB (relying on BoardWorkflowService fallback)
        $this->actingAs($user)->postJson(route('boards.cards.comments.store', $card), ['body' => 'QC approved']);

        $card->refresh();
        $this->assertEquals($supList->id, $card->board_list_id, 'Workflow comment fallback should handle boards named without the word "board"');
    }

    public function test_admin_digital_can_trigger_supervisor_approval(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true, 'team_role' => null]);
        $user->assignRole('admin-digital');
        $workspace = Workspace::create(['name' => 'WS', 'owner_id' => $user->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board - Sep 2026', 'created_by' => $user->id]);
        $list1 = BoardList::create(['board_id' => $board->id, 'name' => 'Supervisor Review (Ms. Somalika)', 'position' => 1]);
        $list2 = BoardList::create(['board_id' => $board->id, 'name' => 'Approved', 'position' => 2]);
        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list1->id,
            'title' => 'Test Card',
            'created_by' => $user->id,
            'status' => 'todo',
            'position' => 0
        ]);

        BoardAutomation::create([
            'board_id' => $board->id,
            'trigger_type' => 'keyword',
            'trigger_word' => 'Approved',
            'trigger_board_id' => $board->id,
            'trigger_list_id' => $list1->id,
            'target_board_id' => $board->id,
            'target_list_id' => $list2->id,
            'action_type' => 'move'
        ]);

        $response = $this->actingAs($user)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'Approved'
        ]);

        $response->assertCreated();
        $card->refresh();
        $this->assertEquals($list2->id, $card->board_list_id, 'Admin digital user should be able to trigger supervisor approval automation');
    }

    public function test_smm_qc_approved_comment_updates_status(): void
    {
        Notification::fake();
        $qcUser = User::factory()->create(['is_active' => true, 'team_role' => 'QC']);
        $workspace = Workspace::create(['name' => 'SMM WS', 'owner_id' => $qcUser->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'SMM Planning Board - September 2026', 'type' => 'smm', 'created_by' => $qcUser->id]);
        $finalCaptions = BoardList::create(['board_id' => $board->id, 'name' => 'Final Captions', 'position' => 1]);

        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $finalCaptions->id,
            'title' => 'SMM Card 1',
            'created_by' => $qcUser->id,
            'status' => 'todo',
            'position' => 0
        ]);

        // QC comments "QC approved SMM"
        $response = $this->actingAs($qcUser)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'QC approved SMM'
        ]);

        $response->assertCreated();
        $card->refresh();
        $this->assertEquals('approved', $card->status?->value ?? (string)$card->status, 'SMM Card in Final Captions should be marked approved when QC comments QC approved SMM');
    }

    public function test_planning_board_blocked_comment_moves_to_block_waiting(): void
    {
        Notification::fake();
        $supUser = User::factory()->create(['is_active' => true, 'team_role' => 'Supervisor']);
        $workspace = Workspace::create(['name' => 'SMM WS', 'owner_id' => $supUser->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'SMM Planning Board - September 2026', 'type' => 'smm', 'created_by' => $supUser->id]);
        $week1 = BoardList::create(['board_id' => $board->id, 'name' => 'Week 1', 'position' => 1]);
        $blockList = BoardList::create(['board_id' => $board->id, 'name' => 'Block/Waiting', 'position' => 2]);

        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $week1->id,
            'title' => 'SMM Card 1',
            'created_by' => $supUser->id,
            'status' => 'todo',
            'position' => 0
        ]);

        $response = $this->actingAs($supUser)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'Blocked'
        ]);

        $response->assertCreated();
        $card->refresh();
        $this->assertEquals($blockList->id, $card->board_list_id, 'SMM Card should move to Block/Waiting when Supervisor comments Blocked');
    }

    public function test_add_system_comment_fallback_when_unauthenticated(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true]);
        $workspace = Workspace::create(['name' => 'WS', 'owner_id' => $user->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Activity Board', 'created_by' => $user->id]);
        $list = BoardList::create(['board_id' => $board->id, 'name' => 'Draft', 'position' => 1]);
        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Background Task Card',
            'created_by' => $user->id,
            'status' => 'todo',
            'position' => 0
        ]);

        // Explicitly logout
        auth()->logout();

        app(\App\Http\Controllers\Board\CardController::class)->addSystemComment($card, 'Automated system audit note');

        $this->assertDatabaseHas('card_comments', [
            'card_id' => $card->id,
            'content' => 'Automated system audit note',
            'is_system' => true,
        ]);
    }

    public function test_bulk_comment_triggers_automations(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true, 'team_role' => 'Supervisor']);
        $workspace = Workspace::create(['name' => 'WS', 'owner_id' => $user->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board - Sep 2026', 'created_by' => $user->id]);
        $list1 = BoardList::create(['board_id' => $board->id, 'name' => 'Supervisor Review', 'position' => 1]);
        $list2 = BoardList::create(['board_id' => $board->id, 'name' => 'Block/Waiting', 'position' => 2]);

        $card1 = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list1->id,
            'title' => 'Card 1',
            'created_by' => $user->id,
            'status' => 'todo',
            'position' => 0
        ]);
        $card2 = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list1->id,
            'title' => 'Card 2',
            'created_by' => $user->id,
            'status' => 'todo',
            'position' => 1
        ]);

        BoardAutomation::create([
            'board_id' => $board->id,
            'trigger_type' => 'keyword',
            'trigger_word' => 'Rejected',
            'trigger_board_id' => $board->id,
            'trigger_list_id' => $list1->id,
            'target_board_id' => $board->id,
            'target_list_id' => $list2->id,
            'action_type' => 'move'
        ]);

        $response = $this->actingAs($user)->postJson(route('boards.cards.bulkAction', $board), [
            'action' => 'comment',
            'card_ids' => [$card1->id, $card2->id],
            'comment' => 'Blocked'
        ]);

        $response->assertOk();
        $card1->refresh();
        $card2->refresh();
        $this->assertEquals($list2->id, $card1->board_list_id);
        $this->assertEquals($list2->id, $card2->board_list_id);
    }

    public function test_supervisor_blocked_from_draft_or_qc_moves_card_to_block_waiting(): void
    {
        Notification::fake();
        $supUser = User::factory()->create(['is_active' => true, 'team_role' => 'Supervisor']);
        $workspace = Workspace::create(['name' => 'WS', 'owner_id' => $supUser->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Graphic Workflow board - September 2026', 'created_by' => $supUser->id]);
        $draftList = BoardList::create(['board_id' => $board->id, 'name' => 'Draft', 'position' => 1]);
        $qcList = BoardList::create(['board_id' => $board->id, 'name' => 'Text (QC) Review (Mr. Dara)', 'position' => 2]);
        $blockList = BoardList::create(['board_id' => $board->id, 'name' => 'Block/Waiting', 'position' => 3]);

        // Card in Draft
        $card1 = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $draftList->id,
            'title' => 'Draft Card',
            'created_by' => $supUser->id,
            'status' => 'todo',
            'position' => 0
        ]);

        // Card in QC
        $card2 = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $qcList->id,
            'title' => 'QC Card',
            'created_by' => $supUser->id,
            'status' => 'todo',
            'position' => 1
        ]);

        // Supervisor comments "Blocked" on card in Draft
        $this->actingAs($supUser)->postJson(route('boards.cards.comments.store', $card1), [
            'body' => 'Blocked'
        ])->assertCreated();

        // Supervisor comments "Blocked" on card in QC
        $this->actingAs($supUser)->postJson(route('boards.cards.comments.store', $card2), [
            'body' => 'Blocked'
        ])->assertCreated();

        $card1->refresh();
        $card2->refresh();
        $this->assertEquals($blockList->id, $card1->board_list_id, 'Draft card should move to Block/Waiting when Supervisor comments Blocked');
        $this->assertEquals($blockList->id, $card2->board_list_id, 'QC card should move to Block/Waiting when Supervisor comments Blocked');
    }

    public function test_comment_ready_on_team_planning_board_copies_card_to_workflow_draft_and_syncs(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true]);
        $workspace = Workspace::create(['name' => 'GraphicTeam@KiuQ', 'owner_id' => $user->id, 'is_active' => true]);
        
        $planningBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'Graphic Planning board - September 2026', 'created_by' => $user->id]);
        $planningWeek1 = BoardList::create(['board_id' => $planningBoard->id, 'name' => 'Week 1', 'position' => 1]);

        $workflowBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'Graphic Workflow board - September 2026', 'created_by' => $user->id]);
        $workflowDraft = BoardList::create(['board_id' => $workflowBoard->id, 'name' => 'Draft', 'position' => 1]);

        $card = Card::create([
            'board_id' => $planningBoard->id,
            'board_list_id' => $planningWeek1->id,
            'title' => 'Poster Design Task',
            'created_by' => $user->id,
            'status' => 'todo',
            'position' => 0
        ]);
        $card->assignees()->attach($user->id);

        // User comments "Ready" on team planning board
        $this->actingAs($user)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'Ready'
        ])->assertCreated();

        $card->refresh();
        // Original card MUST remain in Week 1 on planning board (not moved!)
        $this->assertEquals($planningWeek1->id, $card->board_list_id, 'Original card must remain in Planning board Week 1');
        $this->assertNotEmpty($card->sync_group_id, 'sync_group_id must be assigned');

        // Target workflow board MUST have the copied card in Draft
        $workflowCard = Card::where('board_id', $workflowBoard->id)->first();
        $this->assertNotNull($workflowCard, 'Card should be copied to workflow board');
        $this->assertEquals($workflowDraft->id, $workflowCard->board_list_id, 'Copied card must be in Draft list');
        $this->assertEquals($card->sync_group_id, $workflowCard->sync_group_id, 'Both cards must share sync_group_id');
        $this->assertEquals('Poster Design Task', $workflowCard->title);

        // Commenting "Ready" again should NOT duplicate the card
        $this->actingAs($user)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'Ready'
        ])->assertCreated();

        $this->assertEquals(1, Card::where('board_id', $workflowBoard->id)->count(), 'Card should not be duplicated on workflow board');
    }

    public function test_comment_ready_on_smm_planning_board_copies_to_team_workflow_and_syncs(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        $smmWorkspace = Workspace::create(['name' => 'Social Media Management', 'owner_id' => $user->id, 'is_active' => true]);
        $graphicWorkspace = Workspace::create(['name' => 'GraphicTeam@KiuQ', 'owner_id' => $user->id, 'is_active' => true]);

        $smmBoard = Board::create(['workspace_id' => $smmWorkspace->id, 'name' => 'SMM Planning Board - September 2026', 'type' => 'smm', 'created_by' => $user->id]);
        $smmWeek1 = BoardList::create(['board_id' => $smmBoard->id, 'name' => 'Week 1', 'position' => 1]);

        $graphicPlanning = Board::create(['workspace_id' => $graphicWorkspace->id, 'name' => 'Graphic Planning board - September 2026', 'created_by' => $user->id]);
        $graphicWeek1 = BoardList::create(['board_id' => $graphicPlanning->id, 'name' => 'Week 1', 'position' => 1]);

        $graphicWorkflow = Board::create(['workspace_id' => $graphicWorkspace->id, 'name' => 'Graphic Workflow board - September 2026', 'created_by' => $user->id]);
        $graphicDraft = BoardList::create(['board_id' => $graphicWorkflow->id, 'name' => 'Draft', 'position' => 1]);

        $card = Card::create([
            'board_id' => $smmBoard->id,
            'board_list_id' => $smmWeek1->id,
            'title' => '[Graphic] September Launch Poster',
            'created_by' => $user->id,
            'status' => 'todo',
            'position' => 0
        ]);

        // Comment "Ready" on SMM Planning Board
        $this->actingAs($user)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'Ready'
        ])->assertCreated();

        $card->refresh();
        $this->assertNotEmpty($card->sync_group_id, 'SMM Card must have sync_group_id');
        $this->assertEquals($smmWeek1->id, $card->board_list_id, 'Original card stays in SMM Week 1');

        // Check copy in Graphic Workflow Board Draft
        $workflowCard = Card::where('board_id', $graphicWorkflow->id)->first();
        $this->assertNotNull($workflowCard, 'Card must be copied to Graphic Workflow Board');
        $this->assertEquals($graphicDraft->id, $workflowCard->board_list_id, 'Workflow card must be in Draft');
        $this->assertEquals($card->sync_group_id, $workflowCard->sync_group_id, 'Workflow card shares sync_group_id with SMM card');

        // Check twin in Graphic Planning Board
        $teamPlanningCard = Card::where('board_id', $graphicPlanning->id)->first();
        $this->assertNotNull($teamPlanningCard, 'Card must also be distributed to Graphic Planning Board');
        $this->assertEquals($card->sync_group_id, $teamPlanningCard->sync_group_id, 'Team planning card shares sync_group_id');

        // Commenting Ready again does not duplicate
        $this->actingAs($user)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'Ready'
        ])->assertCreated();

        $this->assertEquals(1, Card::where('board_id', $graphicWorkflow->id)->count(), 'Workflow board must not have duplicates');
    }

    public function test_workflow_board_automations_move_card_normally(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $member = User::factory()->create(['is_active' => true, 'team_role' => 'Standard Member']);
        $head = User::factory()->create(['is_active' => true, 'team_role' => 'Head']);
        $qc = User::factory()->create(['is_active' => true, 'team_role' => 'QC']);
        $supervisor = User::factory()->create(['is_active' => true, 'team_role' => 'Supervisor']);

        $workspace = Workspace::create(['name' => 'VideoTeam@KiuQ', 'owner_id' => $admin->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Video Workflow board - September 2026', 'created_by' => $admin->id]);

        $draft = BoardList::create(['board_id' => $board->id, 'name' => 'Draft', 'position' => 1]);
        $headReview = BoardList::create(['board_id' => $board->id, 'name' => 'Head Review', 'position' => 2]);
        $qcReview = BoardList::create(['board_id' => $board->id, 'name' => 'Text (QC) Review (Mr. Dara)', 'position' => 3]);
        $supReview = BoardList::create(['board_id' => $board->id, 'name' => 'Supervisor Review (Ms. Somalika)', 'position' => 4]);
        $approved = BoardList::create(['board_id' => $board->id, 'name' => 'Approved', 'position' => 5]);

        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $draft->id,
            'title' => 'Video Editing Task',
            'created_by' => $admin->id,
            'status' => 'todo',
            'position' => 0
        ]);
        $card->assignees()->attach($member->id);

        // 1. In Draft: member comments "Team approved" -> moves normally to Head Review
        $this->actingAs($member)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'Team approved'
        ])->assertCreated();
        $card->refresh();
        $this->assertEquals($headReview->id, $card->board_list_id, 'Draft should move to Head Review on Team approved');

        // 2. In Head Review: head comments "Head approved" -> moves normally to QC Review
        $this->actingAs($head)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'Head approved'
        ])->assertCreated();
        $card->refresh();
        $this->assertEquals($qcReview->id, $card->board_list_id, 'Head Review should move to QC on Head approved');

        // 3. In QC Review: qc comments "QC approved" -> moves normally to Supervisor Review
        $this->actingAs($qc)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'QC approved'
        ])->assertCreated();
        $card->refresh();
        $this->assertEquals($supReview->id, $card->board_list_id, 'QC should move to Supervisor Review on QC approved');

        // 4. In Supervisor Review: supervisor comments "Approved" -> moves normally to Approved
        $this->actingAs($supervisor)->postJson(route('boards.cards.comments.store', $card), [
            'body' => 'Approved'
        ])->assertCreated();
        $card->refresh();
        $this->assertEquals($approved->id, $card->board_list_id, 'Supervisor Review should move to Approved on Approved comment');
        $this->assertEquals('approved', $card->status?->value ?? (string)$card->status);
    }

    public function test_production_approved_smm_moves_card_to_approved_list(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_active' => true, 'team_role' => 'Admin']);
        $admin->assignRole('super-admin');

        $workspace = Workspace::create(['name' => 'Marketing Workspace', 'owner_id' => $admin->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board - Oct 2026', 'created_by' => $admin->id]);

        $draft = BoardList::create(['board_id' => $board->id, 'name' => 'Draft', 'position' => 1]);
        $prodList = BoardList::create(['board_id' => $board->id, 'name' => 'QC Review (Mr. Dara)', 'position' => 2]);
        $digitalDept = BoardList::create(['board_id' => $board->id, 'name' => 'Digital Department', 'position' => 3]);
        $approved = BoardList::create(['board_id' => $board->id, 'name' => 'Approved', 'position' => 4]);

        $card1 = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $draft->id,
            'title' => 'SMM Campaign Post',
            'created_by' => $admin->id,
            'status' => 'todo',
            'position' => 0
        ]);

        $qcUser = User::factory()->create(['name' => 'Mr. Dara', 'username' => 'dara', 'is_active' => true, 'team_role' => 'QC']);

        // Posting "Production approved SMM" moves card directly to "Approved" list (not Digital Department)
        $response = $this->actingAs($qcUser)->postJson(route('boards.cards.comments.store', $card1), [
            'body' => 'Production approved SMM'
        ]);

        $response->assertCreated();
        $card1->refresh();
        $this->assertEquals($approved->id, $card1->board_list_id, 'Production approved SMM must move card directly to Approved list');
        $this->assertEquals('approved', $card1->status?->value ?? (string)$card1->status);

        // Verify regular "Production approved" goes to Digital Department, not confused with "Production approved SMM"
        $card2 = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $prodList->id,
            'title' => 'Standard Production Post',
            'created_by' => $admin->id,
            'status' => 'todo',
            'position' => 1
        ]);

        $response2 = $this->actingAs($qcUser)->postJson(route('boards.cards.comments.store', $card2), [
            'body' => 'Production approved'
        ]);

        $response2->assertCreated();
        $card2->refresh();
        $this->assertEquals($digitalDept->id, $card2->board_list_id, 'Standard Production approved should move to Digital Department');
    }

    public function test_dara_and_kim_personal_report_detects_production_approved_smm_without_count_confusion(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_active' => true, 'team_role' => 'Admin']);
        $admin->assignRole('super-admin');

        $workspace = Workspace::create(['name' => 'Digital Workspace', 'owner_id' => $admin->id, 'is_active' => true]);

        // Team A board (Dara) and Team B board (Kim)
        $boardA = Board::create(['workspace_id' => $workspace->id, 'name' => 'Team A Workflow board - Oct 2026', 'created_by' => $admin->id]);
        $boardB = Board::create(['workspace_id' => $workspace->id, 'name' => 'Team B Workflow board - Oct 2026', 'created_by' => $admin->id]);

        $listA_draft = BoardList::create(['board_id' => $boardA->id, 'name' => 'Draft', 'position' => 1]);
        $listA_approved = BoardList::create(['board_id' => $boardA->id, 'name' => 'Approved', 'position' => 2]);

        $listB_draft = BoardList::create(['board_id' => $boardB->id, 'name' => 'Draft', 'position' => 1]);
        $listB_approved = BoardList::create(['board_id' => $boardB->id, 'name' => 'Approved', 'position' => 2]);

        $dara = User::factory()->create(['name' => 'Mr. Dara', 'username' => 'dara', 'is_active' => true, 'team_role' => 'QC']);
        $kim = User::factory()->create(['name' => 'Mr. Kim', 'username' => 'kim', 'is_active' => true, 'team_role' => 'QC']);

        // Card on Team A
        $cardA = Card::create([
            'board_id' => $boardA->id,
            'board_list_id' => $listA_draft->id,
            'title' => 'Post by Team A Lead Dara',
            'created_by' => $admin->id,
            'status' => 'todo',
            'position' => 0
        ]);

        // Card on Team B
        $cardB = Card::create([
            'board_id' => $boardB->id,
            'board_list_id' => $listB_draft->id,
            'title' => 'Post by Team B Lead Kim',
            'created_by' => $admin->id,
            'status' => 'todo',
            'position' => 0
        ]);

        // Dara comments Production approved SMM on Card A
        $this->actingAs($dara)->postJson(route('boards.cards.comments.store', $cardA), [
            'body' => 'Production approved SMM'
        ])->assertCreated();

        // Kim comments Production approved SMM on Card B
        $this->actingAs($kim)->postJson(route('boards.cards.comments.store', $cardB), [
            'body' => 'Production approved SMM'
        ])->assertCreated();

        // 1. Dara exports personal report
        $responseDara = $this->actingAs($dara)->get(route('boards.reports.personal.export', [
            'format' => 'csv',
            'board_ids' => [$boardA->id, $boardB->id],
        ]));
        $responseDara->assertOk();
        $contentDara = $responseDara->getContent();

        // Dara's report must detect Card A and MUST NOT include Card B (no confusion with Kim)
        $this->assertStringContainsString('Post by Team A Lead Dara', $contentDara);
        $this->assertStringNotContainsString('Post by Team B Lead Kim', $contentDara);

        // 2. Kim exports personal report
        $responseKim = $this->actingAs($kim)->get(route('boards.reports.personal.export', [
            'format' => 'csv',
            'board_ids' => [$boardA->id, $boardB->id],
        ]));
        $responseKim->assertOk();
        $contentKim = $responseKim->getContent();

        // Kim's report must detect Card B and MUST NOT include Card A (no confusion with Dara)
        $this->assertStringContainsString('Post by Team B Lead Kim', $contentKim);
        $this->assertStringNotContainsString('Post by Team A Lead Dara', $contentKim);
    }

    public function test_production_approved_smm_moves_from_production_team_a_to_approved_and_counts_for_dara_and_kim(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_active' => true, 'team_role' => 'Admin']);
        $admin->assignRole('super-admin');

        $workspace = Workspace::create(['name' => 'Marketing Workspace', 'owner_id' => $admin->id, 'is_active' => true]);

        // Workflow board with explicit "Production Team A", "Production Team B", "Digital Department", and "Approved" lists
        $board = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'Production Workflow - Team A & B',
            'type' => 'workflow',
            'created_by' => $admin->id
        ]);

        $prodTeamAList = BoardList::create(['board_id' => $board->id, 'name' => 'Production Team A', 'position' => 1]);
        $prodTeamBList = BoardList::create(['board_id' => $board->id, 'name' => 'Production Team B', 'position' => 2]);
        $digitalDeptList = BoardList::create(['board_id' => $board->id, 'name' => 'Digital Department', 'position' => 3]);
        $approvedList = BoardList::create(['board_id' => $board->id, 'name' => 'Approved', 'position' => 4]);

        $dara = User::factory()->create(['name' => 'Mr. Dara', 'username' => 'dara', 'is_active' => true, 'team_role' => 'QC']);
        $kim = User::factory()->create(['name' => 'Mr. Kim', 'username' => 'kim', 'is_active' => true, 'team_role' => 'QC']);

        // Card 1 in Production Team A
        $cardA = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $prodTeamAList->id,
            'title' => 'Social Media Task A1',
            'created_by' => $admin->id,
            'status' => 'todo',
            'position' => 0
        ]);

        // Card 2 in Production Team B
        $cardB = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $prodTeamBList->id,
            'title' => 'Social Media Task B1',
            'created_by' => $admin->id,
            'status' => 'todo',
            'position' => 0
        ]);

        // 1. Mr. Dara comments "Production approved SMM" on Card A
        $responseA = $this->actingAs($dara)->postJson(route('boards.cards.comments.store', $cardA), [
            'body' => 'Production approved SMM'
        ]);
        $responseA->assertCreated();
        $this->assertTrue($responseA->json('card_moved'), 'Card A must be moved by automation');

        $cardA->refresh();
        $this->assertEquals($approvedList->id, $cardA->board_list_id, 'Card A must move from Production Team A to Approved list');
        $this->assertEquals('approved', (string)($cardA->status?->value ?? $cardA->status));
        $this->assertEquals(0, $cardA->position, 'Card A must be placed at position 0 in Approved list');

        // 2. Mr. Kim comments "Production approved SMM" on Card B
        $responseB = $this->actingAs($kim)->postJson(route('boards.cards.comments.store', $cardB), [
            'body' => 'Production approved SMM'
        ]);
        $responseB->assertCreated();
        $this->assertTrue($responseB->json('card_moved'), 'Card B must be moved by automation');

        $cardB->refresh();
        $this->assertEquals($approvedList->id, $cardB->board_list_id, 'Card B must move from Production Team B to Approved list');
        $this->assertEquals('approved', (string)($cardB->status?->value ?? $cardB->status));

        // 3. Verify personal report for Mr. Dara: Task A1 is present, Task B1 is not present
        $exportDara = $this->actingAs($dara)->get(route('boards.reports.personal.export', [
            'format' => 'csv',
            'board_ids' => [$board->id],
        ]));
        $exportDara->assertOk();
        $csvDara = $exportDara->getContent();
        $this->assertStringContainsString('Social Media Task A1', $csvDara);
        $this->assertStringNotContainsString('Social Media Task B1', $csvDara);

        // 4. Verify personal report for Mr. Kim: Task B1 is present, Task A1 is not present
        $exportKim = $this->actingAs($kim)->get(route('boards.reports.personal.export', [
            'format' => 'csv',
            'board_ids' => [$board->id],
        ]));
        $exportKim->assertOk();
        $csvKim = $exportKim->getContent();
        $this->assertStringContainsString('Social Media Task B1', $csvKim);
        $this->assertStringNotContainsString('Social Media Task A1', $csvKim);

        // 5. Verify standard "Production approved" goes to Digital Department
        $cardC = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $prodTeamAList->id,
            'title' => 'Standard Task C',
            'created_by' => $admin->id,
            'status' => 'todo',
            'position' => 0
        ]);

        $responseC = $this->actingAs($dara)->postJson(route('boards.cards.comments.store', $cardC), [
            'body' => 'Production approved'
        ]);
        $responseC->assertCreated();
        $cardC->refresh();
        $this->assertEquals($digitalDeptList->id, $cardC->board_list_id, 'Standard Production approved should move to Digital Department');
    }
}


