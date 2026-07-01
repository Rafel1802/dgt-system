<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Board;
use App\Models\BoardAutomation;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BoardCardActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
    }

    public function test_mouse_move_records_the_actor_and_real_source_without_claiming_automation(): void
    {
        Notification::fake();
        [$user, $board, $drafting, $review, $card] = $this->boardFixture();

        BoardAutomation::create([
            'board_id' => $board->id,
            'trigger_type' => 'keyword',
            'trigger_word' => 'DONE',
            'trigger_board_id' => $board->id,
            'target_board_id' => $board->id,
            'target_list_id' => $drafting->id,
            'action_type' => 'move',
        ]);

        // This mirrors the browser: visual ordering is persisted before /move.
        $card->update(['board_list_id' => $review->id]);

        $response = $this->actingAs($user)->postJson('http://localhost' . route('boards.cards.move', $card, false), [
            'board_list_id' => $review->id,
            'source_list_id' => $drafting->id,
            'position' => 0,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('automation_triggered', false)
            ->assertJsonPath('automation', null);

        $activity = ActivityLog::where('action', 'card.moved')->latest('id')->firstOrFail();
        $this->assertSame($user->id, $activity->user_id);
        $this->assertSame('moved this card from **Drafting** to **Review**', $activity->description);
    }

    public function test_comment_keyword_reports_that_automation_really_ran(): void
    {
        Notification::fake();
        [$user, $board, $drafting, $review, $card] = $this->boardFixture();

        BoardAutomation::create([
            'board_id' => $board->id,
            'trigger_type' => 'keyword',
            'trigger_word' => 'DONE',
            'trigger_board_id' => $board->id,
            'target_board_id' => $board->id,
            'target_list_id' => $review->id,
            'action_type' => 'move',
        ]);

        $this->actingAs($user)
            ->postJson('http://localhost' . route('boards.cards.comments.store', $card, false), ['body' => 'DONE'])
            ->assertCreated()
            ->assertJsonPath('card_moved', true)
            ->assertJsonPath('card.board_list_id', $review->id);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'card.moved_by_automation',
            'subject_id' => $card->id,
        ]);
    }

    private function boardFixture(): array
    {
        $user = User::factory()->create(['is_active' => true]);
        $workspace = Workspace::create([
            'name' => 'Activity Workspace',
            'owner_id' => $user->id,
            'is_active' => true,
        ]);
        $board = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'Activity Board',
            'created_by' => $user->id,
            'notifications_enabled' => true,
        ]);
        $drafting = BoardList::create([
            'board_id' => $board->id,
            'name' => 'Drafting',
            'position' => 0,
        ]);
        $review = BoardList::create([
            'board_id' => $board->id,
            'name' => 'Review',
            'position' => 1,
        ]);
        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $drafting->id,
            'title' => 'Design banner',
            'status' => 'todo',
            'priority' => 'medium',
            'position' => 0,
            'created_by' => $user->id,
        ]);

        return [$user, $board, $drafting, $review, $card];
    }
}
