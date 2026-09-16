<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlanningBoardWeekSyncTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $smmBoard;
    protected $teamBoard;
    protected $workflowBoard;
    protected $smmWeek1;
    protected $smmWeek2;
    protected $teamWeek1;
    protected $teamWeek2;
    protected $workflowDraft;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole('super-admin');

        $workspace = Workspace::create([
            'name' => 'SMM Workspace',
            'owner_id' => $this->user->id,
            'is_active' => true,
        ]);

        $teamWorkspace = Workspace::create([
            'name' => 'Graphic Workspace',
            'owner_id' => $this->user->id,
            'is_active' => true,
        ]);

        // SMM Planning Board
        $this->smmBoard = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'SMM Planning Board - September 2026',
            'created_by' => $this->user->id,
            'type' => 'smm',
        ]);
        $this->smmWeek1 = BoardList::create(['board_id' => $this->smmBoard->id, 'name' => 'Week 1', 'position' => 0]);
        $this->smmWeek2 = BoardList::create(['board_id' => $this->smmBoard->id, 'name' => 'Week 2', 'position' => 1]);

        // Team Planning Board (e.g. Graphic Planning Board)
        $this->teamBoard = Board::create([
            'workspace_id' => $teamWorkspace->id,
            'name' => 'Graphic Planning Board - September 2026',
            'created_by' => $this->user->id,
            'type' => 'general',
        ]);
        $this->teamWeek1 = BoardList::create(['board_id' => $this->teamBoard->id, 'name' => 'Week 1 (01 - 07 Sep)', 'position' => 0]);
        $this->teamWeek2 = BoardList::create(['board_id' => $this->teamBoard->id, 'name' => 'Week 2 (08 - 14 Sep)', 'position' => 1]);

        // Team Workflow Board (should NEVER be moved to week lists)
        $this->workflowBoard = Board::create([
            'workspace_id' => $teamWorkspace->id,
            'name' => 'Graphic Workflow board - September 2026',
            'created_by' => $this->user->id,
            'type' => 'general',
        ]);
        $this->workflowDraft = BoardList::create(['board_id' => $this->workflowBoard->id, 'name' => 'Draft', 'position' => 0]);
    }

    public function test_moving_card_from_week_1_to_week_2_on_smm_board_syncs_to_team_planning_board(): void
    {
        $syncGroupId = (string) Str::uuid();

        // Create card on SMM Planning Board in Week 1
        $smmCard = Card::create([
            'board_id' => $this->smmBoard->id,
            'board_list_id' => $this->smmWeek1->id,
            'title' => 'Product Video Post',
            'sync_group_id' => $syncGroupId,
            'created_by' => $this->user->id,
            'position' => 0,
        ]);

        // Create twin card on Team Planning Board in Week 1
        $teamCard = Card::create([
            'board_id' => $this->teamBoard->id,
            'board_list_id' => $this->teamWeek1->id,
            'title' => 'Product Video Post',
            'sync_group_id' => $syncGroupId,
            'created_by' => $this->user->id,
            'position' => 0,
        ]);

        // Create twin card on Workflow board in Draft
        $workflowCard = Card::create([
            'board_id' => $this->workflowBoard->id,
            'board_list_id' => $this->workflowDraft->id,
            'title' => 'Product Video Post',
            'sync_group_id' => $syncGroupId,
            'created_by' => $this->user->id,
            'position' => 0,
        ]);

        // Move the SMM card to Week 2
        $response = $this->actingAs($this->user)->postJson(route('boards.cards.move', $smmCard), [
            'board_list_id' => $this->smmWeek2->id,
            'position' => 0,
        ]);

        $response->assertOk();

        // Verify SMM card is in Week 2
        $this->assertSame($this->smmWeek2->id, $smmCard->fresh()->board_list_id);

        // Verify Team card is also moved to Week 2 (matching Week 2 (08 - 14 Sep))
        $this->assertSame($this->teamWeek2->id, $teamCard->fresh()->board_list_id);

        // Verify Team card received a system comment explaining the sync
        $this->assertTrue(
            $teamCard->comments()->where('is_system', true)->where('content', 'like', '%Week 2%')->exists()
        );

        // Verify Workflow card was NOT moved (remains in Draft)
        $this->assertSame($this->workflowDraft->id, $workflowCard->fresh()->board_list_id);
    }

    public function test_bulk_moving_cards_to_week_2_syncs_team_planning_board(): void
    {
        $syncGroupId = (string) Str::uuid();

        $smmCard = Card::create([
            'board_id' => $this->smmBoard->id,
            'board_list_id' => $this->smmWeek1->id,
            'title' => 'Bulk SMM Card',
            'sync_group_id' => $syncGroupId,
            'created_by' => $this->user->id,
            'position' => 0,
        ]);

        $teamCard = Card::create([
            'board_id' => $this->teamBoard->id,
            'board_list_id' => $this->teamWeek1->id,
            'title' => 'Bulk SMM Card',
            'sync_group_id' => $syncGroupId,
            'created_by' => $this->user->id,
            'position' => 0,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('boards.cards.bulkAction', $this->smmBoard), [
            'card_ids' => [$smmCard->id],
            'action' => 'move',
            'target_board_id' => $this->smmBoard->id,
            'target_list_id' => $this->smmWeek2->id,
        ]);

        $response->assertOk();

        $this->assertSame($this->smmWeek2->id, $smmCard->fresh()->board_list_id);
        $this->assertSame($this->teamWeek2->id, $teamCard->fresh()->board_list_id);
    }

    public function test_sync_planning_weeks_command_moves_out_of_sync_cards(): void
    {
        $syncGroupId = (string) Str::uuid();

        // SMM card is already in Week 2
        $smmCard = Card::create([
            'board_id' => $this->smmBoard->id,
            'board_list_id' => $this->smmWeek2->id,
            'title' => 'Already Moved SMM Card',
            'sync_group_id' => $syncGroupId,
            'created_by' => $this->user->id,
            'position' => 0,
        ]);

        // Team card is still in Week 1
        $teamCard = Card::create([
            'board_id' => $this->teamBoard->id,
            'board_list_id' => $this->teamWeek1->id,
            'title' => 'Already Moved SMM Card',
            'sync_group_id' => $syncGroupId,
            'created_by' => $this->user->id,
            'position' => 0,
        ]);

        $this->assertSame($this->teamWeek1->id, $teamCard->board_list_id);

        // Run artisan command
        $exitCode = Artisan::call('cards:sync-planning-weeks', ['--week' => '2']);
        $this->assertSame(0, $exitCode);

        // Team card must now be in Week 2
        $this->assertSame($this->teamWeek2->id, $teamCard->fresh()->board_list_id);
    }
}
