<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardChecklist;
use App\Models\CardChecklistItem;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ChecklistReviewMarkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    }

    private function makeItem(User $owner): array
    {
        $workspace = Workspace::create(['name' => 'Marketing', 'owner_id' => $owner->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow', 'created_by' => $owner->id]);
        $list = BoardList::create(['board_id' => $board->id, 'name' => 'Production Team', 'position' => 1]);
        $card = Card::create(['board_id' => $board->id, 'board_list_id' => $list->id, 'title' => 'Card', 'created_by' => $owner->id]);
        $checklist = CardChecklist::create(['card_id' => $card->id, 'title' => 'Status', 'position' => 1]);
        $item = CardChecklistItem::create(['checklist_id' => $checklist->id, 'content' => '1. Poster : 12TH-OCT', 'position' => 1]);

        return [$card, $checklist, $item];
    }

    private function url(Card $card, CardChecklist $cl, CardChecklistItem $item): string
    {
        return route('boards.cards.checklists.items.review', [$card, $cl, $item]);
    }

    public function test_only_dara_and_kim_can_mark_and_flag_issue(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $dara = User::factory()->create(['username' => 'dara', 'name' => 'Mr. Dara (Head)', 'is_active' => true]);
        $other = User::factory()->create(['username' => 'someone', 'name' => 'Someone', 'is_active' => true]);
        [$card, $cl, $item] = $this->makeItem($dara);

        $this->actingAs($other)->patchJson($this->url($card, $cl, $item), ['action' => 'mark'])->assertStatus(403);
        $this->actingAs($other)->patchJson($this->url($card, $cl, $item), ['action' => 'issue'])->assertStatus(403);

        $res = $this->actingAs($dara)->patchJson($this->url($card, $cl, $item), ['action' => 'mark'])
            ->assertOk()
            ->assertJsonPath('item.is_marked', true)
            ->assertJsonPath('item.marked_user.name', 'Mr. Dara (Head)')
            ->assertJsonPath('item.marked_user.username', 'dara');

        // No notification sent on checklist review mark
        \Illuminate\Support\Facades\Notification::assertNothingSent();

        // Untick
        $this->actingAs($dara)->patchJson($this->url($card, $cl, $item), ['action' => 'mark'])
            ->assertOk()->assertJsonPath('item.is_marked', false);

        // Flag issue clears the mark
        $this->actingAs($dara)->patchJson($this->url($card, $cl, $item), ['action' => 'mark']);
        $this->actingAs($dara)->patchJson($this->url($card, $cl, $item), ['action' => 'issue'])
            ->assertOk()
            ->assertJsonPath('item.has_issue', true)
            ->assertJsonPath('item.is_marked', false)
            ->assertJsonPath('item.issue_user.name', 'Mr. Dara (Head)');

        \Illuminate\Support\Facades\Notification::assertNothingSent();
    }

    public function test_only_admins_can_approve(): void
    {
        $kim = User::factory()->create(['username' => 'kim', 'name' => 'Kim', 'is_active' => true]);
        $admin = User::factory()->create(['username' => 'boss1', 'is_active' => true]);
        $admin->assignRole('super-admin');
        [$card, $cl, $item] = $this->makeItem($kim);

        // Cannot approve before anything is marked
        $this->actingAs($admin)->patchJson($this->url($card, $cl, $item), ['action' => 'approve'])->assertStatus(422);

        $this->actingAs($kim)->patchJson($this->url($card, $cl, $item), ['action' => 'issue'])->assertOk();

        // dara/kim are not admins -> cannot approve
        $this->actingAs($kim)->patchJson($this->url($card, $cl, $item), ['action' => 'approve'])->assertStatus(403);

        $this->actingAs($admin)->patchJson($this->url($card, $cl, $item), ['action' => 'approve'])
            ->assertOk()
            ->assertJsonPath('item.is_approved', true)
            ->assertJsonPath('item.has_issue', false)
            ->assertJsonPath('item.is_marked', true);
    }

    public function test_listing_and_content_items_auto_assign_fixed_users(): void
    {
        $chhay = User::factory()->create(['username' => 'chhay', 'name' => 'Chhay', 'is_active' => true]);
        $sreypich = User::factory()->create(['username' => 'sreypich', 'name' => 'Sreypich', 'is_active' => true]);
        [$card] = $this->makeItem($chhay);

        $this->assertEquals('listing', CardChecklistItem::detectCategory('1. Listing : 12TH-OCT'));
        $this->assertEquals('content', CardChecklistItem::detectCategory('Content : 13TH-OCT'));
        $this->assertEquals('video', CardChecklistItem::detectCategory('Video Content : 13TH-OCT'));

        // Works even though neither user is a member of the card
        $this->assertEquals($chhay->id, CardChecklistItem::detectUserIdForCard('Listing : 12TH-OCT', $card));
        $this->assertEquals($sreypich->id, CardChecklistItem::detectUserIdForCard('Content : 13TH-OCT', $card));
    }

    public function test_assigned_item_can_only_be_ticked_by_assignees_or_override_users(): void
    {
        $pich = User::factory()->create(['username' => 'pich', 'name' => 'Mr. Pich', 'is_active' => true]);
        $nalin = User::factory()->create(['username' => 'nalin', 'name' => 'Ms. Nalin', 'is_active' => true]);
        $stranger = User::factory()->create(['username' => 'stranger', 'name' => 'Stranger', 'is_active' => true]);
        $dara = User::factory()->create(['username' => 'dara', 'name' => 'Dara', 'is_active' => true]);
        $somalika = User::factory()->create(['username' => 'somalika', 'name' => 'Ms. Somalika (Supervisor)', 'is_active' => true]);
        [$card, $cl, $item] = $this->makeItem($pich);

        $url = route('boards.cards.checklists.items.toggle', [$card, $cl, $item]);

        // Assign two members at once
        $this->actingAs($pich)->patchJson($url, ['assigned_user_ids' => [$pich->id, $nalin->id]])
            ->assertOk()->assertJsonCount(2, 'item.assigned_users');

        // Stranger is blocked
        $this->actingAs($stranger)->patchJson($url)->assertStatus(403);
        $this->assertFalse($item->fresh()->is_completed);

        // Either assignee can tick
        $this->actingAs($nalin)->patchJson($url)->assertOk();
        $this->assertTrue($item->fresh()->is_completed);

        // dara & somalika can override
        $this->actingAs($dara)->patchJson($url)->assertOk();
        $this->assertFalse($item->fresh()->is_completed);
        $this->actingAs($somalika)->patchJson($url)->assertOk();
        $this->assertTrue($item->fresh()->is_completed);
    }

    public function test_unassigned_item_can_be_ticked_by_anyone(): void
    {
        $owner = User::factory()->create(['username' => 'owner', 'is_active' => true]);
        $stranger = User::factory()->create(['username' => 'stranger', 'is_active' => true]);
        [$card, $cl, $item] = $this->makeItem($owner);

        $this->actingAs($stranger)
            ->patchJson(route('boards.cards.checklists.items.toggle', [$card, $cl, $item]))
            ->assertOk();
        $this->assertTrue($item->fresh()->is_completed);
    }
}
