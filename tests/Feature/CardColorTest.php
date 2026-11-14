<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CardColorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Board $board;
    private Card $card;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole('super-admin');

        $workspace = Workspace::create(['name' => 'Color WS', 'owner_id' => $this->user->id, 'is_active' => true]);
        $this->board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Planning Board – November 2026', 'created_by' => $this->user->id]);
        $list = BoardList::create(['board_id' => $this->board->id, 'name' => 'Week 1', 'position' => 0]);
        $this->card = Card::create([
            'board_id' => $this->board->id,
            'board_list_id' => $list->id,
            'title' => 'Poster',
            'created_by' => $this->user->id,
            'status' => 'todo',
            'priority' => 'medium',
            'position' => 0,
        ]);
    }

    public function test_card_color_can_be_set_changed_and_reset(): void
    {
        $url = 'http://localhost' . route('boards.cards.update', $this->card, false);

        $this->actingAs($this->user)->patchJson($url, ['card_color' => 'purple'])
            ->assertOk()->assertJsonPath('card.card_color', 'purple');
        $this->assertSame('purple', $this->card->fresh()->card_color);

        $this->actingAs($this->user)->patchJson($url, ['card_color' => 'amber'])
            ->assertOk()->assertJsonPath('card.card_color', 'amber');

        $this->actingAs($this->user)->patchJson($url, ['card_color' => null])
            ->assertOk()->assertJsonPath('card.card_color', null);
        $this->assertNull($this->card->fresh()->card_color);
    }

    public function test_invalid_card_color_is_rejected(): void
    {
        $url = 'http://localhost' . route('boards.cards.update', $this->card, false);

        $this->actingAs($this->user)->patchJson($url, ['card_color' => 'not-a-color'])->assertStatus(422);
        $this->assertNull($this->card->fresh()->card_color);
    }

    public function test_board_page_ships_card_color_to_the_ui_with_picker_markup(): void
    {
        $this->card->update(['card_color' => 'teal']);

        $response = $this->actingAs($this->user)->get(route('boards.show', $this->board->slug));

        $response->assertOk()
            ->assertSee('"card_color":"teal"', false)
            ->assertSee('setCardColor(ctxCard', false)
            ->assertSee('kanban-card-colored', false);
    }
}
