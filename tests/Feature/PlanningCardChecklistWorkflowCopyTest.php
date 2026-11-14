<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardChecklist;
use App\Models\CardChecklistItem;
use App\Models\User;
use App\Models\Workspace;
use App\Services\BoardWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlanningCardChecklistWorkflowCopyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $dara;
    protected User $kim;
    protected User $memberA;
    protected User $memberB;
    protected Workspace $workspace;
    protected Board $planningBoard;
    protected Board $workflowBoardA;
    protected Board $workflowBoardB;
    protected BoardList $planningList;
    protected BoardList $workflowADraft;
    protected BoardList $workflowBDraft;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin-digital', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['name' => 'Super Admin', 'username' => 'superadmin', 'is_active' => true]);
        $this->admin->assignRole('super-admin');

        $this->dara = User::factory()->create(['name' => 'Dara (Head - Team A)', 'username' => 'dara', 'team' => 'A', 'is_active' => true]);
        $this->kim = User::factory()->create(['name' => 'KimOun (Head - Team B)', 'username' => 'kim', 'team' => 'B', 'is_active' => true]);
        $this->memberA = User::factory()->create(['name' => 'Member A', 'username' => 'membera', 'team' => 'A', 'is_active' => true]);
        $this->memberB = User::factory()->create(['name' => 'Member B', 'username' => 'memberb', 'team' => 'B', 'is_active' => true]);

        $this->workspace = Workspace::create([
            'name' => 'Digital Department @ KiuQ',
            'owner_id' => $this->admin->id,
            'is_active' => true,
        ]);

        $this->planningBoard = Board::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Planning board – November 2026',
            'created_by' => $this->admin->id,
        ]);
        $this->planningList = $this->planningBoard->lists()->create(['name' => 'Urgent / Priority', 'position' => 0]);

        $this->workflowBoardA = Board::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Workflow board Team A – November 2026',
            'created_by' => $this->admin->id,
        ]);
        $this->workflowADraft = $this->workflowBoardA->lists()->create(['name' => 'Draft', 'position' => 0]);

        $this->workflowBoardB = Board::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Workflow board Team B – November 2026',
            'created_by' => $this->admin->id,
        ]);
        $this->workflowBDraft = $this->workflowBoardB->lists()->create(['name' => 'Draft', 'position' => 0]);
        $this->workflowBoardB->lists()->create(['name' => 'Production Team B', 'position' => 1]);
    }

    public function test_planning_card_without_smm_label_assigned_by_kim_copies_to_team_b_workflow_on_100_percent_checklist(): void
    {
        $card = Card::create([
            'board_id' => $this->planningBoard->id,
            'board_list_id' => $this->planningList->id,
            'title' => 'PRJ: Kuvuo 2.5 Content',
            'created_by' => $this->kim->id, // Assigned by Kim
        ]);

        // Assign mixed team members (Member A and Kim)
        $card->assignees()->attach([$this->memberA->id, $this->kim->id]);

        // Assert that detectTeam detects 'B' because Kim is creator
        $this->assertSame('B', $card->detectTeam());
        $this->assertFalse($card->isBothTeams());

        // Create checklist with 2 items
        $checklist = CardChecklist::create([
            'card_id' => $card->id,
            'title' => 'Checklist',
        ]);
        $item1 = CardChecklistItem::create([
            'checklist_id' => $checklist->id,
            'content' => 'Item 1',
            'is_completed' => true,
        ]);
        $item2 = CardChecklistItem::create([
            'checklist_id' => $checklist->id,
            'content' => 'Item 2',
            'is_completed' => false,
        ]);

        $service = app(BoardWorkflowService::class);

        // Incomplete checklist should NOT copy
        $this->assertNull($service->autoCopyCompletedPlanningCardToWorkflow($card->fresh()));
        $this->assertDatabaseMissing('cards', [
            'board_id' => $this->workflowBoardB->id,
            'title' => 'PRJ: Kuvuo 2.5 Content',
        ]);

        // Complete the second item
        $item2->update(['is_completed' => true]);

        // Now checklist is 100% full: auto-copy must route to Workflow Board Team B Draft list
        $copiedCard = $service->autoCopyCompletedPlanningCardToWorkflow($card->fresh());

        $this->assertNotNull($copiedCard);
        $this->assertSame($this->workflowBoardB->id, $copiedCard->board_id);
        $this->assertSame($this->workflowBDraft->id, $copiedCard->board_list_id);
        $this->assertSame('B', $copiedCard->team);

        // Calling it again should NOT duplicate
        $again = $service->autoCopyCompletedPlanningCardToWorkflow($card->fresh());
        $this->assertSame($copiedCard->id, $again->id);
        $this->assertSame(1, Card::where('board_id', $this->workflowBoardB->id)->where('title', 'PRJ: Kuvuo 2.5 Content')->count());
    }

    public function test_planning_card_without_smm_label_assigned_by_dara_copies_to_team_a_workflow_on_100_percent_checklist(): void
    {
        $card = Card::create([
            'board_id' => $this->planningBoard->id,
            'board_list_id' => $this->planningList->id,
            'title' => 'PRJ: Team A Project',
            'created_by' => $this->dara->id, // Assigned by Dara
        ]);

        $card->assignees()->attach([$this->memberB->id, $this->dara->id]);

        // Dara is creator -> Team A
        $this->assertSame('A', $card->detectTeam());
        $this->assertFalse($card->isBothTeams());

        $checklist = CardChecklist::create([
            'card_id' => $card->id,
            'title' => 'Checklist',
        ]);
        CardChecklistItem::create([
            'checklist_id' => $checklist->id,
            'content' => 'Item 1',
            'is_completed' => true,
        ]);

        $service = app(BoardWorkflowService::class);
        $copiedCard = $service->autoCopyCompletedPlanningCardToWorkflow($card->fresh());

        $this->assertNotNull($copiedCard);
        $this->assertSame($this->workflowBoardA->id, $copiedCard->board_id);
        $this->assertSame($this->workflowADraft->id, $copiedCard->board_list_id);
        $this->assertSame('A', $copiedCard->team);
    }

    public function test_moved_card_on_workflow_board_does_not_get_re_copied(): void
    {
        $card = Card::create([
            'board_id' => $this->planningBoard->id,
            'board_list_id' => $this->planningList->id,
            'title' => 'PRJ: Idempotency Check',
            'created_by' => $this->kim->id,
        ]);

        $checklist = CardChecklist::create([
            'card_id' => $card->id,
            'title' => 'Checklist',
        ]);
        CardChecklistItem::create([
            'checklist_id' => $checklist->id,
            'content' => 'Item 1',
            'is_completed' => true,
        ]);

        $service = app(BoardWorkflowService::class);
        $copiedCard = $service->autoCopyCompletedPlanningCardToWorkflow($card->fresh());
        $this->assertNotNull($copiedCard);

        // Move the copied card to "Production Team B" list
        $prodList = $this->workflowBoardB->lists()->where('name', 'Production Team B')->first();
        $copiedCard->update(['board_list_id' => $prodList->id]);

        // Trigger again (e.g. user toggles another checklist or edits)
        $result = $service->autoCopyCompletedPlanningCardToWorkflow($card->fresh());
        $this->assertSame($copiedCard->id, $result->id);

        // Must still be only 1 card on the target board
        $this->assertSame(1, Card::where('board_id', $this->workflowBoardB->id)->where('title', 'PRJ: Idempotency Check')->count());
    }

    public function test_deleting_copied_card_leaves_original_in_planning_intact(): void
    {
        $card = Card::create([
            'board_id' => $this->planningBoard->id,
            'board_list_id' => $this->planningList->id,
            'title' => 'PRJ: Independence Test',
            'created_by' => $this->kim->id,
        ]);

        $checklist = CardChecklist::create([
            'card_id' => $card->id,
            'title' => 'Checklist',
        ]);
        CardChecklistItem::create([
            'checklist_id' => $checklist->id,
            'content' => 'Item 1',
            'is_completed' => true,
        ]);

        $service = app(BoardWorkflowService::class);
        $copiedCard = $service->autoCopyCompletedPlanningCardToWorkflow($card->fresh());
        $this->assertNotNull($copiedCard);

        // Delete the copied card via CardController@destroy
        $response = $this->actingAs($this->admin)->deleteJson(route('boards.cards.destroy', $copiedCard->id));
        $response->assertOk();

        // The copied card is deleted
        $this->assertTrue($copiedCard->fresh()->trashed());

        // The original planning card MUST STILL STAY ACTIVE!
        $this->assertFalse($card->fresh()->trashed());
        $this->assertNotNull(Card::find($card->id));

        // Now delete the original card (the last one)
        $response2 = $this->actingAs($this->admin)->deleteJson(route('boards.cards.destroy', $card->id));
        $response2->assertOk();
        $this->assertTrue($card->fresh()->trashed());
    }

    public function test_archiving_copied_card_leaves_original_in_planning_unarchived(): void
    {
        $card = Card::create([
            'board_id' => $this->planningBoard->id,
            'board_list_id' => $this->planningList->id,
            'title' => 'PRJ: Archive Independence Test',
            'created_by' => $this->kim->id,
        ]);

        $checklist = CardChecklist::create([
            'card_id' => $card->id,
            'title' => 'Checklist',
        ]);
        CardChecklistItem::create([
            'checklist_id' => $checklist->id,
            'content' => 'Item 1',
            'is_completed' => true,
        ]);

        $service = app(BoardWorkflowService::class);
        $copiedCard = $service->autoCopyCompletedPlanningCardToWorkflow($card->fresh());
        $this->assertNotNull($copiedCard);

        // Archive the copied card
        $response = $this->actingAs($this->admin)->patchJson(route('boards.cards.update', $copiedCard->id), [
            'is_archived' => true,
        ]);
        $response->assertOk();

        // The copied card is archived
        $this->assertTrue((bool)$copiedCard->fresh()->is_archived);

        // The original planning card MUST NOT be archived and still stay!
        $this->assertFalse((bool)$card->fresh()->is_archived);
    }
}

