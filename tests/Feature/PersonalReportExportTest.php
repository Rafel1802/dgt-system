<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PersonalReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    }

    public function test_dara_can_export_personal_report_pdf(): void
    {
        $dara = User::factory()->create([
            'name' => 'Mr. Dara (Head)',
            'username' => 'dara',
            'email' => 'dara@test.com',
            'is_active' => true,
        ]);
        $dara->assignRole('admin-digital');

        $workspace = Workspace::create(['name' => 'Digital Dept', 'owner_id' => $dara->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board Team A – October 2026', 'created_by' => $dara->id]);
        $list = BoardList::create(['board_id' => $board->id, 'name' => 'Approved', 'position' => 1]);

        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Card 1',
            'team' => 'A',
            'created_by' => $dara->id,
        ]);

        $card->comments()->create([
            'user_id' => $dara->id,
            'content' => 'Production approved',
            'is_system' => false,
        ]);

        $response = $this->actingAs($dara)->get('/boards/personal-report/export?is_personal_report=1&report_type=kanban&date_range=today&format=pdf');
        $response->assertOk();
    }

    public function test_dara_can_export_personal_report_csv(): void
    {
        $dara = User::factory()->create([
            'name' => 'Mr. Dara (Head)',
            'username' => 'dara',
            'email' => 'dara@test.com',
            'is_active' => true,
        ]);
        $dara->assignRole('admin-digital');

        $workspace = Workspace::create(['name' => 'Digital Dept', 'owner_id' => $dara->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board Team A – October 2026', 'created_by' => $dara->id]);
        $list = BoardList::create(['board_id' => $board->id, 'name' => 'Approved', 'position' => 1]);

        Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Card 1',
            'team' => 'A',
            'created_by' => $dara->id,
        ]);

        $response = $this->actingAs($dara)->get('/boards/personal-report/export?is_personal_report=1&report_type=kanban&date_range=today&format=csv');
        $response->assertOk();
    }

    public function test_kim_can_export_personal_report_pdf(): void
    {
        $kim = User::factory()->create([
            'name' => 'Mr. Kim',
            'username' => 'kim',
            'email' => 'kim@test.com',
            'is_active' => true,
        ]);
        $kim->assignRole('admin-digital');

        $workspace = Workspace::create(['name' => 'Digital Dept', 'owner_id' => $kim->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board Team B – October 2026', 'created_by' => $kim->id]);
        $list = BoardList::create(['board_id' => $board->id, 'name' => 'Approved', 'position' => 1]);

        Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Card B1',
            'team' => 'B',
            'created_by' => $kim->id,
        ]);

        $response = $this->actingAs($kim)->get('/boards/personal-report/export?is_personal_report=1&report_type=kanban&date_range=this_month&format=pdf');
        $response->assertOk();
    }

    public function test_card_in_digital_department_shows_yellow_digital_department_status_not_fail(): void
    {
        $dara = User::factory()->create([
            'name' => 'Mr. Dara (Head)',
            'username' => 'dara',
            'email' => 'dara@test.com',
            'is_active' => true,
        ]);
        $dara->assignRole('admin-digital');

        $workspace = Workspace::create(['name' => 'Digital Dept', 'owner_id' => $dara->id, 'is_active' => true]);
        $planningBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'Planning Board@KiuQ – October 2026', 'created_by' => $dara->id]);
        $week1List = BoardList::create(['board_id' => $planningBoard->id, 'name' => 'Week 1', 'position' => 1]);

        $workflowBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board Team A – October 2026', 'created_by' => $dara->id]);
        $digitalDeptList = BoardList::create(['board_id' => $workflowBoard->id, 'name' => 'Digital Department', 'position' => 3]);

        $syncGroupId = 'test-sync-group-123';
        $planningCard = Card::create([
            'board_id' => $planningBoard->id,
            'board_list_id' => $week1List->id,
            'title' => 'PRJ: KUVUO WITH BUNDLE - Ebay listing',
            'team' => 'A',
            'created_by' => $dara->id,
            'sync_group_id' => $syncGroupId,
        ]);

        $workflowCard = Card::create([
            'board_id' => $workflowBoard->id,
            'board_list_id' => $digitalDeptList->id,
            'title' => 'PRJ: KUVUO WITH BUNDLE - Ebay listing',
            'team' => 'A',
            'created_by' => $dara->id,
            'sync_group_id' => $syncGroupId,
        ]);

        $workflowCard->comments()->create([
            'user_id' => $dara->id,
            'content' => 'Production approved',
            'is_system' => false,
        ]);

        $response = $this->actingAs($dara)->get('/boards/personal-report/export?is_personal_report=1&report_type=kanban&date_range=today&format=pdf');
        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('Digital Department', $content);
        $this->assertStringContainsString('background:#fef08a', $content);
        $this->assertStringNotContainsString('background:#fee2e2;color:#b91c1c;">Fail', $content);
    }

    public function test_personal_report_ui_shows_only_board_for_dara(): void
    {
        $dara = User::factory()->create([
            'name' => 'Mr. Dara (Head)',
            'username' => 'dara',
            'email' => 'dara@test.com',
            'is_active' => true,
        ]);
        $dara->assignRole('admin-digital');

        $workspace = Workspace::create(['name' => 'Digital Dept', 'owner_id' => $dara->id, 'is_active' => true]);
        Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board Team A – October 2026', 'created_by' => $dara->id]);

        $response = $this->actingAs($dara)->get('/boards/personal-report');
        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('Board Report', $content);
        $this->assertStringContainsString('Team A Report', $content);
        $this->assertStringNotContainsString('value="website"', $content);
        $this->assertStringNotContainsString('value="follow_up"', $content);
        $this->assertStringNotContainsString('value="social_media"', $content);
        $this->assertStringNotContainsString('Select Social Media Class', $content);
    }

    public function test_personal_report_ui_shows_only_board_for_kim(): void
    {
        $kim = User::factory()->create([
            'name' => 'Mr. Kim',
            'username' => 'kim',
            'email' => 'kim@test.com',
            'is_active' => true,
        ]);
        $kim->assignRole('admin-digital');

        $workspace = Workspace::create(['name' => 'Digital Dept', 'owner_id' => $kim->id, 'is_active' => true]);
        Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board Team B – October 2026', 'created_by' => $kim->id]);

        $response = $this->actingAs($kim)->get('/boards/personal-report');
        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('Board Report', $content);
        $this->assertStringContainsString('Team B Report', $content);
        $this->assertStringNotContainsString('value="website"', $content);
        $this->assertStringNotContainsString('value="follow_up"', $content);
        $this->assertStringNotContainsString('value="social_media"', $content);
        $this->assertStringNotContainsString('Select Social Media Class', $content);
    }

    public function test_personal_report_ui_shows_all_options_for_general_supervisor(): void
    {
        $supervisor = User::factory()->create([
            'name' => 'General Supervisor',
            'username' => 'gensup',
            'email' => 'gensup@test.com',
            'team_role' => 'supervisor',
            'is_active' => true,
        ]);
        $supervisor->assignRole('super-admin');

        $workspace = Workspace::create(['name' => 'Digital Dept', 'owner_id' => $supervisor->id, 'is_active' => true]);
        Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board Team A – October 2026', 'created_by' => $supervisor->id]);

        $response = $this->actingAs($supervisor)->get('/boards/personal-report');
        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('value="kanban"', $content);
        $this->assertStringContainsString('value="website"', $content);
        $this->assertStringContainsString('value="social_media"', $content);
    }

    public function test_personal_report_ui_shows_planning_board_for_this_month_and_hides_past_month(): void
    {
        $dara = User::factory()->create([
            'name' => 'Mr. Dara (Head)',
            'username' => 'dara_test_planning',
            'email' => 'dara_plan@test.com',
            'is_active' => true,
        ]);
        $dara->assignRole('admin-digital');

        $workspace = Workspace::create(['name' => 'Digital Dept', 'owner_id' => $dara->id, 'is_active' => true]);
        
        $currentMonth = now()->format('F Y'); // e.g. "October 2026"
        $currentMonthShort = now()->format('M Y'); // e.g. "Oct 2026"

        $boardCurrentWorkflow = Board::create(['workspace_id' => $workspace->id, 'name' => "Workflow board Team A – {$currentMonth}", 'created_by' => $dara->id, 'is_hidden' => false]);
        $boardCurrentPlanning = Board::create(['workspace_id' => $workspace->id, 'name' => "Planning board Team A – {$currentMonth}", 'type' => 'planning', 'created_by' => $dara->id, 'is_hidden' => false]);
        $boardPastPlanning = Board::create(['workspace_id' => $workspace->id, 'name' => 'Planning board Team A – September 2026', 'type' => 'planning', 'created_by' => $dara->id, 'is_hidden' => false]);

        $response = $this->actingAs($dara)->get('/boards/personal-report');
        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString("Planning board Team A – {$currentMonth}", $content);
        $this->assertStringContainsString('Planning board Team A – September 2026', $content);
        $this->assertStringContainsString('Old Hidden Board', $content);
    }

    public function test_member_export_planning_board_includes_normal_card_and_smm_ticked_checklist_task(): void
    {
        $member = User::factory()->create([
            'name' => 'Member John',
            'username' => 'john',
            'email' => 'john@test.com',
            'is_active' => true,
        ]);
        $member->assignRole('digital-team');

        $workspace = Workspace::create(['name' => 'Digital Dept', 'owner_id' => $member->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Planning board Team A – October 2026', 'type' => 'planning', 'created_by' => $member->id]);
        $list = BoardList::create(['board_id' => $board->id, 'name' => 'In Progress', 'position' => 1]);

        // 1. Normal card assigned to member
        $normalCard = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Normal Website Task 101',
            'description' => 'Fix header styling',
            'created_by' => $member->id,
        ]);
        $normalCard->assignees()->attach($member->id);

        // 2. SMM card with checklist
        $smmCard = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'SMM Campaign October',
            'description' => "1.TYPH-0101 : Power drill introduction video\n2.TYPH-0202 : Battery comparison graphic",
            'smm_class_label' => 'Video',
            'created_by' => $member->id,
        ]);
        $chk = $smmCard->checklists()->create(['title' => 'Tasks Checklist', 'position' => 1]);
        $item1 = $chk->items()->create([
            'content' => '1.Video Short : 15TH-OCT',
            'is_completed' => true,
            'completed_by' => $member->id,
            'assigned_user_id' => $member->id,
            'completed_at' => now(),
            'position' => 1,
        ]);
        $item2 = $chk->items()->create([
            'content' => '2.Graphic Post : 16TH-OCT',
            'is_completed' => false,
            'assigned_user_id' => $member->id,
            'position' => 2,
        ]);

        $response = $this->actingAs($member)->get("/boards/{$board->slug}/export/csv?member_id={$member->id}&raw=1");
        $response->assertOk();
        $csv = $response->streamedContent();

        // Normal card must be included
        $this->assertStringContainsString('Normal Website Task 101', $csv);
        // SMM ticked checklist task must be detected from description
        $this->assertStringContainsString('1.TYPH-0101 : Power drill introduction video', $csv);
        // Unticked task must NOT be included
        $this->assertStringNotContainsString('2.TYPH-0202 : Battery comparison graphic', $csv);
    }

    public function test_tasks_not_duplicated_across_synced_boards(): void
    {
        $dara = User::factory()->create([
            'name' => 'Mr. Dara (Head)',
            'username' => 'dara_sync',
            'email' => 'dara_sync@test.com',
            'is_active' => true,
        ]);
        $dara->assignRole('admin-digital');

        $workspace = Workspace::create(['name' => 'Digital Dept', 'owner_id' => $dara->id, 'is_active' => true]);
        $workflowBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board Team A – October 2026', 'created_by' => $dara->id]);
        $planningBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'Planning board Team A – October 2026', 'type' => 'planning', 'created_by' => $dara->id]);
        
        $list1 = BoardList::create(['board_id' => $workflowBoard->id, 'name' => 'Approved', 'position' => 1]);
        $list2 = BoardList::create(['board_id' => $planningBoard->id, 'name' => 'Approved', 'position' => 1]);

        $syncGroupId = 'sync-group-test-uuid-999';

        $card1 = Card::create([
            'board_id' => $workflowBoard->id,
            'board_list_id' => $list1->id,
            'title' => 'Unique Synced Task Title 888',
            'team' => 'A',
            'sync_group_id' => $syncGroupId,
            'created_by' => $dara->id,
        ]);
        $card1->comments()->create([
            'user_id' => $dara->id,
            'content' => 'Production approved',
            'is_system' => false,
        ]);

        $card2 = Card::create([
            'board_id' => $planningBoard->id,
            'board_list_id' => $list2->id,
            'title' => 'Unique Synced Task Title 888',
            'team' => 'A',
            'sync_group_id' => $syncGroupId,
            'created_by' => $dara->id,
        ]);
        $card2->comments()->create([
            'user_id' => $dara->id,
            'content' => 'Production approved',
            'is_system' => false,
        ]);

        $response = $this->actingAs($dara)->get("/boards/personal-report/export?is_personal_report=1&report_type=kanban&board_ids[]={$workflowBoard->id}&board_ids[]={$planningBoard->id}&format=raw_csv");
        $response->assertOk();
        $csv = $response->streamedContent();

        // Must appear exactly once in the export
        $this->assertEquals(1, substr_count($csv, 'Unique Synced Task Title 888'), 'Task must not be duplicated across synced boards');
    }

    public function test_dara_personal_report_includes_normal_card_checklist_approved_and_error_tasks(): void
    {
        $dara = User::factory()->create([
            'name' => 'Mr. Dara (Head)',
            'username' => 'dara_norm',
            'email' => 'dara_norm@test.com',
            'is_active' => true,
        ]);
        $dara->assignRole('admin-digital');

        $workspace = Workspace::create(['name' => 'Digital Dept', 'owner_id' => $dara->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board Team A – October 2026', 'created_by' => $dara->id]);
        $list = BoardList::create(['board_id' => $board->id, 'name' => 'In Progress', 'position' => 1]);

        // Normal card (non-SMM) with checklist items
        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'PRJ: KUVUO WITH BUNDLE',
            'team' => 'A',
            'created_by' => $dara->id,
        ]);

        $chk = $card->checklists()->create(['title' => 'Tasks Checklist', 'position' => 1]);
        $itemGraphic = $chk->items()->create([
            'content' => 'Graphic',
            'position' => 1,
            'is_marked' => true,
            'marked_by' => $dara->id,
            'marked_at' => now(),
        ]);
        $itemVideo = $chk->items()->create([
            'content' => 'Video',
            'position' => 2,
            'has_issue' => true,
            'issue_by' => $dara->id,
            'issue_at' => now(),
        ]);
        $itemDesc = $chk->items()->create([
            'content' => 'Description',
            'position' => 3,
            'is_completed' => false,
        ]);

        $response = $this->actingAs($dara)->get("/boards/personal-report/export?is_personal_report=1&report_type=kanban&board_ids[]={$board->id}&date_range=today&format=raw_csv");
        $response->assertOk();
        $csv = $response->streamedContent();

        // 1. Dara approved Graphic -> must be detected as "PRJ: KUVUO WITH BUNDLE - Graphic"
        $this->assertStringContainsString('PRJ: KUVUO WITH BUNDLE - Graphic', $csv);
        // 2. Dara flagged error on Video -> must be detected as "PRJ: KUVUO WITH BUNDLE - Video"
        $this->assertStringContainsString('PRJ: KUVUO WITH BUNDLE - Video', $csv);
        // 3. Unreviewed Description -> must NOT be in Dara's report
        $this->assertStringNotContainsString('PRJ: KUVUO WITH BUNDLE - Description', $csv);
    }

    public function test_standard_member_personal_report_includes_normal_card_checklist_ticked_task(): void
    {
        $vouchky = User::factory()->create([
            'name' => 'Ms. Vouchky',
            'username' => 'vouchky',
            'email' => 'vouchky@test.com',
            'is_active' => true,
        ]);
        $vouchky->assignRole('digital-team');

        $samnang = User::factory()->create([
            'name' => 'Mr. Samnang',
            'username' => 'samnang',
            'email' => 'samnang@test.com',
            'is_active' => true,
        ]);
        $samnang->assignRole('digital-team');

        $workspace = Workspace::create(['name' => 'Digital Dept', 'owner_id' => $vouchky->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board Team A – October 2026', 'created_by' => $vouchky->id]);
        $list = BoardList::create(['board_id' => $board->id, 'name' => 'In Progress', 'position' => 1]);

        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'PRJ: KUVUO WITH BUNDLE',
            'created_by' => $vouchky->id,
        ]);

        $chk = $card->checklists()->create(['title' => 'Production Tasks', 'position' => 1]);
        $itemGraphic = $chk->items()->create([
            'content' => 'Graphic',
            'is_completed' => true,
            'completed_by' => $vouchky->id,
            'assigned_user_id' => $vouchky->id,
            'completed_at' => now(),
            'position' => 1,
        ]);
        $itemVideo = $chk->items()->create([
            'content' => 'Video',
            'is_completed' => true,
            'completed_by' => $samnang->id,
            'assigned_user_id' => $samnang->id,
            'completed_at' => now(),
            'position' => 2,
        ]);

        // Export Vouchky's report
        $responseVouchky = $this->actingAs($vouchky)->get("/boards/{$board->slug}/export/csv?member_id={$vouchky->id}&raw=1");
        $responseVouchky->assertOk();
        $csvVouchky = $responseVouchky->streamedContent();

        $this->assertStringContainsString('PRJ: KUVUO WITH BUNDLE - Graphic', $csvVouchky);
        $this->assertStringNotContainsString('PRJ: KUVUO WITH BUNDLE - Video', $csvVouchky);

        // Export Samnang's report
        $responseSamnang = $this->actingAs($samnang)->get("/boards/{$board->slug}/export/csv?member_id={$samnang->id}&raw=1");
        $responseSamnang->assertOk();
        $csvSamnang = $responseSamnang->streamedContent();

        $this->assertStringContainsString('PRJ: KUVUO WITH BUNDLE - Video', $csvSamnang);
        $this->assertStringNotContainsString('PRJ: KUVUO WITH BUNDLE - Graphic', $csvSamnang);
    }
}

