<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardAutomation;
use App\Models\BoardList;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BoardTemplateAndCopyTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $workspace;
    protected $templateBoard;
    protected $memberUser1;
    protected $memberUser2;

    protected function setUp(): void
    {
        parent::setUp();

        // Run roles and permissions seeder
        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole('super-admin');

        $this->workspace = Workspace::create([
            'name' => 'Template Workspace',
            'owner_id' => $this->user->id,
            'is_active' => true,
        ]);

        // Create a template board representing a Workflow board
        $this->templateBoard = Board::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Workflow board - June',
            'created_by' => $this->user->id,
            'background_type' => 'color',
            'background_value' => '#4f46e5',
        ]);

        // Create lists on template board
        $list1 = BoardList::create([
            'board_id' => $this->templateBoard->id,
            'name' => 'Drafting',
            'position' => 0,
            'color' => '#ff0000',
            'wip_limit' => 5,
        ]);

        $list2 = BoardList::create([
            'board_id' => $this->templateBoard->id,
            'name' => 'Head Review',
            'position' => 1,
            'color' => '#00ff00',
            'wip_limit' => 3,
        ]);

        // Create automation on template board
        BoardAutomation::create([
            'board_id' => $this->templateBoard->id,
            'trigger_type' => 'move_to_list',
            'trigger_word' => 'approved',
            'trigger_board_id' => $this->templateBoard->id,
            'trigger_list_id' => $list1->id,
            'target_board_id' => $this->templateBoard->id,
            'target_list_id' => $list2->id,
        ]);

        // Create additional users for members testing
        $this->memberUser1 = User::factory()->create(['is_active' => true]);
        $this->memberUser2 = User::factory()->create(['is_active' => true]);

        // Add them to workspace members
        $this->workspace->members()->attach($this->memberUser1->id, ['role' => 'member']);
        $this->workspace->members()->attach($this->memberUser2->id, ['role' => 'member']);

        // Add them to template board
        $this->templateBoard->members()->attach($this->memberUser1->id, ['role' => 'member']);
        $this->templateBoard->members()->attach($this->memberUser2->id, ['role' => 'admin']);
    }

    public function test_create_board_from_template(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('boards.store'), [
                'workspace_id' => $this->workspace->id,
                'name' => 'Workflow board - July',
                'background_type' => 'color',
                'background_value' => '#4f46e5',
                'visibility' => 'workspace',
                'template' => 'workflow',
                'template_month' => 'July',
                'template_year' => '2026',
            ]);

        // It should redirect to show board
        $response->assertStatus(302);

        $newBoard = Board::where('name', 'Workflow board - July')->firstOrFail();
        
        // Assert it copied lists correctly
        $this->assertCount(2, $newBoard->lists);
        $this->assertEquals('Drafting', $newBoard->lists[0]->name);
        $this->assertEquals('#ff0000', $newBoard->lists[0]->color);
        $this->assertEquals(5, $newBoard->lists[0]->wip_limit);

        $this->assertEquals('Head Review', $newBoard->lists[1]->name);
        $this->assertEquals(3, $newBoard->lists[1]->wip_limit);

        // Assert it copied and re-mapped the automation
        $automations = BoardAutomation::where('board_id', $newBoard->id)->get();
        $this->assertCount(1, $automations);

        $auto = $automations->first();
        $this->assertEquals($newBoard->id, $auto->trigger_board_id);
        $this->assertEquals($newBoard->id, $auto->target_board_id);
        $this->assertEquals($newBoard->lists[0]->id, $auto->trigger_list_id);
        $this->assertEquals($newBoard->lists[1]->id, $auto->target_list_id);

        // Assert it copied members correctly
        $this->assertCount(2, $newBoard->members);
        $this->assertTrue($newBoard->members->contains($this->memberUser1));
        $this->assertTrue($newBoard->members->contains($this->memberUser2));
        $this->assertEquals('member', $newBoard->members->firstWhere('id', $this->memberUser1->id)->pivot->role);
        $this->assertEquals('admin', $newBoard->members->firstWhere('id', $this->memberUser2->id)->pivot->role);
    }

    public function test_copy_board_re_maps_automations(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('boards.copy', $this->templateBoard), [
                'name' => 'Workflow board - July (Copy)',
                'include_cards' => false,
            ]);

        $response->assertStatus(201);
        $data = $response->json();
        $newBoardId = $data['board']['id'];

        $newBoard = Board::findOrFail($newBoardId);
        
        // Check lists copied
        $this->assertCount(2, $newBoard->lists);

        // Check automations re-mapped
        $automations = BoardAutomation::where('board_id', $newBoard->id)->get();
        $this->assertCount(1, $automations);

        $auto = $automations->first();
        $this->assertEquals($newBoard->id, $auto->trigger_board_id);
        $this->assertEquals($newBoard->id, $auto->target_board_id);
        $this->assertEquals($newBoard->lists[0]->id, $auto->trigger_list_id);
        $this->assertEquals($newBoard->lists[1]->id, $auto->target_list_id);

        // Assert it copied members correctly
        $this->assertCount(2, $newBoard->members);
        $this->assertTrue($newBoard->members->contains($this->memberUser1));
        $this->assertTrue($newBoard->members->contains($this->memberUser2));
    }

    public function test_template_retrieves_oldest_board_as_template(): void
    {
        // 1. First, create a board 'Workflow board - July' from the template.
        // It will clone the lists/automations/members from the original 'Workflow board - June'.
        $response1 = $this->actingAs($this->user)
            ->post(route('boards.store'), [
                'workspace_id' => $this->workspace->id,
                'name' => 'Workflow board - July',
                'background_type' => 'color',
                'background_value' => '#4f46e5',
                'visibility' => 'workspace',
                'template' => 'workflow',
                'template_month' => 'July',
                'template_year' => '2026',
            ]);
        $response1->assertStatus(302);
        
        $julyBoard = Board::where('name', 'Workflow board - July')->firstOrFail();
        $this->assertCount(2, $julyBoard->lists);

        // 2. Modify or empty the July board (e.g. delete lists/members) to simulate a user working on it.
        $julyBoard->lists()->delete();
        $julyBoard->members()->detach();
        $julyBoard->load(['lists', 'members']);
        $this->assertCount(0, $julyBoard->lists);
        $this->assertCount(0, $julyBoard->members);

        // 3. Create 'Workflow board - August' from template.
        // It should still copy from the original master template 'Workflow board - June' (which has 2 lists and 2 members), NOT the empty 'Workflow board - July'.
        $response2 = $this->actingAs($this->user)
            ->post(route('boards.store'), [
                'workspace_id' => $this->workspace->id,
                'name' => 'Workflow board - August',
                'background_type' => 'color',
                'background_value' => '#4f46e5',
                'visibility' => 'workspace',
                'template' => 'workflow',
                'template_month' => 'August',
                'template_year' => '2026',
            ]);
        $response2->assertStatus(302);

        $augustBoard = Board::where('name', 'Workflow board - August')->firstOrFail();

        // It should have copied the 2 lists and 2 members from the original template
        $this->assertCount(2, $augustBoard->lists);
        $this->assertCount(2, $augustBoard->members);
    }
}
