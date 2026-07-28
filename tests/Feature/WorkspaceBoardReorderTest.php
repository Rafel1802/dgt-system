<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkspaceBoardReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_boards_can_be_reordered_inside_their_workspace(): void
    {
        config(['app.url' => 'http://localhost']);
        $user = User::factory()->create(['is_active' => true]);
        Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
        $user->assignRole('super-admin');

        $workspace = Workspace::create([
            'name' => 'Content Team',
            'owner_id' => $user->id,
            'is_active' => true,
        ]);
        $planning = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'Planning',
            'created_by' => $user->id,
            'position' => 0,
        ]);
        $workflow = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'Workflow',
            'created_by' => $user->id,
            'position' => 1,
        ]);

        $this->actingAs($user)
            ->postJson('http://localhost' . route('boards.workspaces.boards.reorder', $workspace, false), [
                'order' => [$workflow->id, $planning->id],
            ])
            ->assertOk();

        $this->assertSame(0, $workflow->fresh()->position);
        $this->assertSame(1, $planning->fresh()->position);
    }
}
