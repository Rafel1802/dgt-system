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

class BoardSelectModeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;
    protected Board $board;
    protected BoardList $list;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole('super-admin');

        $this->workspace = Workspace::create([
            'name' => 'Test Workspace',
            'owner_id' => $this->user->id,
            'is_active' => true,
        ]);

        $this->board = Board::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Weekly Board',
            'slug' => 'weekly-board',
            'created_by' => $this->user->id,
        ]);

        $this->list = BoardList::create([
            'board_id' => $this->board->id,
            'name' => 'Week 1',
            'position' => 0,
        ]);

        Card::create([
            'board_list_id' => $this->list->id,
            'title' => 'Sample Card 1',
            'position' => 0,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_board_view_renders_select_actions_and_script_has_start_select_mode(): void
    {
        $response = $this->actingAs($this->user)->get(route('boards.show', $this->board->slug));

        $response->assertOk();
        $response->assertSee('startSelectMode()', false);
        $response->assertSee('selectAllInList(list.id)', false);
        $response->assertSee('isSelectMode', false);
        $response->assertSee('selectedCards', false);

        // Verify JS contains startSelectMode definition
        $jsContent = file_get_contents(public_path('js/trello-board.js'));
        $this->assertStringContainsString('startSelectMode(', $jsContent);
        $this->assertStringContainsString('toggleSelectMode(', $jsContent);
    }
}
