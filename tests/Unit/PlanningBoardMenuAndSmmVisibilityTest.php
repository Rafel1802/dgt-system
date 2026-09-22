<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Board;
use App\Models\Card;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class PlanningBoardMenuAndSmmVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'boss', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin-digital', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'digital-team', 'guard_name' => 'web']);
    }

    public function test_boss_supervisor_and_qc_are_identified_correctly()
    {
        $boss = User::factory()->create();
        $boss->assignRole('boss');
        $this->assertTrue($boss->isBossSupervisorOrQc());

        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');
        $this->assertTrue($supervisor->isBossSupervisorOrQc());

        $supervisorByTeamRole = User::factory()->create(['team_role' => 'Video Supervisor']);
        $this->assertTrue($supervisorByTeamRole->isBossSupervisorOrQc());

        $qcUser = User::factory()->create(['team_role' => 'QC Reviewer']);
        $this->assertTrue($qcUser->isBossSupervisorOrQc());

        $regularMember = User::factory()->create(['team_role' => 'Video Editor']);
        $regularMember->assignRole('digital-team');
        $this->assertFalse($regularMember->isBossSupervisorOrQc());
    }

    public function test_boss_supervisor_and_qc_receive_empty_planning_boards_collection()
    {
        $admin = User::factory()->create();
        $workspace = Workspace::create(['name' => 'Video Team', 'color' => '#6366f1', 'owner_id' => $admin->id]);
        $board = Board::create([
            'name' => 'Video Team Board',
            'workspace_id' => $workspace->id,
            'is_archived' => false,
            'is_hidden' => false,
            'created_by' => $admin->id,
        ]);

        $boss = User::factory()->create();
        $boss->assignRole('boss');
        $board->members()->attach($boss->id, ['role' => 'member']);

        $this->assertCount(0, $boss->getPlanningBoardsWithTaskCounts());

        $qc = User::factory()->create(['team_role' => 'QC']);
        $board->members()->attach($qc->id, ['role' => 'member']);

        $this->assertCount(0, $qc->getPlanningBoardsWithTaskCounts());
    }

    public function test_regular_member_retrieves_planning_boards_excluding_workflow_boards()
    {
        $admin = User::factory()->create();
        $workspace = Workspace::create(['name' => 'Video Team', 'color' => '#6366f1', 'owner_id' => $admin->id]);

        // Planning board
        $planningBoard = Board::create([
            'name' => 'Video Team Board',
            'workspace_id' => $workspace->id,
            'is_archived' => false,
            'is_hidden' => false,
            'created_by' => $admin->id,
        ]);

        // Workflow board (should be excluded!)
        $workflowBoard = Board::create([
            'name' => 'Workflow board – September 2026',
            'workspace_id' => $workspace->id,
            'is_archived' => false,
            'is_hidden' => false,
            'created_by' => $admin->id,
        ]);

        $samnang = User::factory()->create(['name' => 'Samnang', 'team_role' => 'Video Editor']);
        $samnang->assignRole('digital-team');

        $planningBoard->members()->attach($samnang->id, ['role' => 'member']);
        $workflowBoard->members()->attach($samnang->id, ['role' => 'member']);

        // Create cards on planning board
        $card1 = Card::create([
            'board_id' => $planningBoard->id,
            'title' => 'Video Editing Task 1',
            'is_archived' => false,
            'created_by' => $admin->id,
        ]);
        $card1->assignees()->attach($samnang->id);

        $card2 = Card::create([
            'board_id' => $planningBoard->id,
            'title' => 'Video Editing Task 2',
            'is_archived' => false,
            'created_by' => $admin->id,
        ]);
        $card2->assignees()->attach($samnang->id);

        // Another card assigned to someone else
        $otherUser = User::factory()->create();
        $card3 = Card::create([
            'board_id' => $planningBoard->id,
            'title' => 'Other Task',
            'is_archived' => false,
            'created_by' => $admin->id,
        ]);
        $card3->assignees()->attach($otherUser->id);

        $boards = $samnang->getPlanningBoardsWithTaskCounts();

        // Must ONLY return the planning board, NEVER the workflow board
        $this->assertCount(1, $boards);
        $first = $boards->first();
        $this->assertEquals($planningBoard->id, $first->id);
        $this->assertStringContainsString('Video Team Board', $first->name);

        // Task count must be 2 (the 2 cards assigned to Samnang)
        $this->assertEquals(2, $first->user_tasks_count);
    }

    public function test_unassigned_board_falls_back_to_total_active_cards()
    {
        $admin = User::factory()->create();
        $workspace = Workspace::create(['name' => 'Graphic Team', 'color' => '#6366f1', 'owner_id' => $admin->id]);
        $planningBoard = Board::create([
            'name' => 'Graphic Team Board',
            'workspace_id' => $workspace->id,
            'is_archived' => false,
            'is_hidden' => false,
            'created_by' => $admin->id,
        ]);

        $member = User::factory()->create(['name' => 'Dara', 'team_role' => 'Designer']);
        $member->assignRole('digital-team');
        $planningBoard->members()->attach($member->id, ['role' => 'member']);

        // 3 cards with NO assignees at all
        Card::create(['board_id' => $planningBoard->id, 'title' => 'Card A', 'is_archived' => false, 'created_by' => $admin->id]);
        Card::create(['board_id' => $planningBoard->id, 'title' => 'Card B', 'is_archived' => false, 'created_by' => $admin->id]);
        Card::create(['board_id' => $planningBoard->id, 'title' => 'Card C', 'is_archived' => false, 'created_by' => $admin->id]);

        $boards = $member->getPlanningBoardsWithTaskCounts();
        $this->assertCount(1, $boards);
        $this->assertEquals(3, $boards->first()->user_tasks_count);
    }

    public function test_task_count_controller_renders_board_boxes_with_month_and_task_count()
    {
        $admin = User::factory()->create();
        $workspace = Workspace::create(['name' => 'Video Team', 'color' => '#6366f1', 'owner_id' => $admin->id]);
        $board = Board::create([
            'name' => 'Planning board – September 2026',
            'workspace_id' => $workspace->id,
            'is_archived' => false,
            'is_hidden' => false,
            'created_by' => $admin->id,
        ]);

        $listWeek1 = $board->lists()->create(['name' => 'Week 1', 'position' => 0]);
        $listWeek2 = $board->lists()->create(['name' => 'Week 2', 'position' => 1]);

        $samnang = User::factory()->create(['name' => 'Samnang', 'team_role' => 'Video Editor']);
        $samnang->assignRole('digital-team');
        $board->members()->attach($samnang->id, ['role' => 'member']);

        // 2 cards for Samnang: one in Week 1, one in Week 2
        $c1 = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $listWeek1->id,
            'title' => 'Video Cut 1',
            'is_archived' => false,
            'created_by' => $admin->id,
        ]);
        $c1->assignees()->attach($samnang->id);

        $c2 = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $listWeek2->id,
            'title' => 'Video Cut 2',
            'is_archived' => false,
            'created_by' => $admin->id,
        ]);
        $c2->assignees()->attach($samnang->id);

        $response = $this->actingAs($samnang)->get(route('tasks.count'));

        $response->assertStatus(200);
        $response->assertViewIs('boards.tasks-count');
        $response->assertViewHas('boardBoxes');
        $response->assertViewHas('totalTasksCount', 2);

        $boxes = $response->viewData('boardBoxes');
        $this->assertCount(1, $boxes);
        $this->assertEquals('Video Team', $boxes[0]['workspace_name']);
        $this->assertEquals('September 2026', $boxes[0]['month_year']);
        $this->assertEquals(2, $boxes[0]['task_count']);
        $this->assertEquals(1, $boxes[0]['weeks']['Week 1']);
        $this->assertEquals(1, $boxes[0]['weeks']['Week 2']);

        // Test filtering by board ID
        $filteredResponse = $this->actingAs($samnang)->get(route('tasks.count', ['board_id' => $board->id]));
        $filteredResponse->assertStatus(200);
        $filteredCards = $filteredResponse->viewData('filteredCards');
        $this->assertCount(2, $filteredCards);
    }

    public function test_due_date_warning_alerts_one_day_before()
    {
        $admin = User::factory()->create();
        $workspace = Workspace::create(['name' => 'Video Team', 'color' => '#6366f1', 'owner_id' => $admin->id]);
        $board = Board::create([
            'name' => 'Planning board – September 2026',
            'workspace_id' => $workspace->id,
            'is_archived' => false,
            'is_hidden' => false,
            'created_by' => $admin->id,
        ]);
        $list = $board->lists()->create(['name' => 'Week 3', 'position' => 0]);

        $samnang = User::factory()->create(['name' => 'Samnang', 'team_role' => 'Video Editor']);
        $samnang->assignRole('digital-team');
        $board->members()->attach($samnang->id, ['role' => 'member']);

        // Card 1: Due tomorrow (1 day before due) -> MUST be alerted!
        $tomorrow = \Carbon\Carbon::tomorrow()->toDateString();
        $cTomorrow = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Urgent Video Render for Client',
            'deadline' => $tomorrow,
            'is_archived' => false,
            'created_by' => $admin->id,
        ]);
        $cTomorrow->assignees()->attach($samnang->id);

        // Card 2: Due next week (7 days away) -> Should NOT trigger 1-day alert
        $cNextWeek = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Future Video Planning',
            'deadline' => \Carbon\Carbon::now()->addDays(7)->toDateString(),
            'is_archived' => false,
            'created_by' => $admin->id,
        ]);
        $cNextWeek->assignees()->attach($samnang->id);

        // Card 3: Overdue card -> Should also alert
        $cOverdue = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Overdue Thumbnail Design',
            'deadline' => \Carbon\Carbon::now()->subDays(2)->toDateString(),
            'is_archived' => false,
            'created_by' => $admin->id,
        ]);
        $cOverdue->assignees()->attach($samnang->id);

        $response = $this->actingAs($samnang)->get(route('tasks.count'));

        $response->assertStatus(200);
        $response->assertViewHas('dueTomorrowCount', 1);
        $response->assertViewHas('overdueCount', 1);
        $response->assertViewHas('totalWarningCount', 2);

        $warnings = $response->viewData('warningTasks');
        $this->assertCount(2, $warnings);

        // Assert warning tasks contain task name, board name, and due date
        $tomorrowWarning = $warnings->firstWhere('due_type', 'tomorrow');
        $this->assertNotNull($tomorrowWarning);
        $this->assertEquals('Urgent Video Render for Client', $tomorrowWarning['title']);
        $this->assertEquals('Video Team', $tomorrowWarning['workspace_name']);
        $this->assertStringContainsString('Due in 1 Day', $tomorrowWarning['badge_text']);
        $this->assertNotEmpty($tomorrowWarning['due_formatted']);

        // Assert the page HTML outputs the warning banner with task name and due date
        $response->assertSee('Deadline Warning Alert');
        $response->assertSee('Urgent Video Render for Client');
        $response->assertSee('Overdue Thumbnail Design');
        $response->assertDontSee("\u{FFFD}"); // No broken UTF-8 replacement character
    }

    public function test_tasks_count_and_deadline_warning_shows_on_dashboard_for_members()
    {
        $admin = User::factory()->create();
        $workspace = Workspace::create(['name' => 'Video Team', 'color' => '#6366f1', 'owner_id' => $admin->id]);
        $board = Board::create([
            'name' => 'Planning board – September 2026',
            'workspace_id' => $workspace->id,
            'is_archived' => false,
            'is_hidden' => false,
            'created_by' => $admin->id,
        ]);
        $list = $board->lists()->create(['name' => 'Week 3', 'position' => 0]);

        $member = User::factory()->create(['name' => 'Lyza', 'team_role' => 'Content Writer']);
        $member->assignRole('digital-team');
        $board->members()->attach($member->id, ['role' => 'member']);

        // Overdue task
        $cOverdue = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Create Description TYPH-40',
            'deadline' => \Carbon\Carbon::now()->subDays(4)->toDateString(),
            'is_archived' => false,
            'created_by' => $admin->id,
        ]);
        $cOverdue->assignees()->attach($member->id);

        // Future task
        $cFuture = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Future Content Research',
            'deadline' => \Carbon\Carbon::now()->addDays(5)->toDateString(),
            'is_archived' => false,
            'created_by' => $admin->id,
        ]);
        $cFuture->assignees()->attach($member->id);

        // Test dashboard view for member
        $response = $this->actingAs($member)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('totalTasksCount', 2);
        $response->assertViewHas('overdueCount', 1);
        $response->assertViewHas('totalWarningCount', 1);

        $response->assertSee('Your Assigned Tasks');
        $response->assertSee('TOTAL TASKS');
        $response->assertSee('OVERDUE');
        $response->assertSee('Deadline Warning Alert');
        $response->assertSee('Create Description TYPH-40');
    }

    public function test_overdue_only_counts_uncompleted_tasks_and_excludes_approved_or_completed()
    {
        $admin = User::factory()->create();
        $workspace = Workspace::create(['name' => 'Video Team', 'color' => '#6366f1', 'owner_id' => $admin->id]);
        
        // Planning board
        $planningBoard = Board::create([
            'name' => 'Planning board – September 2026',
            'workspace_id' => $workspace->id,
            'is_archived' => false,
            'is_hidden' => false,
            'created_by' => $admin->id,
        ]);
        $listWeek2 = $planningBoard->lists()->create(['name' => 'Week 2', 'position' => 0]);

        // Workflow board
        $workflowBoard = Board::create([
            'name' => 'Workflow board – September 2026',
            'workspace_id' => $workspace->id,
            'is_archived' => false,
            'is_hidden' => false,
            'created_by' => $admin->id,
        ]);
        $listApproved = $workflowBoard->lists()->create(['name' => 'Approved', 'position' => 10]);

        $samnang = User::factory()->create(['name' => 'Samnang', 'team_role' => 'Video Editor']);
        $samnang->assignRole('digital-team');
        $planningBoard->members()->attach($samnang->id, ['role' => 'member']);

        $pastDate = \Carbon\Carbon::now()->subDays(5)->toDateString();

        // Task 1: Approved via approved_at -> MUST NOT BE OVERDUE
        $c1 = Card::create([
            'board_id' => $planningBoard->id,
            'board_list_id' => $listWeek2->id,
            'title' => 'STOMP 502R PRO + 502G PRO',
            'deadline' => $pastDate,
            'approved_at' => \Carbon\Carbon::now()->subDays(1),
            'status' => 'approved',
            'is_archived' => false,
            'created_by' => $admin->id,
        ]);
        $c1->assignees()->attach($samnang->id);

        // Task 2: Completed via block_completed_at -> MUST NOT BE OVERDUE
        $c2 = Card::create([
            'board_id' => $planningBoard->id,
            'board_list_id' => $listWeek2->id,
            'title' => 'TYPH-0115 + TYPH-2004 - Short Reel (HOLD)',
            'deadline' => $pastDate,
            'block_completed_at' => \Carbon\Carbon::now()->subDays(1),
            'status' => 'todo',
            'is_archived' => false,
            'created_by' => $admin->id,
        ]);
        $c2->assignees()->attach($samnang->id);

        // Task 3: In Week 2 on Planning board, but its twin on Workflow board is in "Approved" list -> MUST NOT BE OVERDUE
        $c3Planning = Card::create([
            'board_id' => $planningBoard->id,
            'board_list_id' => $listWeek2->id,
            'title' => 'TYPH-0903 - Short Reel (HOLD)',
            'deadline' => $pastDate,
            'status' => 'todo',
            'sync_group_id' => 'sync-test-999',
            'is_archived' => false,
            'created_by' => $admin->id,
        ]);
        $c3Planning->assignees()->attach($samnang->id);

        $c3Workflow = Card::create([
            'board_id' => $workflowBoard->id,
            'board_list_id' => $listApproved->id, // In Approved list
            'title' => 'TYPH-0903 - Short Reel (HOLD)',
            'deadline' => $pastDate,
            'status' => 'approved',
            'sync_group_id' => 'sync-test-999',
            'is_archived' => false,
            'created_by' => $admin->id,
        ]);

        // Task 4: Genuinely uncompleted overdue task -> MUST BE OVERDUE
        $c4GenuinelyOverdue = Card::create([
            'board_id' => $planningBoard->id,
            'board_list_id' => $listWeek2->id,
            'title' => 'Unfinished Urgent Video Cut',
            'deadline' => $pastDate,
            'status' => 'in_progress',
            'is_archived' => false,
            'created_by' => $admin->id,
        ]);
        $c4GenuinelyOverdue->assignees()->attach($samnang->id);

        $response = $this->actingAs($samnang)->get(route('tasks.count'));

        $response->assertStatus(200);
        // Only task 4 should be overdue! The 3 approved/completed tasks must NOT be counted
        $response->assertViewHas('overdueCount', 1);
        $response->assertViewHas('totalWarningCount', 1);

        $warningTasks = $response->viewData('warningTasks');
        $this->assertCount(1, $warningTasks);
        $this->assertEquals('Unfinished Urgent Video Cut', $warningTasks[0]['title']);

        // Assert approved/complete tasks do NOT show in warning alert
        $this->assertNull($warningTasks->firstWhere('title', 'STOMP 502R PRO + 502G PRO'));
        $this->assertNull($warningTasks->firstWhere('title', 'TYPH-0115 + TYPH-2004 - Short Reel (HOLD)'));
        $this->assertNull($warningTasks->firstWhere('title', 'TYPH-0903 - Short Reel (HOLD)'));
        $response->assertSee('Unfinished Urgent Video Cut');
    }

    public function test_smm_planning_board_is_excluded_and_does_not_count_to_total()
    {
        $admin = User::factory()->create();

        // 1. Team Workspace & Team Planning Board (e.g. Video Team)
        $videoWorkspace = Workspace::create(['name' => 'Video Team', 'color' => '#6366f1', 'owner_id' => $admin->id]);
        $videoBoard = Board::create([
            'name' => 'Planning board – September 2026',
            'workspace_id' => $videoWorkspace->id,
            'is_archived' => false,
            'is_hidden' => false,
            'created_by' => $admin->id,
        ]);
        $listWeek1 = $videoBoard->lists()->create(['name' => 'Week 1', 'position' => 0]);

        // 2. Social Media Management Workspace & SMM Planning Board
        $smmWorkspace = Workspace::create(['name' => 'Social Media Management', 'color' => '#8b5cf6', 'owner_id' => $admin->id]);
        $smmBoard = Board::create([
            'name' => 'SMM Planning Board – September 2026',
            'workspace_id' => $smmWorkspace->id,
            'type' => 'smm',
            'is_archived' => false,
            'is_hidden' => false,
            'created_by' => $admin->id,
        ]);
        $smmList = $smmBoard->lists()->create(['name' => 'Week 1', 'position' => 0]);

        $samnang = User::factory()->create(['name' => 'Mr. Samnang', 'team_role' => 'Video Editor']);
        $samnang->assignRole('digital-team');

        $videoBoard->members()->attach($samnang->id, ['role' => 'member']);
        $smmBoard->members()->attach($samnang->id, ['role' => 'member']);

        // 3 cards on Video Team Planning board (2 regular + 1 with SMM label)
        $card1 = Card::create([
            'board_id' => $videoBoard->id,
            'board_list_id' => $listWeek1->id,
            'title' => 'Video Team Card 1',
            'is_archived' => false,
            'created_by' => $admin->id,
        ]);
        $card1->assignees()->attach($samnang->id);

        $card2 = Card::create([
            'board_id' => $videoBoard->id,
            'board_list_id' => $listWeek1->id,
            'title' => 'Video Team Card 2 (with SMM label)',
            'is_archived' => false,
            'created_by' => $admin->id,
        ]);
        $card2->assignees()->attach($samnang->id);
        $smmLabel = $videoBoard->labels()->create(['name' => 'SMM', 'color' => '#6366f1']);
        $card2->labels()->attach($smmLabel->id);

        // 5 cards on SMM Planning Board (should NOT be included or counted in total!)
        for ($i = 1; $i <= 5; $i++) {
            $smmCard = Card::create([
                'board_id' => $smmBoard->id,
                'board_list_id' => $smmList->id,
                'title' => "SMM Board Card {$i}",
                'is_archived' => false,
                'created_by' => $admin->id,
            ]);
            $smmCard->assignees()->attach($samnang->id);
        }

        // Check getPlanningBoardsWithTaskCounts
        $planningBoards = $samnang->getPlanningBoardsWithTaskCounts();

        // Must ONLY return Video Team Board (1 board), NOT SMM Planning Board
        $this->assertCount(1, $planningBoards);
        $this->assertEquals($videoBoard->id, $planningBoards->first()->id);
        $this->assertEquals(2, $planningBoards->first()->user_tasks_count);

        // Check tasks count page HTTP request
        $response = $this->actingAs($samnang)->get(route('tasks.count'));

        $response->assertStatus(200);
        // Total tasks count must be 2 (ONLY from Video Team Planning Board, SMM Planning Board's 5 cards excluded!)
        $response->assertViewHas('totalTasksCount', 2);

        $boardBoxes = $response->viewData('boardBoxes');
        $this->assertCount(1, $boardBoxes);
        $this->assertEquals('Video Team', $boardBoxes[0]['workspace_name']);
        $this->assertEquals(2, $boardBoxes[0]['task_count']);

        // SMM Planning Board should not appear in the response
        $response->assertDontSee('SMM Planning Board');
    }
}


