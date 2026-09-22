<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Label;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SupervisorDashboardApprovalQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    }

    public function test_recent_activities_is_removed_from_dashboard_for_everyone(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin-digital');

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertDontSee('Recent Activities');
        $response->assertDontSee("Live feed of what's happening across the system.");
        $response->assertDontSee('Audit Log');
    }

    public function test_supervisor_sees_2_column_layout_and_supervisor_list_tasks(): void
    {
        $supervisor = User::factory()->create([
            'is_active' => true,
            'name' => 'Ms. Somalika (Supervisor)',
            'team_role' => 'Supervisor',
        ]);
        $supervisor->assignRole('admin-digital');

        $workspace = Workspace::create([
            'name' => 'Graphic Workspace',
            'owner_id' => $supervisor->id,
        ]);
        $board = Board::create([
            'name' => 'Graphic Workflow Board',
            'slug' => 'graphic-workflow-board',
            'workspace_id' => $workspace->id,
            'created_by' => $supervisor->id,
            'is_archived' => false,
        ]);

        $approvedList = BoardList::create([
            'name' => 'Approved',
            'board_id' => $board->id,
            'position' => 1,
        ]);

        $supervisorList = BoardList::create([
            'name' => 'Supervisor Review',
            'board_id' => $board->id,
            'position' => 0,
        ]);

        $card = Card::create([
            'title' => 'Final Banner Graphic 2026',
            'board_id' => $board->id,
            'board_list_id' => $supervisorList->id,
            'created_by' => $supervisor->id,
            'priority' => 'high',
            'label' => 'Graphic',
            'is_archived' => false,
        ]);

        $label = Label::create([
            'name' => 'Urgent Revision',
            'color' => '#ef4444',
            'board_id' => $board->id,
        ]);
        $card->labels()->attach($label->id);

        // Create Planning Board to test planning stats auto-expansion
        $planningBoard = Board::create([
            'name' => 'Graphic Planning Board 2026',
            'slug' => 'graphic-planning-board-2026',
            'workspace_id' => $workspace->id,
            'created_by' => $supervisor->id,
            'is_archived' => false,
            'is_hidden' => false,
        ]);

        $response = $this->actingAs($supervisor)->get(route('dashboard'));
        $response->assertOk();

        // Check 2-Column Summary exists
        $response->assertSee('Supervisor List');
        $response->assertSee('QC Review');
        $response->assertSee('Your Queue');

        // Check task list below
        $response->assertSee('Tasks on Supervisor List');
        $response->assertSee('Final Banner Graphic 2026');
        $response->assertSee('Urgent Revision');

        // Check direct link with card query parameter
        $cardUrl = route('boards.show', [$board->slug, 'card' => $card->id]);
        $response->assertSee($cardUrl);

        // Check planning boards are auto-expanded by default
        $response->assertSee('x-data="{ expanded: true }"', false);
    }

    public function test_qc_user_sees_qc_action_tasks_and_direct_card_links(): void
    {
        $qc = User::factory()->create([
            'is_active' => true,
            'name' => 'Mr. Dara (QC)',
            'team_role' => 'QC',
        ]);
        $qc->assignRole('digital-team');

        $workspace = Workspace::create([
            'name' => 'QC Workspace',
            'owner_id' => $qc->id,
        ]);
        $board = Board::create([
            'name' => 'Workflow board - September 2026',
            'slug' => 'workflow-board-september-2026',
            'workspace_id' => $workspace->id,
            'created_by' => $qc->id,
            'is_archived' => false,
        ]);

        BoardList::create(['name' => 'Approved', 'board_id' => $board->id, 'position' => 2]);
        $qcList = BoardList::create(['name' => 'Text (QC) Review (Mr. Dara)', 'board_id' => $board->id, 'position' => 1]);

        $card = Card::create([
            'title' => 'Final Description TYPH-KUVUO 2.5',
            'board_id' => $board->id,
            'board_list_id' => $qcList->id,
            'created_by' => $qc->id,
            'priority' => 'urgent',
            'label' => 'CRM',
            'is_archived' => false,
        ]);

        $response = $this->actingAs($qc)->get(route('dashboard'));
        $response->assertOk();

        $response->assertSee('Tasks Awaiting QC Action');
        $response->assertSee('Final Description TYPH-KUVUO 2.5');

        // Check direct card auto-open link
        $cardUrl = route('boards.show', [$board->slug, 'card' => $card->id]);
        $response->assertSee($cardUrl);
    }
}
