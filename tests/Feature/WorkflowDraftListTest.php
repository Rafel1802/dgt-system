<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkflowDraftListTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Board $board;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://localhost']);

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole('super-admin');

        $workspace = Workspace::create([
            'name' => 'Digital Department @ KiuQ',
            'owner_id' => $this->user->id,
            'is_active' => true,
        ]);

        // Team B board as it looks in production: the first "Draft" list is gone.
        $this->board = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'Workflow board Team B – November 2026',
            'created_by' => $this->user->id,
        ]);

        foreach (['Production Team B', 'Digital Department', 'Approved', 'Blocked/Waiting'] as $i => $name) {
            $this->board->lists()->create(['name' => $name, 'position' => $i]);
        }
    }

    public function test_missing_draft_list_is_recreated_as_first_list_when_board_opens(): void
    {
        $this->actingAs($this->user)->get(route('boards.show', $this->board->slug))->assertOk();

        $names = $this->board->lists()->pluck('name')->all();
        $this->assertSame(['Draft', 'Production Team B', 'Digital Department', 'Approved', 'Blocked/Waiting'], $names);
    }

    public function test_archived_or_deleted_draft_list_is_restored_with_its_cards(): void
    {
        $draft = $this->board->lists()->create(['name' => 'Draft', 'position' => 0, 'is_archived' => true]);
        $this->actingAs($this->user)->get(route('boards.show', $this->board->slug))->assertOk();
        $this->assertFalse($draft->fresh()->is_archived);

        $draft->delete();
        $this->actingAs($this->user)->get(route('boards.show', $this->board->slug))->assertOk();
        $this->assertNotNull(BoardList::find($draft->id));
    }

    public function test_draft_list_of_a_workflow_board_cannot_be_renamed_archived_or_deleted(): void
    {
        $draft = $this->board->lists()->create(['name' => 'Draft', 'position' => -1]);

        $this->actingAs($this->user)->patchJson(route('boards.lists.update', $draft->id), ['name' => 'Production Team B'])->assertStatus(422);
        $this->actingAs($this->user)->patchJson(route('boards.lists.update', $draft->id), ['is_archived' => true])->assertStatus(422);
        $this->actingAs($this->user)->deleteJson(route('boards.lists.destroy', $draft->id))->assertStatus(422);

        $this->assertSame('Draft', $draft->fresh()->name);
        $this->assertFalse($draft->fresh()->is_archived);

        // Other lists are still editable
        $other = $this->board->lists()->where('name', 'Approved')->first();
        $this->actingAs($this->user)->patchJson(route('boards.lists.update', $other->id), ['name' => 'Approved ✓'])->assertOk();
    }
}

