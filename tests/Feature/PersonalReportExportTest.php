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
}
