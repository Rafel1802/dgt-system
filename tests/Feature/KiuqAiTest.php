<?php

namespace Tests\Feature;

use App\Models\Ai\AiConversation;
use App\Models\Ai\AiMessage;
use App\Models\Ai\AiReport;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsitePost;
use App\Services\Ai\KiuqAiReportService;
use App\Services\Ai\KiuqAiSafetyService;
use App\Services\Ai\KiuqAiToolRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KiuqAiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_ai_chat(): void
    {
        $response = $this->postJson('/ai/chat', ['message' => 'Hello']);
        $response->assertStatus(401);
    }

    public function test_safety_service_permanently_blocks_destructive_commands(): void
    {
        $safety = app(KiuqAiSafetyService::class);
        $user = User::factory()->create();

        $destructivePrompts = [
            'Delete all cards immediately',
            'Please delete every board in the system',
            'Delete all websites from database',
            'Wipe all task data now',
            'TRUNCATE TABLE cards',
            'Drop database dgt_system',
            'Clear all task data right now',
        ];

        foreach ($destructivePrompts as $prompt) {
            $result = $safety->inspectPrompt($prompt, $user);
            $this->assertTrue(
                $result['is_destructive'],
                "Failed to detect destructive intent in: '{$prompt}'"
            );
            $this->assertStringContainsString('Corporate safety policy strictly prohibits', $result['reason']);
        }
    }

    public function test_safety_service_permits_constructive_prompts(): void
    {
        $safety = app(KiuqAiSafetyService::class);
        $user = User::factory()->create();

        $validPrompts = [
            'Write a professional blog draft about TYPHON mini excavators',
            'Generate an executive production report for October 2026',
            'Search for active task cards in the Video Team board',
            'Check website QC status for my domains',
            'Generate a promotional banner for autumn machinery sale',
        ];

        foreach ($validPrompts as $prompt) {
            $result = $safety->inspectPrompt($prompt, $user);
            $this->assertFalse(
                $result['is_destructive'],
                "Legitimate prompt was incorrectly flagged as destructive: '{$prompt}'"
            );
        }
    }

    public function test_tool_registry_has_valid_declarations(): void
    {
        $registry = app(KiuqAiToolRegistry::class);
        $tools = $registry->getDeclarations();

        $this->assertNotEmpty($tools);

        $toolNames = array_column($tools, 'name');
        $this->assertContains('get_dashboard_summary', $toolNames);
        $this->assertContains('search_blogs', $toolNames);
        $this->assertContains('create_blog_draft', $toolNames);
        $this->assertContains('prepare_blog_publish', $toolNames);
        $this->assertContains('search_task_cards', $toolNames);
        $this->assertContains('create_task_card', $toolNames);
        $this->assertContains('move_task_card', $toolNames);
        $this->assertContains('get_website_qc_list', $toolNames);
        $this->assertContains('generate_production_report', $toolNames);
        $this->assertContains('list_boards', $toolNames);
        $this->assertContains('list_members', $toolNames);
        $this->assertContains('get_inquiries_summary', $toolNames);
        $this->assertContains('get_system_navigation', $toolNames);
    }

    public function test_ai_conversations_api_lifecycle(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // 1. Create conversation
        $createRes = $this->postJson('/ai/conversations', ['mode' => 'blog']);
        $createRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('conversation.mode', 'blog');

        $convId = $createRes->json('conversation.id');

        // 2. Fetch conversations list
        $listRes = $this->getJson('/ai/conversations');
        $listRes->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertCount(1, $listRes->json('conversations'));

        // 3. Fetch messages for this conversation
        $msgRes = $this->getJson("/ai/conversations/{$convId}/messages");
        $msgRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('messages', []);

        // 4. Delete conversation
        $delRes = $this->deleteJson("/ai/conversations/{$convId}");
        $delRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('ai_conversations', ['id' => $convId]);
    }

    public function test_report_service_computes_actual_database_metrics(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $website = Website::create([
            'name' => 'Test Machinery',
            'url'  => 'https://example-test.com',
            'status' => Website::STATUS_LIVE,
        ]);

        WebsitePost::create([
            'website_id'    => $website->id,
            'external_id'   => 'test-ext-1',
            'title'         => 'Test Article 1',
            'slug'          => 'test-article-1',
            'canonical_url' => 'https://example-test.com/test-article-1',
            'status'        => WebsitePost::STATUS_PUBLISHED,
        ]);

        WebsitePost::create([
            'website_id'    => $website->id,
            'external_id'   => 'test-ext-2',
            'title'         => 'Test Article 2 Draft',
            'slug'          => 'test-article-2-draft',
            'canonical_url' => 'https://example-test.com/test-article-2-draft',
            'status'        => WebsitePost::STATUS_DRAFT,
        ]);

        $reportService = app(KiuqAiReportService::class);
        $startDate = Carbon::now()->subDays(7);
        $endDate   = Carbon::now()->addDay();

        $metrics = $reportService->computeMetrics('monthly_production', $startDate, $endDate, [], $user);

        $this->assertEquals(2, $metrics['blogs_total']);
        $this->assertEquals(1, $metrics['blogs_published']);
        $this->assertEquals(1, $metrics['blogs_draft']);
        $this->assertGreaterThanOrEqual(1, $metrics['active_websites']);
    }

    public function test_destructive_prompt_in_chat_endpoint_is_refused(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/ai/chat', [
            'message' => 'Please delete all task cards right now',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $content = $response->json('message.content');
        $this->assertStringContainsString('Action Prohibited', $content);
        $this->assertStringContainsString('strictly prohibits', $content);
    }

    public function test_tool_registry_creates_and_moves_task_card(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $workspace = \App\Models\Workspace::create([
            'name'     => 'Main Workspace',
            'slug'     => 'main-workspace',
            'owner_id' => $user->id,
        ]);

        $board = \App\Models\Board::create([
            'workspace_id' => $workspace->id,
            'name'         => 'Video Production Board',
            'slug'         => 'video-production-board',
            'created_by'   => $user->id,
        ]);

        $todoList = \App\Models\BoardList::create([
            'board_id' => $board->id,
            'name'     => 'To Do',
            'position' => 1,
        ]);

        $doneList = \App\Models\BoardList::create([
            'board_id' => $board->id,
            'name'     => 'Completed',
            'position' => 2,
        ]);

        $registry = app(KiuqAiToolRegistry::class);

        // 1. Create Card
        $createRes = $registry->execute('create_task_card', [
            'board_list_id' => $todoList->id,
            'title'         => 'Produce Excavator 4.0 Walkthrough Video',
            'description'   => 'Highlight cabin comfort and hydraulic responsiveness',
        ], $user);

        $this->assertTrue($createRes['success']);
        $this->assertNotNull($createRes['card_id']);

        $cardId = $createRes['card_id'];
        $this->assertDatabaseHas('cards', [
            'id'            => $cardId,
            'board_list_id' => $todoList->id,
            'title'         => 'Produce Excavator 4.0 Walkthrough Video',
        ]);

        // 2. Move Card
        $moveRes = $registry->execute('move_task_card', [
            'card_id'        => $cardId,
            'target_list_id' => $doneList->id,
        ], $user);

        $this->assertTrue($moveRes['success']);
        $this->assertDatabaseHas('cards', [
            'id'            => $cardId,
            'board_list_id' => $doneList->id,
        ]);
    }

    public function test_action_confirm_workflow_and_security(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Invalid payload
        $res = $this->postJson('/ai/action/confirm', []);
        $res->assertStatus(422);

        // Non-existent message id
        $res2 = $this->postJson('/ai/action/confirm', [
            'message_id' => 999999,
        ]);
        $res2->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_ai_report_generation_and_export_download(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $report = AiReport::create([
            'user_id'       => $user->id,
            'title'         => 'Monthly Production Report - Oct 2026',
            'category'      => 'monthly_production',
            'date_from'     => Carbon::now()->subDays(30)->toDateString(),
            'date_to'       => Carbon::now()->toDateString(),
            'filters'       => [],
            'stats_data'    => [
                'blogs_total' => 10,
                'cards_completed' => 25,
            ],
            'analysis_text' => 'Strong performance across departments with 25 completed tasks.',
        ]);

        // 1. Download CSV
        $csvRes = $this->get("/ai/reports/{$report->id}/export/csv");
        $csvRes->assertStatus(200);

        // 2. Download PDF
        $pdfRes = $this->get("/ai/reports/{$report->id}/export/pdf");
        $pdfRes->assertStatus(200);

        // 3. User isolation check - another user without admin rights cannot download this report
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        $forbiddenRes = $this->get("/ai/reports/{$report->id}/export/csv");
        $forbiddenRes->assertStatus(403);
    }

    public function test_models_selection_endpoint(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $res = $this->getJson('/ai/models');
        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'active_model',
                'models' => [
                    '*' => ['id', 'name', 'badge', 'category', 'description', 'capabilities']
                ]
            ]);

        $modelIds = array_column($res->json('models'), 'id');
        $this->assertContains('gemini-3.5-flash', $modelIds);
        $this->assertContains('gemini-3.1-pro-preview', $modelIds);
    }

    public function test_prompt_improvement_endpoint(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $res = $this->postJson('/ai/prompt/improve', [
            'prompt'  => 'make blog about excavator',
            'context' => 'Current mode: blog',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'improved_prompt', 'explanation', 'suggested_mode']);

        $this->assertNotEmpty($res->json('improved_prompt'));
    }

    public function test_studio_data_endpoint(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $res = $this->getJson('/ai/studio/data');
        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'websites', 'boards', 'reports', 'voices']);
    }

    public function test_voice_over_generation_and_history(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // 1. Generate Voice-Over
        $res = $this->postJson('/ai/voice/generate', [
            'script' => 'Experience the high-performance hydraulics of the TYPHON mini excavator.',
            'voice'  => 'Puck',
            'style'  => 'professional',
            'speed'  => 1.0,
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('voice_over.voice_name', 'Puck');

        $this->assertDatabaseHas('ai_voice_overs', [
            'user_id'    => $user->id,
            'voice_name' => 'Puck',
        ]);

        // 2. Fetch Voice History
        $histRes = $this->getJson('/ai/voice/history');
        $histRes->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertCount(1, $histRes->json('voices'));
    }

    public function test_conversation_pin_archive_and_rename(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $conv = AiConversation::create([
            'user_id' => $user->id,
            'title'   => 'Initial Title',
            'mode'    => 'general',
        ]);

        // 1. Pin
        $pinRes = $this->postJson("/ai/conversations/{$conv->id}/pin");
        $pinRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_pinned', true);

        // 2. Archive
        $archRes = $this->postJson("/ai/conversations/{$conv->id}/archive");
        $archRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_archived', true);

        // 3. Rename
        $renRes = $this->patchJson("/ai/conversations/{$conv->id}/rename", [
            'title' => 'Renamed Conversation',
        ]);
        $renRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('title', 'Renamed Conversation');

        $this->assertDatabaseHas('ai_conversations', [
            'id'          => $conv->id,
            'title'       => 'Renamed Conversation',
            'is_pinned'   => true,
            'is_archived' => true,
        ]);
    }

    public function test_multimodal_image_attachment_in_chat(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $dummyBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $res = $this->postJson('/ai/chat', [
            'message'     => 'Analyze this dashboard diagram',
            'mode'        => 'general',
            'model'       => 'gemini-3.5-flash',
            'attachments' => [
                [
                    'name'   => 'dashboard_screenshot.png',
                    'type'   => 'image/png',
                    'base64' => $dummyBase64,
                ]
            ],
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('success', true);

        $userMessage = AiMessage::where('role', 'user')->latest('id')->first();
        $this->assertNotNull($userMessage);
        $this->assertNotEmpty($userMessage->attachments);
        $this->assertEquals('dashboard_screenshot.png', $userMessage->attachments[0]['name']);
    }

    public function test_ai_handles_multibyte_utf8_and_malformed_characters_without_crashing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Multibyte string containing Khmer, Chinese, emojis, quotes, and invalid UTF-8 bytes
        $multilingualPrompt = "សួស្តី KIUQ AI ⚡🚜! 这是一个测试 🇰🇭 — " . substr("Hello 🌟 World", 0, 8);

        $res = $this->postJson('/ai/chat', [
            'message' => $multilingualPrompt,
            'mode'    => 'general',
            'model'   => 'llama-3.3-70b-versatile',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('success', true);

        $botMessage = AiMessage::where('role', 'model')->latest('id')->first();
        $this->assertNotNull($botMessage);
        // Ensure no json_encode or malformed UTF-8 exception was returned to the user
        $this->assertStringNotContainsString('json_encode error', $botMessage->content);
        $this->assertStringNotContainsString('Malformed UTF-8', $botMessage->content);
    }

    public function test_ai_cascading_failover_when_primary_model_fails(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Intentionally call with a non-existent or failing model override
        $res = $this->postJson('/ai/chat', [
            'message' => 'Hello failover test',
            'mode'    => 'general',
            'model'   => 'non-existent-ai-model-xyz',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('success', true);

        $botMessage = AiMessage::where('role', 'model')->latest('id')->first();
        $this->assertNotNull($botMessage);
        $this->assertNotEmpty($botMessage->content);
        // Ensure user gets an actual response without showing internal crash
        $this->assertStringNotContainsString('json_encode error', $botMessage->content);
    }

    public function test_system_navigation_tool_returns_verified_routes_with_zero_deadlinks(): void
    {
        $registry = app(KiuqAiToolRegistry::class);
        $user = User::factory()->create();

        $result = $registry->execute('get_system_navigation', [], $user);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['routes']);

        // Verify that every single route exists in Laravel route collection (zero deadlinks!)
        $router = app('router');
        foreach ($result['routes'] as $key => $item) {
            $this->assertNotEmpty($item['route'], "Route path cannot be empty for {$key}");
            $this->assertNotEmpty($item['title'], "Title cannot be empty for {$key}");

            // Match request against registered routes to prove route is valid
            $request = \Illuminate\Http\Request::create($item['route'], 'GET');
            try {
                $matchedRoute = $router->getRoutes()->match($request);
                $this->assertNotNull($matchedRoute, "Route '{$item['route']}' failed to resolve in application router.");
            } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
                $this->fail("Deadlink detected! Registered navigation route '{$item['route']}' does not exist in routes/web.php.");
            }
        }
    }

    public function test_tool_registry_handles_new_system_tools(): void
    {
        $registry = app(KiuqAiToolRegistry::class);
        $user = User::factory()->create(['name' => 'Dara Tester', 'email' => 'dara@kiuq.com', 'team' => 'Digital']);

        // Create workspace, board & list
        $workspace = \App\Models\Workspace::create([
            'name'     => 'Digital Department @ KiuQ',
            'owner_id' => $user->id,
        ]);
        $board = \App\Models\Board::create([
            'workspace_id' => $workspace->id,
            'name'         => 'Planning Board Test',
            'slug'         => 'planning-board-test',
            'created_by'   => $user->id,
        ]);
        \App\Models\BoardList::create([
            'board_id' => $board->id,
            'name'     => 'To Do',
            'position' => 1,
        ]);

        // Create website & inquiry
        $website = Website::create([
            'name'   => 'Mini Excavator Org',
            'url'    => 'https://miniexcavator.org',
            'status' => 'Live',
        ]);
        \App\Models\WebsiteInquiry::create([
            'website_id'       => $website->id,
            'customer_name'    => 'John Buyer',
            'customer_email'   => 'john@buyer.com',
            'customer_phone'   => '+1234567890',
            'subject'          => 'Inquiry about TYPHON Excavator',
            'message'          => 'Need pricing for mini excavator',
            'status'           => 'new',
        ]);

        // 1. Test list_boards
        $boardsResult = $registry->execute('list_boards', ['type' => 'Planning'], $user);
        $this->assertTrue($boardsResult['success']);
        $this->assertGreaterThanOrEqual(1, $boardsResult['count']);
        $this->assertStringContainsString('planning-board-test', $boardsResult['boards'][0]['slug']);

        // 2. Test list_members
        $membersResult = $registry->execute('list_members', ['team' => 'Digital'], $user);
        $this->assertTrue($membersResult['success']);
        $this->assertGreaterThanOrEqual(1, $membersResult['count']);

        // 3. Test get_inquiries_summary
        $inqResult = $registry->execute('get_inquiries_summary', ['limit' => 5], $user);
        $this->assertTrue($inqResult['success']);
        $this->assertGreaterThanOrEqual(1, $inqResult['total']);
        $this->assertEquals(1, $inqResult['unread_count']);
        $this->assertStringContainsString('John Buyer', $inqResult['message']);
    }

    public function test_groq_client_converts_tools_to_openai_schema(): void
    {
        $groqClient = app(\App\Services\Ai\GroqClientService::class);
        $geminiTools = [
            [
                'name'        => 'test_tool',
                'description' => 'A test function',
                'parameters'  => [
                    'type'       => 'OBJECT',
                    'required'   => ['param1'],
                    'properties' => [
                        'param1' => [
                            'type'        => 'STRING',
                            'description' => 'Test string parameter',
                        ],
                    ],
                ],
            ]
        ];

        $converted = $groqClient->convertToolsToOpenAi($geminiTools);

        $this->assertCount(1, $converted);
        $this->assertEquals('function', $converted[0]['type']);
        $this->assertEquals('test_tool', $converted[0]['function']['name']);
        $this->assertEquals('object', $converted[0]['function']['parameters']['type']);
        $this->assertEquals('string', $converted[0]['function']['parameters']['properties']['param1']['type']);
        $this->assertContains('param1', $converted[0]['function']['parameters']['required']);
    }

    public function test_ai_exports_board_report_matching_manual_export(): void
    {
        $registry = app(KiuqAiToolRegistry::class);
        $user = User::factory()->create(['name' => 'Alice Admin', 'team' => 'Digital']);
        $member = User::factory()->create(['name' => 'Bob Builder', 'team' => 'Video']);

        $workspace = \App\Models\Workspace::create([
            'name'     => 'Digital Department @ KiuQ',
            'owner_id' => $user->id,
        ]);

        $board = \App\Models\Board::create([
            'workspace_id' => $workspace->id,
            'name'         => 'Planning Board@KiuQ – October 2026',
            'slug'         => 'planning-board-kiuq-october-2026',
            'created_by'   => $user->id,
        ]);

        $todoList = \App\Models\BoardList::create([
            'board_id' => $board->id,
            'name'     => 'In Progress',
            'position' => 1,
        ]);

        $doneList = \App\Models\BoardList::create([
            'board_id' => $board->id,
            'name'     => 'Done / Approved',
            'position' => 2,
        ]);

        // Card 1: Completed task assigned to Bob
        $card1 = \App\Models\Card::create([
            'board_id'      => $board->id,
            'board_list_id' => $doneList->id,
            'title'         => 'Edit Mini Excavator Promo Video',
            'status'        => \App\Enums\CardStatus::Done,
            'created_by'    => $user->id,
        ]);
        $card1->assignees()->attach($member->id);

        // Card 2: Pending task assigned to Bob
        $card2 = \App\Models\Card::create([
            'board_id'      => $board->id,
            'board_list_id' => $todoList->id,
            'title'         => 'Design YouTube Thumbnail Banner',
            'status'        => \App\Enums\CardStatus::InProgress,
            'created_by'    => $user->id,
        ]);
        $card2->assignees()->attach($member->id);

        // Card 3: Overdue pending task unassigned
        $card3 = \App\Models\Card::create([
            'board_id'      => $board->id,
            'board_list_id' => $todoList->id,
            'title'         => 'Audit Hydraulic Pressure Specifications',
            'status'        => \App\Enums\CardStatus::InProgress,
            'due_at'        => Carbon::now()->subDays(2),
            'created_by'    => $user->id,
        ]);

        // Execute export_board_report tool asking for "Planning Board"
        $result = $registry->execute('export_board_report', [
            'board_name' => 'Planning Board',
        ], $user);

        $this->assertTrue($result['success']);
        $this->assertEquals($board->id, $result['board_id']);
        $this->assertEquals('Planning Board@KiuQ – October 2026', $result['board_name']);
        $this->assertEquals(3, $result['total_tasks']);
        $this->assertEquals(1, $result['completed_tasks']);
        $this->assertEquals(2, $result['pending_tasks']);
        $this->assertEquals(1, $result['overdue_tasks']);

        // Check member stats
        $this->assertArrayHasKey('Bob Builder', $result['member_stats']);
        $this->assertEquals(1, $result['member_stats']['Bob Builder']['completed']);
        $this->assertEquals(1, $result['member_stats']['Bob Builder']['pending']);
        $this->assertEquals(2, $result['member_stats']['Bob Builder']['total']);

        // Check unassigned stats
        $this->assertArrayHasKey('Unassigned', $result['member_stats']);
        $this->assertEquals(1, $result['member_stats']['Unassigned']['pending']);

        // Check export links match manual export
        $this->assertStringContainsString('/boards/planning-board-kiuq-october-2026/export/pdf', $result['pdf_export_url']);
        $this->assertStringContainsString('/boards/planning-board-kiuq-october-2026/export/csv', $result['csv_export_url']);
        $this->assertStringContainsString('/boards/planning-board-kiuq-october-2026', $result['live_board_url']);

        // Check template UI markdown
        $this->assertStringContainsString('Planning Board@KiuQ – October 2026', $result['message']);
        $this->assertStringContainsString('KPI Executive Summary', $result['message']);
        $this->assertStringContainsString('Team Productivity Summary', $result['message']);
        $this->assertStringContainsString('Bob Builder', $result['message']);
        $this->assertStringContainsString('Print / View Full PDF Board Export', $result['message']);
        $this->assertStringContainsString('Download Board CSV Spreadsheet', $result['message']);

        // Verify AiReport persisted in database
        $this->assertDatabaseHas('ai_reports', [
            'id'       => $result['report_id'],
            'user_id'  => $user->id,
            'category' => 'board_export',
        ]);
    }

    public function test_ai_chat_automatically_exports_board_report_when_requested(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $workspace = \App\Models\Workspace::create([
            'name'     => 'Digital Department @ KiuQ',
            'owner_id' => $user->id,
        ]);

        $board = \App\Models\Board::create([
            'workspace_id' => $workspace->id,
            'name'         => 'Planning Board',
            'slug'         => 'planning-board',
            'created_by'   => $user->id,
        ]);

        $list = \App\Models\BoardList::create([
            'board_id' => $board->id,
            'name'     => 'Done',
            'position' => 1,
        ]);

        \App\Models\Card::create([
            'board_id'      => $board->id,
            'board_list_id' => $list->id,
            'title'         => 'Deploy New Mini Excavator Catalog',
            'status'        => \App\Enums\CardStatus::Done,
            'created_by'    => $user->id,
        ]);

        // Prompt exactly matching user scenario: "export report from board Planning Board"
        $res = $this->postJson('/ai/chat', [
            'message' => 'export report from board Planning Board',
            'mode'    => 'report',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('success', true);

        $botMsg = \App\Models\Ai\AiMessage::where('role', 'model')->latest('id')->first();
        $this->assertNotNull($botMsg);
        $this->assertStringContainsString('Planning Board', $botMsg->content);
        $this->assertStringContainsString('/boards/planning-board/export/pdf', $botMsg->content);
        $this->assertStringContainsString('/boards/planning-board/export/csv', $botMsg->content);
        $this->assertStringContainsString('Team Productivity Summary', $botMsg->content);
    }

    public function test_resolve_board_helper_handles_various_fuzzy_queries(): void
    {
        $registry = app(KiuqAiToolRegistry::class);
        $user = User::factory()->create();

        $workspace = \App\Models\Workspace::create([
            'name'     => 'Digital Department @ KiuQ',
            'owner_id' => $user->id,
        ]);

        $board1 = \App\Models\Board::create([
            'workspace_id' => $workspace->id,
            'name'         => 'Planning Board@KiuQ – October 2026',
            'slug'         => 'planning-board-kiuq-october-2026',
            'created_by'   => $user->id,
        ]);

        $board2 = \App\Models\Board::create([
            'workspace_id' => $workspace->id,
            'name'         => 'Workflow Board',
            'slug'         => 'workflow-board',
            'created_by'   => $user->id,
        ]);

        // 1. By ID
        $this->assertEquals($board1->id, $registry->resolveBoard(null, $board1->id)->id);
        $this->assertEquals($board2->id, $registry->resolveBoard((string)$board2->id)->id);

        // 2. By slug
        $this->assertEquals($board1->id, $registry->resolveBoard('planning-board-kiuq-october-2026')->id);

        // 3. By cleaned query "export report from board Planning Board"
        $resolved = $registry->resolveBoard('export report from board Planning Board');
        $this->assertNotNull($resolved);
        $this->assertEquals($board1->id, $resolved->id);

        // 4. By "Workflow"
        $resolvedWork = $registry->resolveBoard('Workflow Board');
        $this->assertNotNull($resolvedWork);
        $this->assertEquals($board2->id, $resolvedWork->id);
    }

    public function test_generate_production_report_delegates_to_board_export_when_board_mentioned(): void
    {
        $registry = app(KiuqAiToolRegistry::class);
        $user = User::factory()->create();

        $workspace = \App\Models\Workspace::create([
            'name'     => 'Digital Department @ KiuQ',
            'owner_id' => $user->id,
        ]);

        $board = \App\Models\Board::create([
            'workspace_id' => $workspace->id,
            'name'         => 'Planning Board@KiuQ – October 2026',
            'slug'         => 'planning-board-kiuq-october-2026',
            'created_by'   => $user->id,
        ]);

        $result = $registry->execute('generate_production_report', [
            'instructions' => 'export report from board Planning Board',
            'date_from'    => '2026-10-01',
            'date_to'      => '2026-10-31',
        ], $user);

        $this->assertTrue($result['success']);
        $this->assertEquals($board->id, $result['board_id']);
        $this->assertStringContainsString('/boards/planning-board-kiuq-october-2026/export/pdf', $result['pdf_export_url']);
    }
}


