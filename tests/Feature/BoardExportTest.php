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

        // Card created and moved (activity) this month
        $cardToday = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Today Task',
            'status' => CardStatus::Todo,
            'created_by' => $this->user->id,
        ]);
        $cardToday->created_at = now();
        $cardToday->save();
        // A 'moved' activity in-range is required for the date filter to include this card
        \App\Models\ActivityLog::create([
            'subject_id'   => $cardToday->id,
            'subject_type' => \App\Models\Card::class,
            'user_id'      => $this->user->id,
            'action'       => 'moved',
            'description'  => 'Moved card to In Progress',
            'created_at'   => now(),
        ]);

        // Card created 2 months ago with activity also 2 months ago
        $cardOld = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Old Task',
            'status' => CardStatus::Todo,
            'created_by' => $this->user->id,
        ]);
        $cardOld->created_at = now()->subMonths(2);
        $cardOld->save();
        \App\Models\ActivityLog::create([
            'subject_id'   => $cardOld->id,
            'subject_type' => \App\Models\Card::class,
            'user_id'      => $this->user->id,
            'action'       => 'moved',
            'description'  => 'Moved card to In Progress',
            'created_at'   => now()->subMonths(2),
        ]);

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

    public function test_export_pdf_has_clickable_images_and_card_links(): void
    {
        $card = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Card With Attachments',
            'status' => CardStatus::Todo,
            'created_by' => $this->user->id,
        ]);

        $imageFile = \App\Models\CardFile::create([
            'card_id' => $card->id,
            'uploaded_by' => $this->user->id,
            'original_name' => 'screenshot.png',
            'stored_name' => 'screenshot.png',
            'disk' => 'local',
            'path' => 'kanban/' . $card->id . '/screenshot.png',
            'mime_type' => 'image/png',
            'size' => 1024,
        ]);

        $urlFile = \App\Models\CardFile::create([
            'card_id' => $card->id,
            'uploaded_by' => $this->user->id,
            'original_name' => 'External Reference',
            'stored_name' => 'https://drive.google.com/test-file',
            'disk' => 'url',
            'path' => 'https://drive.google.com/test-file',
            'mime_type' => 'link',
            'size' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('boards.export.pdf', [
                'board' => $this->board->slug,
            ]));

        $response->assertStatus(200);
        $content = $response->getContent();

        // Check that card title has clickable link with ?card=ID
        $expectedCardUrl = route('boards.show', $this->board->slug) . '?card=' . $card->id;
        $this->assertStringContainsString($expectedCardUrl, $content);
        $this->assertStringContainsString('Card With Attachments', $content);

        // Check that image has clickable link
        $this->assertStringContainsString('1 Images', $content);
        $this->assertStringContainsString($imageFile->preview_url, $content);

        // Check that external link is present and clickable
        $this->assertStringContainsString('https://drive.google.com/test-file', $content);
        $this->assertStringContainsString('Link 1', $content);
    }

    public function test_export_csv_has_attached_column_and_clickable_links(): void
    {
        $card = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Task With CSV Attachments',
            'status' => CardStatus::Todo,
            'created_by' => $this->user->id,
        ]);

        $imageFile = \App\Models\CardFile::create([
            'card_id' => $card->id,
            'uploaded_by' => $this->user->id,
            'original_name' => 'photo.jpg',
            'stored_name' => 'photo.jpg',
            'disk' => 'local',
            'path' => 'kanban/' . $card->id . '/photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2048,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('boards.export.csv', [
                'board' => $this->board->slug,
            ]));

        $response->assertStatus(200);
        $content = $response->getContent();

        // Check Attached column header
        $this->assertStringContainsString('Attached', $content);

        // Check that card title has link
        $expectedCardUrl = route('boards.show', $this->board->slug) . '?card=' . $card->id;
        $this->assertStringContainsString($expectedCardUrl, $content);

        // Check that image link is in the Attached column
        $this->assertStringContainsString('1 Images', $content);
        $this->assertStringContainsString($imageFile->preview_url, $content);
    }

    public function test_export_csv_raw_streaming(): void
    {
        $card = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Raw CSV Task',
            'status' => CardStatus::Todo,
            'created_by' => $this->user->id,
        ]);

        $imageFile = \App\Models\CardFile::create([
            'card_id' => $card->id,
            'uploaded_by' => $this->user->id,
            'original_name' => 'image.png',
            'stored_name' => 'image.png',
            'disk' => 'local',
            'path' => 'kanban/' . $card->id . '/image.png',
            'mime_type' => 'image/png',
            'size' => 1024,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('boards.export.csv', [
                'board' => $this->board->slug,
                'raw' => 1,
            ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Raw CSV Task', $content);
        $this->assertStringContainsString($imageFile->preview_url, $content);
    }

    public function test_personal_report_csv_export_includes_tasks(): void
    {
        $supervisor = User::factory()->create([
            'is_active' => true,
            'team_role' => 'Supervisor',
        ]);
        $supervisor->assignRole('super-admin');

        $card = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Personal Task for CSV',
            'status' => CardStatus::Todo,
            'created_by' => $supervisor->id,
        ]);

        \App\Models\ActivityLog::create([
            'subject_type' => Card::class,
            'subject_id' => $card->id,
            'user_id' => $supervisor->id,
            'action' => 'approved',
            'description' => 'approved this card',
        ]);

        $response = $this->actingAs($supervisor)
            ->get(route('boards.reports.personal.export', [
                'format' => 'csv',
                'board_ids' => [$this->board->id],
            ]));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('Personal Task for CSV', $content);
        $this->assertStringNotContainsString('No tasks found matching the selected filters.', $content);
    }

    public function test_pdf_export_with_comments_and_screenshots_renders_clean_format(): void
    {
        $card = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'Task With Comment Screenshots',
            'status' => CardStatus::Todo,
            'created_by' => $this->user->id,
        ]);

        $cardFile = \App\Models\CardFile::create([
            'card_id' => $card->id,
            'uploaded_by' => $this->user->id,
            'original_name' => 'screenshot-proof.png',
            'stored_name' => 'screenshot-proof.png',
            'disk' => 'url',
            'path' => 'https://example.com/screenshot-proof.png',
            'mime_type' => 'image/png',
            'size' => 1024,
            'is_comment_image' => true,
        ]);

        $comment = $card->comments()->create([
            'user_id' => $this->user->id,
            'content' => "Please see the design update:\n![proof]({$cardFile->download_url})",
            'is_system' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('boards.export.pdf', [
                'board' => $this->board->slug,
                'include_comments' => '1',
            ]));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('Task Comments', $content);
        $this->assertStringContainsString($this->user->name, $content);
        $this->assertStringContainsString('Please see the design update:', $content);
        $this->assertStringContainsString('comment-screenshot-card', $content);
        $this->assertStringContainsString('comment-screenshot-thumbnail', $content);
        $this->assertStringContainsString('screenshot-proof.png', $content);
    }

    public function test_qc_personal_report_with_include_comments_includes_screenshots(): void
    {
        $qcUser = User::factory()->create([
            'is_active' => true,
            'team_role' => 'QC Reviewer',
        ]);
        $qcUser->assignRole('super-admin');

        $card = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'QC Task With Screenshot',
            'status' => CardStatus::Todo,
            'created_by' => $qcUser->id,
        ]);

        // QC approval comment for activity date
        $card->comments()->create([
            'user_id' => $qcUser->id,
            'content' => 'QC approved task',
            'is_system' => false,
        ]);

        // Team comment with screenshot
        $card->comments()->create([
            'user_id' => $this->user->id,
            'content' => "Here is the screenshot:\n![screenshot](https://example.com/screenshot.jpg)",
            'is_system' => false,
        ]);

        $response = $this->actingAs($qcUser)
            ->get(route('boards.reports.personal.export', [
                'format' => 'pdf',
                'board_ids' => [$this->board->id],
                'include_comments' => '1',
            ]));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('QC Task With Screenshot', $content);
        $this->assertStringContainsString('Here is the screenshot:', $content);
        $this->assertStringContainsString('comment-screenshot-card', $content);
        $this->assertStringContainsString('https://example.com/screenshot.jpg', $content);
    }

    public function test_personal_report_csv_with_include_comments_renders_comments_and_screenshots(): void
    {
        $supervisor = User::factory()->create([
            'is_active' => true,
            'team_role' => 'Supervisor',
        ]);
        $supervisor->assignRole('super-admin');

        $card = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $this->list->id,
            'title' => 'CSV Task With Screenshot',
            'status' => CardStatus::Todo,
            'created_by' => $supervisor->id,
        ]);

        \App\Models\ActivityLog::create([
            'subject_type' => Card::class,
            'subject_id' => $card->id,
            'user_id' => $supervisor->id,
            'action' => 'approved',
            'description' => 'approved this card',
        ]);

        $card->comments()->create([
            'user_id' => $this->user->id,
            'content' => "Tested on staging:\n![stage_screen](https://example.com/stage.png)",
            'is_system' => false,
        ]);

        $response = $this->actingAs($supervisor)
            ->get(route('boards.reports.personal.export', [
                'format' => 'csv',
                'board_ids' => [$this->board->id],
                'include_comments' => '1',
            ]));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('CSV Task With Screenshot', $content);
        $this->assertStringContainsString('Tested on staging:', $content);
        $this->assertStringContainsString('https://example.com/stage.png', $content);
    }
}
