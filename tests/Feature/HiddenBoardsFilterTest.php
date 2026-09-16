<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HiddenBoardsFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://localhost']);

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole('super-admin');

        $this->workspace = Workspace::create([
            'name' => 'GraphicTeam@KiuQ',
            'owner_id' => $this->user->id,
            'is_active' => true,
        ]);
    }

    public function test_hidden_boards_modal_renders_search_and_month_filter(): void
    {
        // Create 2 hidden boards
        $augBoard = Board::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Planning board – August 2026',
            'created_by' => $this->user->id,
            'is_hidden' => true,
            'created_at' => '2026-08-01 10:00:00',
        ]);

        $sepBoard = Board::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Workflow board – September 2026',
            'created_by' => $this->user->id,
            'is_hidden' => true,
            'created_at' => '2026-09-01 10:00:00',
        ]);

        $response = $this->actingAs($this->user)->get(route('boards.workspaces'));

        $response->assertOk();
        $response->assertSee('x-data="hiddenBoardsManager()"', false);
        $response->assertSee('x-model="search"', false);
        $response->assertSee('x-model="selectedMonth"', false);
        $response->assertSee('All Months', false);
        $response->assertSee('August (1)', false);
        $response->assertSee('September (1)', false);
        $response->assertSee('data-name="Planning board – August 2026"', false);
        $response->assertSee('data-name="Workflow board – September 2026"', false);
        $response->assertSee('isItemVisible', false);
    }

    public function test_hidden_boards_modal_renders_empty_state_when_no_hidden_boards(): void
    {
        $response = $this->actingAs($this->user)->get(route('boards.workspaces'));

        $response->assertOk();
        $response->assertSee('No Hidden Boards', false);
        $response->assertDontSee('placeholder="Search hidden boards or workspaces..."', false);
    }

    public function test_hidden_boards_modal_handles_month_abbreviations_and_badges(): void
    {
        Board::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Marketing – Sep 2026',
            'created_by' => $this->user->id,
            'is_hidden' => true,
            'created_at' => '2026-08-15 10:00:00', // created in August, but for September
        ]);

        $response = $this->actingAs($this->user)->get(route('boards.workspaces'));

        $response->assertOk();
        // Should detect September from "Sep" in the name
        $response->assertSee('September (1)', false);
        $response->assertSee('data-month="September"', false);
        $response->assertSee('data-year="2026"', false);
        $response->assertSee('September 2026', false);
    }
}
