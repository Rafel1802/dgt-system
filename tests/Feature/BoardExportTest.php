<?php

namespace Tests\Feature;

use App\Enums\CardPriority;
use App\Enums\CardStatus;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Label;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BoardExportTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $workspace;
    protected $board;
    protected $list;

    protected function setUp(): void
    {
        parent::setUp();

        // Run roles and permissions seeder
        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        // Create standard user and assign a role
        $this->user = User::factory()->create([
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');

        // Create workspace
        $this->workspace = Workspace::create([
            'name' => 'Test Workspace',
            'owner_id' => $this->user->id,
            'is_active' => true,
        ]);

        // Create board
        $this->board = Board::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Test Board',
            'created_by' => $this->user->id,
            'background_type' => 'color',
            'background_value' => '#4f46e5',
        ]);

        // Create board list
        $this->list = BoardList::create([
            'board_id' => $this->board->id,
            'name' => 'To Do',
            'position' => 1,
        ]);
    }

    public function test_csv_export_without_filters(): void
    {
        // Create some cards
        $card1 = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'First Task',
            'status' => CardStatus::Todo,
            'priority' => CardPriority::Medium,
            'created_by' => $this->user->id,
        ]);

        $card2 = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Second Task',
            'status' => CardStatus::Done,
            'priority' => CardPriority::High,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('boards.export.csv', [
                'board' => $this->board->slug,
                'statuses' => ['draft', 'completed'],
            ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');
        
        $content = $response->getContent();
        
        // Assert XLS contains column headers
        $this->assertStringContainsString('Task / Title', $content);
        $this->assertStringContainsString('Status', $content);
        $this->assertStringContainsString('Assigned Members', $content);
        
        // Assert XLS contains tasks data
        $this->assertStringContainsString('First Task', $content);
        $this->assertStringContainsString('Second Task', $content);
    }

    public function test_pdf_export_without_filters(): void
    {
        $card1 = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'First Task',
            'status' => CardStatus::Todo,
            'priority' => CardPriority::Medium,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('boards.export.pdf', [
                'board' => $this->board->slug,
                'statuses' => ['draft'],
            ]));

        $response->assertStatus(200);
        $response->assertViewIs('boards.export-pdf');
        $response->assertSee('Test Board');
        $response->assertSee('First Task');
        $response->assertSee('Total Tasks');
    }

    public function test_export_filters_by_status(): void
    {
        $todoCard = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Todo Task',
            'status' => CardStatus::Todo,
            'created_by' => $this->user->id,
        ]);

        $doneCard = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Done Task',
            'status' => CardStatus::Done,
            'created_by' => $this->user->id,
        ]);

        // Request only completed cards
        $response = $this->actingAs($this->user)
            ->get(route('boards.export.csv', [
                'board' => $this->board->slug,
                'statuses' => ['completed'],
            ]));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringNotContainsString('Todo Task', $content);
        $this->assertStringContainsString('Done Task', $content);
    }

    public function test_export_filters_by_member(): void
    {
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        $card1 = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Task for Member 1',
            'status' => CardStatus::Todo,
            'created_by' => $this->user->id,
        ]);
        $card1->assignees()->attach($member1->id, ['assigned_at' => now()]);

        $card2 = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Task for Member 2',
            'status' => CardStatus::Todo,
            'created_by' => $this->user->id,
        ]);
        $card2->assignees()->attach($member2->id, ['assigned_at' => now()]);

        $response = $this->actingAs($this->user)
            ->get(route('boards.export.csv', [
                'board' => $this->board->slug,
                'member_id' => $member1->id,
                'statuses' => ['draft'],
            ]));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('Task for Member 1', $content);
        $this->assertStringNotContainsString('Task for Member 2', $content);
    }

    public function test_export_filters_by_date_range(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 5, 12, 0, 0));

        // Card created today
        $cardToday = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Today Task',
            'status' => CardStatus::Todo,
            'created_by' => $this->user->id,
        ]);
        $cardToday->created_at = now();
        $cardToday->save();

        // Card created 2 months ago
        $cardOld = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Old Task',
            'status' => CardStatus::Todo,
            'created_by' => $this->user->id,
        ]);
        $cardOld->created_at = now()->subMonths(2);
        $cardOld->save();

        // Request with 'this_month' filter
        $response = $this->actingAs($this->user)
            ->get(route('boards.export.csv', [
                'board' => $this->board->slug,
                'date_range' => 'this_month',
                'statuses' => ['draft'],
            ]));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('Today Task', $content);
        $this->assertStringNotContainsString('Old Task', $content);

        Carbon::setTestNow(); // Reset test time
    }
}
