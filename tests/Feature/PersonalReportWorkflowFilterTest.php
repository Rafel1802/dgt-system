<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PersonalReportWorkflowFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    }

    public function test_personal_report_only_includes_workflow_boards_and_excludes_planning_boards(): void
    {
        $qcUser = User::factory()->create([
            'name' => 'QC Dara',
            'team_role' => 'QC Specialist',
            'is_active' => true,
        ]);
        $this->actingAs($qcUser);

        $workspace = Workspace::create([
            'name' => 'Graphic Team',
            'owner_id' => $qcUser->id,
            'is_active' => true,
        ]);

        // Create 2 workflow boards (one current, one hidden past month)
        $currentWorkflow = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'Workflow board – September 2026',
            'slug' => 'workflow-board-september-2026',
            'is_hidden' => false,
            'is_archived' => false,
            'created_by' => $qcUser->id,
            'background_type' => 'color',
            'background_value' => '#4f46e5',
        ]);

        $pastWorkflow = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'Workflow board – August 2026',
            'slug' => 'workflow-board-august-2026',
            'is_hidden' => true,
            'is_archived' => false,
            'created_by' => $qcUser->id,
            'background_type' => 'color',
            'background_value' => '#4f46e5',
        ]);

        // Create 2 planning boards (should be strictly excluded)
        $currentPlanning = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'Planning board – September 2026',
            'slug' => 'planning-board-september-2026',
            'is_hidden' => false,
            'is_archived' => false,
            'created_by' => $qcUser->id,
            'background_type' => 'color',
            'background_value' => '#4f46e5',
        ]);

        $pastPlanning = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'Planning board – August 2026',
            'slug' => 'planning-board-august-2026',
            'is_hidden' => true,
            'is_archived' => false,
            'created_by' => $qcUser->id,
            'background_type' => 'color',
            'background_value' => '#4f46e5',
        ]);

        $response = $this->get(route('boards.reports.personal'));
        $response->assertSuccessful();

        $workspaces = $response->viewData('workspaces');
        $this->assertNotEmpty($workspaces);

        $boardNames = $workspaces->first()->boards->pluck('name')->all();

        // Must include the workflow boards
        $this->assertContains('Workflow board – September 2026', $boardNames);
        $this->assertContains('Workflow board – August 2026', $boardNames);

        // Must NOT include the planning boards
        $this->assertNotContains('Planning board – September 2026', $boardNames);
        $this->assertNotContains('Planning board – August 2026', $boardNames);

        // Verify hidden boards count
        $hiddenBoardsCount = $response->viewData('hiddenBoardsCount');
        $this->assertEquals(1, $hiddenBoardsCount);

        // Verify HTML renders Old Hidden Board button
        $response->assertSee('Old Hidden Board');
    }

    public function test_workspace_with_only_planning_boards_is_completely_excluded(): void
    {
        $qcUser = User::factory()->create([
            'name' => 'QC Dara',
            'team_role' => 'QC Specialist',
            'is_active' => true,
        ]);
        $this->actingAs($qcUser);

        $planningOnlyWorkspace = Workspace::create([
            'name' => 'Planning Only Workspace',
            'owner_id' => $qcUser->id,
            'is_active' => true,
        ]);

        Board::create([
            'workspace_id' => $planningOnlyWorkspace->id,
            'name' => 'Planning board – September 2026',
            'slug' => 'planning-board-september-2026-unique',
            'is_hidden' => false,
            'is_archived' => false,
            'created_by' => $qcUser->id,
            'background_type' => 'color',
            'background_value' => '#4f46e5',
        ]);

        $response = $this->get(route('boards.reports.personal'));
        $response->assertSuccessful();

        $workspaces = $response->viewData('workspaces');
        $workspaceNames = $workspaces->pluck('name')->all();

        $this->assertNotContains('Planning Only Workspace', $workspaceNames);
    }

    public function test_qc_user_can_restore_hidden_board(): void
    {
        $qcUser = User::factory()->create([
            'name' => 'QC Dara',
            'team_role' => 'QC Specialist',
            'is_active' => true,
        ]);
        $this->actingAs($qcUser);

        $workspace = Workspace::create([
            'name' => 'Listing Team',
            'owner_id' => $qcUser->id,
            'is_active' => true,
        ]);

        $hiddenBoard = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'Workflow board – August 2026',
            'slug' => 'workflow-board-august-2026-restore',
            'is_hidden' => true,
            'is_archived' => false,
            'created_by' => $qcUser->id,
            'background_type' => 'color',
            'background_value' => '#4f46e5',
        ]);

        $response = $this->patchJson(route('boards.toggle-hidden', $hiddenBoard->slug));
        $response->assertSuccessful();
        $response->assertJson(['success' => true, 'is_hidden' => false]);

        $this->assertFalse($hiddenBoard->fresh()->is_hidden);
    }
}
