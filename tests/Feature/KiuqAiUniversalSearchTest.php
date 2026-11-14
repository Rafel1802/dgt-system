<?php

namespace Tests\Feature;

use App\Models\Ai\AiConversation;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\ProductionReport\ProductionReport;
use App\Models\ProductionReport\ProductionReportTeamMapping;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\KiuqAiToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KiuqAiUniversalSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $dara;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dara = User::factory()->create([
            'username' => 'dara',
            'name' => 'Dara (Head - Team A)',
            'email' => 'dara@kiuq.com',
            'team' => 'Team A',
            'team_role' => 'Head of Production Team A',
            'is_active' => true,
        ]);
    }

    public function test_smart_search_handles_train_ai_and_system_search_capabilities(): void
    {
        $registry = app(KiuqAiToolRegistry::class);
        $result = $registry->detectAndExecuteSmartSearch(
            'train AI to smart i can search anything any section inside my system as well thanks',
            $this->dara
        );

        $this->assertNotNull($result);
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Universal AI Search Trained & Active Across All System Sections', $result['message']);
        $this->assertStringContainsString('Kanban Boards', $result['message']);
        $this->assertStringContainsString('Task Cards', $result['message']);
        $this->assertStringContainsString('Production Reports', $result['message']);
        $this->assertStringContainsString('Websites & CMS', $result['message']);
        $this->assertStringContainsString('Google Search Console', $result['message']);
    }

    public function test_smart_search_finds_boards(): void
    {
        $workspace = Workspace::create([
            'name' => 'Digital Department @ KiuQ',
            'slug' => 'digital-department-kiuq',
            'owner_id' => $this->dara->id,
        ]);

        $board = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'Workflow board Team B – November 2026',
            'slug' => 'workflow-board-team-b-november-2026',
            'is_archived' => false,
            'is_hidden' => false,
            'created_by' => $this->dara->id,
        ]);

        $registry = app(KiuqAiToolRegistry::class);
        $result = $registry->detectAndExecuteSmartSearch('find workflow board team b', $this->dara);

        $this->assertNotNull($result);
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Workflow board Team B', $result['message']);
    }

    public function test_smart_search_finds_production_reports(): void
    {
        ProductionReport::create([
            'user_id' => $this->dara->id,
            'report_code' => 'PR-WEEKLY-TEST-A1',
            'team_code' => 'A',
            'team_name' => 'Team A',
            'report_type' => 'weekly',
            'reporting_date' => now()->toDateString(),
            'reporting_start_date' => now()->startOfWeek()->toDateString(),
            'reporting_end_date' => now()->endOfWeek()->toDateString(),
            'reporting_month' => 11,
            'reporting_year' => 2026,
            'title' => 'Team A Weekly Production Report',
            'executive_summary' => 'Weekly summary of production tasks.',
            'status' => 'submitted',
        ]);

        $registry = app(KiuqAiToolRegistry::class);
        $result = $registry->detectAndExecuteSmartSearch('show team a reports', $this->dara);

        $this->assertNotNull($result);
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Production Reports', $result['message']);
        $this->assertStringContainsString('Team A', $result['message']);
    }

    public function test_streaming_chat_fast_paths_smart_search_without_network_error(): void
    {
        $this->actingAs($this->dara);

        $conversation = AiConversation::create([
            'user_id' => $this->dara->id,
            'title' => 'Test Stream Smart Search',
            'model' => 'gemini-flash-lite-latest',
            'mode' => 'general',
        ]);

        $response = $this->post('/ai/chat/stream', [
            'conversation_id' => $conversation->id,
            'message' => 'train AI to smart i can search anything any section inside my system as well thanks',
            'model' => 'gemini-flash-lite-latest',
            'mode' => 'general',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));
    }
}
