<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CardTeamAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $dara;
    private User $kim;
    private Board $board;
    private BoardList $list;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin-digital', 'guard_name' => 'web']);

        $this->dara = User::factory()->create([
            'name' => 'Mr. Dara (Head)',
            'username' => 'dara',
            'is_active' => true,
        ]);
        $this->dara->assignRole('admin-digital');

        $this->kim = User::factory()->create([
            'name' => 'Mr. Kim (Head)',
            'username' => 'kim',
            'is_active' => true,
        ]);
        $this->kim->assignRole('admin-digital');

        $workspace = Workspace::create(['name' => 'Digital WS', 'owner_id' => $this->dara->id, 'is_active' => true]);
        $this->board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Planning Board – November 2026', 'created_by' => $this->dara->id]);
        $this->list = BoardList::create(['board_id' => $this->board->id, 'name' => 'Draft', 'position' => 0]);
    }

    public function test_card_model_persists_both_teams_without_creator_override(): void
    {
        $cardDara = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Joint Project Dara',
            'created_by' => $this->dara->id,
            'team' => 'Both',
            'status' => 'todo',
            'priority' => 'medium',
            'position' => 0,
        ]);

        $this->assertEquals('Both', $cardDara->fresh()->team);
        $this->assertTrue($cardDara->isBothTeams());
        $this->assertTrue($cardDara->hasTeam('A'));
        $this->assertTrue($cardDara->hasTeam('B'));

        $cardKim = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Joint Project Kim',
            'created_by' => $this->kim->id,
            'team' => 'Both',
            'status' => 'todo',
            'priority' => 'medium',
            'position' => 1,
        ]);

        $this->assertEquals('Both', $cardKim->fresh()->team);
        $this->assertTrue($cardKim->isBothTeams());
        $this->assertTrue($cardKim->hasTeam('A'));
        $this->assertTrue($cardKim->hasTeam('B'));
    }

    public function test_card_api_patch_team_both_and_single_and_remove(): void
    {
        $syncGroupId = (string) Str::uuid();

        $card = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'API Card Test',
            'created_by' => $this->dara->id,
            'team' => 'A',
            'sync_group_id' => $syncGroupId,
            'status' => 'todo',
            'priority' => 'medium',
            'position' => 0,
        ]);

        $twinCard = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Twin Card Test',
            'created_by' => $this->dara->id,
            'team' => 'A',
            'sync_group_id' => $syncGroupId,
            'status' => 'todo',
            'priority' => 'medium',
            'position' => 1,
        ]);

        $url = 'http://localhost' . route('boards.cards.update', $card, false);

        // 1. Change team to 'Both' (Team A & B JOINT)
        $response = $this->actingAs($this->dara)->patchJson($url, ['team' => 'Both']);
        $response->assertOk();
        $response->assertJsonPath('card.team', 'Both');
        $this->assertEquals('Both', $card->fresh()->team);
        $this->assertTrue($card->fresh()->isBothTeams());
        // Verify twin card also got synced to 'Both'
        $this->assertEquals('Both', $twinCard->fresh()->team);
        $this->assertTrue($twinCard->fresh()->isBothTeams());

        // 2. Change team to 'B'
        $response = $this->actingAs($this->dara)->patchJson($url, ['team' => 'B']);
        $response->assertOk();
        $response->assertJsonPath('card.team', 'B');
        $this->assertEquals('B', $card->fresh()->team);
        $this->assertFalse($card->fresh()->isBothTeams());
        $this->assertEquals('B', $twinCard->fresh()->team);

        // 3. Remove team assignment (null or 'none')
        $response = $this->actingAs($this->dara)->patchJson($url, ['team' => null]);
        $response->assertOk();
        $response->assertJsonPath('card.team', null);
        $this->assertNull($card->fresh()->team);
    }
}
