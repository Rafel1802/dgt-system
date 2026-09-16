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

class SmmCardSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $smmWorkspace;
    protected Workspace $graphicWorkspace;
    protected Workspace $videoWorkspace;
    protected Workspace $listingWorkspace;
    protected Workspace $qcWorkspace;

    protected Board $smmBoard;
    protected Board $graphicBoard;
    protected Board $videoBoard;
    protected Board $listingBoard;
    protected Board $qcBoard;

    protected BoardList $smmWeek1;
    protected BoardList $smmWeek2;
    protected BoardList $graphicWeek1;
    protected BoardList $graphicWeek2;
    protected BoardList $videoWeek1;
    protected BoardList $listingWeek1;
    protected BoardList $qcWeek1;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole('super-admin');

        // Setup workspaces
        $this->smmWorkspace = Workspace::create(['name' => 'Social Media Management', 'owner_id' => $this->user->id]);
        $this->graphicWorkspace = Workspace::create(['name' => 'GraphicTeam@KiuQ', 'owner_id' => $this->user->id]);
        $this->videoWorkspace = Workspace::create(['name' => 'VideoTeam@KiuQ', 'owner_id' => $this->user->id]);
        $this->listingWorkspace = Workspace::create(['name' => 'Listing-ContenWriter-Team@KiuQ', 'owner_id' => $this->user->id]);
        $this->qcWorkspace = Workspace::create(['name' => 'QC/Technical@kiuQ', 'owner_id' => $this->user->id]);

        // Setup boards
        $this->smmBoard = Board::create([
            'workspace_id' => $this->smmWorkspace->id,
            'name' => 'SMM Planning Board – July 2026',
            'type' => 'smm',
            'created_by' => $this->user->id,
        ]);

        $this->graphicBoard = Board::create([
            'workspace_id' => $this->graphicWorkspace->id,
            'name' => 'Planning board – August 2026',
            'type' => 'kanban',
            'created_by' => $this->user->id,
        ]);

        $this->videoBoard = Board::create([
            'workspace_id' => $this->videoWorkspace->id,
            'name' => 'Planning board – August 2026',
            'type' => 'kanban',
            'created_by' => $this->user->id,
        ]);

        $this->listingBoard = Board::create([
            'workspace_id' => $this->listingWorkspace->id,
            'name' => 'Planning board – August 2026',
            'type' => 'kanban',
            'created_by' => $this->user->id,
        ]);

        $this->qcBoard = Board::create([
            'workspace_id' => $this->qcWorkspace->id,
            'name' => 'Planning board – August 2026',
            'type' => 'kanban',
            'created_by' => $this->user->id,
        ]);

        // Setup lists
        $this->smmWeek1 = BoardList::create(['board_id' => $this->smmBoard->id, 'name' => 'Week 1', 'position' => 1]);
        $this->smmWeek2 = BoardList::create(['board_id' => $this->smmBoard->id, 'name' => 'Week 2', 'position' => 2]);

        $this->graphicWeek1 = BoardList::create(['board_id' => $this->graphicBoard->id, 'name' => 'Week 1', 'position' => 1]);
        $this->graphicWeek2 = BoardList::create(['board_id' => $this->graphicBoard->id, 'name' => 'Week 2', 'position' => 2]);

        $this->videoWeek1 = BoardList::create(['board_id' => $this->videoBoard->id, 'name' => 'Week 1', 'position' => 1]);
        $this->listingWeek1 = BoardList::create(['board_id' => $this->listingBoard->id, 'name' => 'Week 1', 'position' => 1]);
        $this->qcWeek1 = BoardList::create(['board_id' => $this->qcBoard->id, 'name' => 'Week 1', 'position' => 1]);
    }

    public function test_poster_design_card_auto_syncs_to_graphic_team(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('boards.cards.store', $this->smmBoard->slug), [
            'board_list_id' => $this->smmWeek1->id,
            'title' => 'TYPH-0503G + TYPH-5005M SMM Content - Poster Design',
        ]);

        $response->assertCreated();
        $cardId = $response->json('card.id');
        $card = Card::find($cardId);

        $this->assertNotNull($card);
        $this->assertSame('Graphic Team', $card->smm_team_label);
        $this->assertNotEmpty($card->sync_group_id);

        // Verify twin card was created on Graphic Team board in Week 1
        $twin = Card::where('sync_group_id', $card->sync_group_id)
            ->where('board_id', $this->graphicBoard->id)
            ->first();

        $this->assertNotNull($twin);
        $this->assertSame($this->graphicWeek1->id, $twin->board_list_id);
        $this->assertSame('TYPH-0503G + TYPH-5005M SMM Content - Poster Design', $twin->title);
    }

    public function test_short_reel_and_long_landscape_cards_auto_sync_to_video_team(): void
    {
        // 1. Long landscape
        $res1 = $this->actingAs($this->user)->postJson(route('boards.cards.store', $this->smmBoard->slug), [
            'board_list_id' => $this->smmWeek1->id,
            'title' => 'TYPH-1702proB + TYPH-PA01 SMM Content - Long Landscape',
        ]);
        $res1->assertCreated();
        $card1 = Card::find($res1->json('card.id'));
        $this->assertSame('Video Team', $card1->smm_team_label);

        $twin1 = Card::where('sync_group_id', $card1->sync_group_id)
            ->where('board_id', $this->videoBoard->id)
            ->first();
        $this->assertNotNull($twin1);

        // 2. Short reel
        $res2 = $this->actingAs($this->user)->postJson(route('boards.cards.store', $this->smmBoard->slug), [
            'board_list_id' => $this->smmWeek1->id,
            'title' => 'TYPH-0115 SMM Content - Short Reel',
        ]);
        $res2->assertCreated();
        $card2 = Card::find($res2->json('card.id'));
        $this->assertSame('Video Team', $card2->smm_team_label);

        $twin2 = Card::where('sync_group_id', $card2->sync_group_id)
            ->where('board_id', $this->videoBoard->id)
            ->first();
        $this->assertNotNull($twin2);
    }

    public function test_share_blog_auto_syncs_to_listing_content_team(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('boards.cards.store', $this->smmBoard->slug), [
            'board_list_id' => $this->smmWeek1->id,
            'title' => 'Share Blog: New machinery industry article',
        ]);

        $response->assertCreated();
        $card = Card::find($response->json('card.id'));
        $this->assertSame('Listing Team', $card->smm_team_label);

        $twin = Card::where('sync_group_id', $card->sync_group_id)
            ->where('board_id', $this->listingBoard->id)
            ->first();
        $this->assertNotNull($twin);
    }

    public function test_qc_prefix_auto_syncs_to_qc_team(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('boards.cards.store', $this->smmBoard->slug), [
            'board_list_id' => $this->smmWeek1->id,
            'title' => '[QC] Check specifications for TYPH-0503G',
        ]);

        $response->assertCreated();
        $card = Card::find($response->json('card.id'));
        $this->assertSame('QC Team', $card->smm_team_label);

        $twin = Card::where('sync_group_id', $card->sync_group_id)
            ->where('board_id', $this->qcBoard->id)
            ->first();
        $this->assertNotNull($twin);
    }

    public function test_moving_card_between_weeks_on_smm_board_syncs_twin_on_team_board(): void
    {
        // Create card in Week 1
        $res = $this->actingAs($this->user)->postJson(route('boards.cards.store', $this->smmBoard->slug), [
            'board_list_id' => $this->smmWeek1->id,
            'title' => 'TYPH-0503G SMM Content - Poster Design',
        ]);
        $card = Card::find($res->json('card.id'));

        $twin = Card::where('sync_group_id', $card->sync_group_id)
            ->where('board_id', $this->graphicBoard->id)
            ->first();
        $this->assertSame($this->graphicWeek1->id, $twin->board_list_id);

        // Move to Week 2 on SMM board
        $updateRes = $this->actingAs($this->user)->patchJson(route('boards.cards.update', $card->id), [
            'board_list_id' => $this->smmWeek2->id,
        ]);
        $updateRes->assertOk();

        // Check twin also moved to Week 2 on Graphic board
        $twin->refresh();
        $this->assertSame($this->graphicWeek2->id, $twin->board_list_id);
    }

    public function test_board_view_does_not_contain_sync_to_chips(): void
    {
        $response = $this->actingAs($this->user)->get(route('boards.show', $this->smmBoard->slug));
        $response->assertOk();
        $response->assertDontSee('Sync to:');
    }

    public function test_content_writing_card_syncs_to_content_writing_team_and_does_not_attach_listing_label(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('boards.cards.store', $this->smmBoard->slug), [
            'board_list_id' => $this->smmWeek1->id,
            'title' => 'Blog Writing: New Industry Trends',
            'smm_team_label' => 'Content Writing Team',
        ]);

        $response->assertCreated();
        $card = Card::with('labels')->find($response->json('card.id'));
        $this->assertSame('Content Writing Team', $card->smm_team_label);

        $labels = $card->labels->pluck('name')->all();
        $this->assertContains('Content Writing Team', $labels);
        $this->assertNotContains('Listing Team', $labels);
    }

    public function test_toggling_member_on_smm_board_syncs_assignee_to_team_board_and_vice_versa(): void
    {
        $member = User::factory()->create(['name' => 'Pich Designer', 'is_active' => true]);

        // Create card on SMM board (auto-syncs to Graphic board)
        $res = $this->actingAs($this->user)->postJson(route('boards.cards.store', $this->smmBoard->slug), [
            'board_list_id' => $this->smmWeek1->id,
            'title' => 'TYPH-0503G Content - Poster Design',
        ]);
        $res->assertCreated();
        $smmCard = Card::find($res->json('card.id'));

        $graphicTwin = Card::where('sync_group_id', $smmCard->sync_group_id)
            ->where('board_id', $this->graphicBoard->id)
            ->first();
        $this->assertNotNull($graphicTwin);

        // 1. Assign member on SMM card
        $assignRes = $this->actingAs($this->user)->postJson(route('boards.cards.members', $smmCard->id), [
            'user_id' => $member->id,
        ]);
        $assignRes->assertOk();

        // Check twin card has member
        $graphicTwin->refresh();
        $this->assertTrue($graphicTwin->assignees->contains('id', $member->id));

        // 2. Remove member from Graphic twin card
        $removeRes = $this->actingAs($this->user)->postJson(route('boards.cards.members', $graphicTwin->id), [
            'user_id' => $member->id,
        ]);
        $removeRes->assertOk();

        // Check SMM card has member removed
        $smmCard->refresh();
        $this->assertFalse($smmCard->assignees->contains('id', $member->id));
    }

    public function test_resolve_member_distinguishes_pich_from_srey_pich(): void
    {
        $sreyPich = User::factory()->create(['name' => 'Srey Pich', 'username' => 'sreypich', 'is_active' => true]);
        $pich = User::factory()->create(['name' => 'Pich', 'username' => 'pich', 'is_active' => true]);

        $controller = app(\App\Http\Controllers\SocialMedia\SmmImportController::class);
        $method = new \ReflectionMethod($controller, 'buildUserLookup');
        $method->setAccessible(true);
        $lookup = $method->invoke($controller);

        $resolveMethod = new \ReflectionMethod($controller, 'resolveMember');
        $resolveMethod->setAccessible(true);

        // "Pich" should resolve to Pich ($pich->id), NOT Srey Pich ($sreyPich->id)
        $resPich = $resolveMethod->invoke($controller, 'Pich', $lookup);
        $this->assertSame($pich->id, $resPich['id']);
        $this->assertSame('Pich', $resPich['resolved_name']);

        // "Srey Pich" should resolve to Srey Pich
        $resSreyPich = $resolveMethod->invoke($controller, 'Srey Pich', $lookup);
        $this->assertSame($sreyPich->id, $resSreyPich['id']);

        // "Sreypich" (no space) should also resolve to Srey Pich
        $resNormalized = $resolveMethod->invoke($controller, 'Sreypich', $lookup);
        $this->assertSame($sreyPich->id, $resNormalized['id']);
    }

    public function test_existing_card_import_syncs_assignees_and_assign_by_to_team_twins(): void
    {
        $creator = User::factory()->create(['name' => 'Srey Pich', 'is_active' => true]);
        $assignee = User::factory()->create(['name' => 'Pich Designer', 'is_active' => true]);

        // Create card on SMM board (auto-syncs to Graphic board)
        $res = $this->actingAs($this->user)->postJson(route('boards.cards.store', $this->smmBoard->slug), [
            'board_list_id' => $this->smmWeek1->id,
            'title' => 'TYPH-0503G - Poster Design',
            'content_public_date' => '2026-08-10',
        ]);
        $res->assertCreated();
        $smmCard = Card::find($res->json('card.id'));

        $graphicTwin = Card::where('sync_group_id', $smmCard->sync_group_id)
            ->where('board_id', $this->graphicBoard->id)
            ->first();
        $this->assertNotNull($graphicTwin);
        $this->assertEmpty($graphicTwin->assignees);

        // Confirm import payload targeting the same card
        $importPayload = [
            'rows' => [
                [
                    'valid' => true,
                    'title' => 'TYPH-0503G - Poster Design',
                    'content_public_date' => '2026-08-10',
                    'start_date' => null,
                    'smm_class_label' => 'ImpossibleMachinery',
                    'smm_cluster_label' => 'Poster Design',
                    'smm_team_label' => 'Graphic Team',
                    'description' => 'Updated via spreadsheet import',
                    'deadline' => '2026-08-08 12:00:00',
                    'due_time' => '12:00',
                    'assign_to_raw' => 'Pich Designer',
                    'assign_by_raw' => 'Srey Pich',
                    'list_id' => $this->smmWeek1->id,
                    'worksheet' => 'Week 1',
                ]
            ]
        ];

        $confirmRes = $this->actingAs($this->user)->postJson(route('smm-boards.import.confirm', $this->smmBoard->slug), $importPayload);
        $confirmRes->assertOk();

        // Refresh twin card on Graphic board
        $graphicTwin->refresh();
        $this->assertSame($creator->id, $graphicTwin->created_by);
        $this->assertTrue($graphicTwin->assignees->contains('id', $assignee->id));

        // Refresh SMM card
        $smmCard->refresh();
        $this->assertSame($creator->id, $smmCard->created_by);
        $this->assertTrue($smmCard->assignees->contains('id', $assignee->id));
    }

    public function test_sync_fix_members_artisan_command_repairs_mismatched_twins(): void
    {
        $creator = User::factory()->create(['name' => 'Supervisor User', 'is_active' => true]);
        $wrongCreator = User::factory()->create(['name' => 'Wrong User', 'is_active' => true]);
        $member = User::factory()->create(['name' => 'Worker User', 'is_active' => true]);
        $syncGroupId = (string)\Illuminate\Support\Str::uuid();

        // Create card on SMM board with member and creator
        $smmCard = Card::create([
            'board_id' => $this->smmBoard->id,
            'board_list_id' => $this->smmWeek1->id,
            'title' => 'Mismatched Card Test',
            'sync_group_id' => $syncGroupId,
            'created_by' => $creator->id,
            'status' => 'todo',
        ]);
        $smmCard->assignees()->attach($member->id, ['assigned_at' => now()]);

        // Create twin on Graphic board with missing member and wrong creator
        $graphicTwin = Card::create([
            'board_id' => $this->graphicBoard->id,
            'board_list_id' => $this->graphicWeek1->id,
            'title' => 'Mismatched Card Test',
            'sync_group_id' => $syncGroupId,
            'created_by' => $wrongCreator->id, // wrong creator
            'status' => 'todo',
        ]);

        $this->assertEmpty($graphicTwin->assignees);
        $this->assertNotSame($creator->id, $graphicTwin->created_by);

        // Run artisan repair command
        $this->artisan('sync:fix-members')->assertSuccessful();

        $graphicTwin->refresh();
        $this->assertSame($creator->id, $graphicTwin->created_by);
        $this->assertTrue($graphicTwin->assignees->contains('id', $member->id));
    }
}
