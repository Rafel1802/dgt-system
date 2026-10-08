<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlanningBoardTeamFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin-digital', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'boss', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'digital-team', 'guard_name' => 'web']);
    }

    public function test_can_filter_all_planning_teams_method_identifies_dara_kim_somalika_and_admin_digital(): void
    {
        $dara = User::factory()->create(['username' => 'dara', 'team' => 'A']);
        $this->assertTrue($dara->canFilterAllPlanningTeams());

        $kim = User::factory()->create(['username' => 'kim', 'team' => 'B']);
        $this->assertTrue($kim->canFilterAllPlanningTeams());

        $somalika = User::factory()->create(['username' => 'somalika', 'team' => null]);
        $this->assertTrue($somalika->canFilterAllPlanningTeams());

        $adminDigital = User::factory()->create(['username' => 'random_admin']);
        $adminDigital->assignRole('admin-digital');
        $this->assertTrue($adminDigital->canFilterAllPlanningTeams());

        $regularMemberA = User::factory()->create(['username' => 'regular_member_a', 'team' => 'A']);
        $regularMemberA->assignRole('digital-team');
        $this->assertFalse($regularMemberA->canFilterAllPlanningTeams());

        $regularMemberB = User::factory()->create(['username' => 'regular_member_b', 'team' => 'B']);
        $regularMemberB->assignRole('digital-team');
        $this->assertFalse($regularMemberB->canFilterAllPlanningTeams());
    }

    public function test_planning_board_shows_all_teams_cards_to_dara_kim_somalika_and_admin_digital(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $workspace = Workspace::create([
            'name' => 'Digital Media',
            'owner_id' => $admin->id,
            'is_active' => true,
        ]);

        $planningBoard = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'Digital Planning Board - September 2026',
            'created_by' => $admin->id,
            'is_archived' => false,
            'is_hidden' => false,
        ]);

        $list = BoardList::create([
            'board_id' => $planningBoard->id,
            'name' => 'Week 1',
            'position' => 1000,
        ]);

        // Card for Team A
        $cardA = Card::create([
            'board_id' => $planningBoard->id,
            'board_list_id' => $list->id,
            'title' => 'Team A Graphic Banner',
            'team' => 'A',
            'created_by' => $admin->id,
            'is_archived' => false,
        ]);

        // Card for Team B
        $cardB = Card::create([
            'board_id' => $planningBoard->id,
            'board_list_id' => $list->id,
            'title' => 'Team B Video Reel',
            'team' => 'B',
            'created_by' => $admin->id,
            'is_archived' => false,
        ]);

        // Test Dara (Team A user who should see both teams)
        $dara = User::factory()->create(['username' => 'dara', 'team' => 'A', 'is_active' => true]);
        $dara->assignRole('digital-team');
        $planningBoard->members()->attach($dara->id, ['role' => 'member']);
        $workspace->members()->attach($dara->id, ['role' => 'member']);

        $responseDara = $this->actingAs($dara)->get(route('boards.show', $planningBoard->slug));
        $responseDara->assertOk();
        $boardDataDara = $responseDara->viewData('boardData');
        $this->assertTrue($boardDataDara['currentUser']['can_filter_all_teams']);
        $boardViewDara = $responseDara->viewData('board');
        $cardsDara = $boardViewDara->activeLists->first()->cards->pluck('id')->toArray();
        $this->assertContains($cardA->id, $cardsDara);
        $this->assertContains($cardB->id, $cardsDara);

        // Test Kim (Team B user who should see both teams)
        $kim = User::factory()->create(['username' => 'kim', 'team' => 'B', 'is_active' => true]);
        $kim->assignRole('digital-team');
        $planningBoard->members()->attach($kim->id, ['role' => 'member']);
        $workspace->members()->attach($kim->id, ['role' => 'member']);

        $responseKim = $this->actingAs($kim)->get(route('boards.show', $planningBoard->slug));
        $responseKim->assertOk();
        $boardDataKim = $responseKim->viewData('boardData');
        $this->assertTrue($boardDataKim['currentUser']['can_filter_all_teams']);
        $boardViewKim = $responseKim->viewData('board');
        $cardsKim = $boardViewKim->activeLists->first()->cards->pluck('id')->toArray();
        $this->assertContains($cardA->id, $cardsKim);
        $this->assertContains($cardB->id, $cardsKim);

        // Test Somalika (Supervisor)
        $somalika = User::factory()->create(['username' => 'somalika', 'is_active' => true]);
        $somalika->assignRole('supervisor');
        $planningBoard->members()->attach($somalika->id, ['role' => 'member']);
        $workspace->members()->attach($somalika->id, ['role' => 'member']);

        $responseSomalika = $this->actingAs($somalika)->get(route('boards.show', $planningBoard->slug));
        $responseSomalika->assertOk();
        $boardDataSomalika = $responseSomalika->viewData('boardData');
        $this->assertTrue($boardDataSomalika['currentUser']['can_filter_all_teams']);

        // Test Admin Digital
        $adminDigital = User::factory()->create(['username' => 'admin_digital_user', 'is_active' => true]);
        $adminDigital->assignRole('admin-digital');
        $planningBoard->members()->attach($adminDigital->id, ['role' => 'member']);
        $workspace->members()->attach($adminDigital->id, ['role' => 'member']);

        $responseAdminDigital = $this->actingAs($adminDigital)->get(route('boards.show', $planningBoard->slug));
        $responseAdminDigital->assertOk();
        $boardDataAdminDigital = $responseAdminDigital->viewData('boardData');
        $this->assertTrue($boardDataAdminDigital['currentUser']['can_filter_all_teams']);

        // Test Regular Member from Team A: must NOT see Team B card
        $regularA = User::factory()->create(['username' => 'regular_member_a', 'team' => 'A', 'is_active' => true]);
        $regularA->assignRole('digital-team');
        $planningBoard->members()->attach($regularA->id, ['role' => 'member']);
        $workspace->members()->attach($regularA->id, ['role' => 'member']);

        $responseRegular = $this->actingAs($regularA)->get(route('boards.show', $planningBoard->slug));
        $responseRegular->assertOk();
        $boardDataRegular = $responseRegular->viewData('boardData');
        $this->assertFalse($boardDataRegular['currentUser']['can_filter_all_teams']);
        $boardView = $responseRegular->viewData('board');
        $cardsRegular = $boardView->activeLists->first()->cards->pluck('id')->toArray();
        $this->assertContains($cardA->id, $cardsRegular);
        $this->assertNotContains($cardB->id, $cardsRegular);
    }

    public function test_board_snapshot_preserves_both_teams_and_assigned_cards_consistently_with_show(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $workspace = Workspace::create([
            'name' => 'Digital Media WS',
            'owner_id' => $admin->id,
            'is_active' => true,
        ]);

        $planningBoard = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'Digital Planning Board - October 2026',
            'created_by' => $admin->id,
            'is_archived' => false,
            'is_hidden' => false,
        ]);

        $list = BoardList::create([
            'board_id' => $planningBoard->id,
            'name' => 'Week 1',
            'position' => 1000,
        ]);

        $regularA = User::factory()->create(['username' => 'regular_member_a2', 'team' => 'A', 'is_active' => true]);
        $regularA->assignRole('digital-team');
        $planningBoard->members()->attach($regularA->id, ['role' => 'member']);
        $workspace->members()->attach($regularA->id, ['role' => 'member']);

        // Card for Team A
        $cardA = Card::create([
            'board_id' => $planningBoard->id,
            'board_list_id' => $list->id,
            'title' => 'Team A Card',
            'team' => 'A',
            'created_by' => $admin->id,
            'is_archived' => false,
        ]);

        // Card for Both teams
        $cardBoth = Card::create([
            'board_id' => $planningBoard->id,
            'board_list_id' => $list->id,
            'title' => 'Shared Card Both Teams',
            'team' => 'Both',
            'created_by' => $admin->id,
            'is_archived' => false,
        ]);

        // Card for Team B assigned to regularA
        $cardBAssigned = Card::create([
            'board_id' => $planningBoard->id,
            'board_list_id' => $list->id,
            'title' => 'Team B Card Assigned To A',
            'team' => 'B',
            'created_by' => $admin->id,
            'is_archived' => false,
        ]);
        $cardBAssigned->assignees()->attach($regularA->id);

        // Unassigned Card for Team B
        $cardB = Card::create([
            'board_id' => $planningBoard->id,
            'board_list_id' => $list->id,
            'title' => 'Team B Unassigned Card',
            'team' => 'B',
            'created_by' => $admin->id,
            'is_archived' => false,
        ]);

        // Check show() view
        $showRes = $this->actingAs($regularA)->get(route('boards.show', $planningBoard->slug));
        $showRes->assertOk();
        $boardView = $showRes->viewData('board');
        $showCardIds = $boardView->activeLists->first()->cards->pluck('id')->toArray();
        $this->assertContains($cardA->id, $showCardIds);
        $this->assertContains($cardBoth->id, $showCardIds);
        $this->assertContains($cardBAssigned->id, $showCardIds);
        $this->assertNotContains($cardB->id, $showCardIds);

        // Check snapshot route
        $snapshotRes = $this->actingAs($regularA)->get("/boards/{$planningBoard->slug}/snapshot");
        $snapshotRes->assertOk();
        $snapshotData = $snapshotRes->json();
        $snapshotCardIds = collect($snapshotData['lists'][0]['cards'])->pluck('id')->toArray();

        $this->assertContains($cardA->id, $snapshotCardIds, 'Snapshot must include Team A card for Team A user');
        $this->assertContains($cardBoth->id, $snapshotCardIds, 'Snapshot must include Both teams card for Team A user');
        $this->assertContains($cardBAssigned->id, $snapshotCardIds, 'Snapshot must include assigned card even if Team B');
        $this->assertNotContains($cardB->id, $snapshotCardIds, 'Snapshot must not include unassigned Team B card');
    }
}
