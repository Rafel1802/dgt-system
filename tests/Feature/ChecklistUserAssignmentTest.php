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

class ChecklistUserAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    }

    public function test_category_detection_keywords(): void
    {
        // Video keywords
        $this->assertEquals('video', CardChecklistItem::detectCategory('1. Video : 12TH-OCT'));
        $this->assertEquals('video', CardChecklistItem::detectCategory('Video Short : 16TH-OCT'));
        $this->assertEquals('video', CardChecklistItem::detectCategory('short video for tiktok'));
        $this->assertEquals('video', CardChecklistItem::detectCategory('Video Landscape : 20TH'));
        $this->assertEquals('video', CardChecklistItem::detectCategory('Landscape Video test'));
        $this->assertEquals('video', CardChecklistItem::detectCategory('Reel : promo'));
        $this->assertEquals('video', CardChecklistItem::detectCategory('Instagram Reels'));
        $this->assertEquals('video', CardChecklistItem::detectCategory('Video Content creation'));

        // Graphic keywords
        $this->assertEquals('graphic', CardChecklistItem::detectCategory('1. Poster : 12TH-OCT'));
        $this->assertEquals('graphic', CardChecklistItem::detectCategory('Graphic : 15TH-OCT'));
        $this->assertEquals('graphic', CardChecklistItem::detectCategory('Design post'));
        $this->assertEquals('graphic', CardChecklistItem::detectCategory('Graphic Design'));
        $this->assertEquals('graphic', CardChecklistItem::detectCategory('Social Media Graphic'));
        $this->assertEquals('graphic', CardChecklistItem::detectCategory('Artwork preparation'));
        $this->assertEquals('graphic', CardChecklistItem::detectCategory('Banner 300x250'));
        $this->assertEquals('graphic', CardChecklistItem::detectCategory('Creative concept'));

        // Listing & Description keywords
        $this->assertEquals('listing', CardChecklistItem::detectCategory('Description'));
        $this->assertEquals('listing', CardChecklistItem::detectCategory('Item description'));
        $this->assertEquals('listing', CardChecklistItem::detectCategory('Listing'));
        $this->assertEquals('listing', CardChecklistItem::detectCategory('Ebay Listing'));
        $this->assertEquals('listing', CardChecklistItem::detectCategory('Desc'));

        // Non-matching
        $this->assertNull(CardChecklistItem::detectCategory('General Task : 20TH-OCT'));
        $this->assertNull(CardChecklistItem::detectCategory('Call client'));
    }

    public function test_automatic_detection_respects_card_members_and_categories(): void
    {
        $samnang = User::factory()->create(['name' => 'Samnang', 'is_active' => true]);
        $vouchky = User::factory()->create(['name' => 'Vouchky', 'is_active' => true]);
        $nalin = User::factory()->create(['name' => 'Nalin', 'is_active' => true]); // not on card

        $workspace = Workspace::create(['name' => 'Marketing', 'owner_id' => $samnang->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'SMM Planning Board', 'created_by' => $samnang->id]);
        $list = BoardList::create(['board_id' => $board->id, 'name' => 'Scheduled', 'position' => 1]);

        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Weekly Campaign',
            'created_by' => $samnang->id,
        ]);
        // Card members: Samnang (Video) and Vouchky (Graphic)
        $card->assignees()->attach([$samnang->id, $vouchky->id]);
        $card->load('assignees');

        // Video checklist detects Samnang (not Nalin, because Nalin is not on card)
        $this->assertEquals($samnang->id, CardChecklistItem::detectUserIdForCard('Video Short : 16TH-OCT', $card));

        // Graphic checklist detects Vouchky
        $this->assertEquals($vouchky->id, CardChecklistItem::detectUserIdForCard('1. Poster : 12TH-OCT', $card));

        // General checklist does not detect any user
        $this->assertNull(CardChecklistItem::detectUserIdForCard('General Task : 20TH-OCT', $card));
    }

    public function test_multiple_matching_card_members_does_not_auto_assign(): void
    {
        $samnang = User::factory()->create(['name' => 'Samnang', 'is_active' => true]);
        $nalin = User::factory()->create(['name' => 'Nalin', 'is_active' => true]);
        $vouchky = User::factory()->create(['name' => 'Vouchky', 'is_active' => true]);

        $workspace = Workspace::create(['name' => 'Marketing', 'owner_id' => $samnang->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'SMM Planning Board', 'created_by' => $samnang->id]);
        $list = BoardList::create(['board_id' => $board->id, 'name' => 'Scheduled', 'position' => 1]);

        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Weekly Campaign',
            'created_by' => $samnang->id,
        ]);
        // Both Samnang and Nalin are video users and both are on this card
        $card->assignees()->attach([$samnang->id, $nalin->id, $vouchky->id]);
        $card->load('assignees');

        // Since both Samnang and Nalin match, it must return null so user chooses
        $this->assertNull(CardChecklistItem::detectUserIdForCard('Video Short : 16TH-OCT', $card));

        // Graphic only has Vouchky, so Vouchky is detected
        $this->assertEquals($vouchky->id, CardChecklistItem::detectUserIdForCard('Poster : 12TH-OCT', $card));
    }

    public function test_api_store_and_update_checklist_item_with_assigned_user(): void
    {
        $samnang = User::factory()->create(['name' => 'Samnang', 'is_active' => true]);
        $vouchky = User::factory()->create(['name' => 'Vouchky', 'is_active' => true]);

        $workspace = Workspace::create(['name' => 'Marketing', 'owner_id' => $samnang->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'SMM Planning Board', 'created_by' => $samnang->id]);
        $list = BoardList::create(['board_id' => $board->id, 'name' => 'Scheduled', 'position' => 1]);

        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Weekly Campaign',
            'created_by' => $samnang->id,
        ]);
        $card->assignees()->attach([$samnang->id, $vouchky->id]);

        $checklist = CardChecklist::create([
            'card_id' => $card->id,
            'title' => 'Deliverables',
            'position' => 1,
        ]);

        // 1. Auto-detected via API when assigned_user_id not provided
        $response = $this->actingAs($samnang)->postJson(
            route('boards.cards.checklists.items.store', [$card, $checklist]),
            ['title' => 'Video Short : 16TH-OCT']
        );
        $response->assertStatus(201);
        $this->assertEquals($samnang->id, $response->json('item.assigned_user_id'));
        $this->assertEquals('Samnang', $response->json('item.assigned_user.name'));

        $itemId = $response->json('item.id');

        // 2. Manual override via PATCH to Vouchky
        $patchResponse = $this->actingAs($samnang)->patchJson(
            route('boards.cards.checklists.items.toggle', [$card, $checklist, $itemId]),
            [
                'title' => 'Video Short : 16TH-OCT',
                'assigned_user_id' => $vouchky->id,
            ]
        );
        $patchResponse->assertStatus(200);
        $this->assertEquals($vouchky->id, $patchResponse->json('item.assigned_user_id'));
        $this->assertEquals('Vouchky', $patchResponse->json('item.assigned_user.name'));

        // 3. Clear user assignment
        $clearResponse = $this->actingAs($samnang)->patchJson(
            route('boards.cards.checklists.items.toggle', [$card, $checklist, $itemId]),
            [
                'assigned_user_id' => null,
            ]
        );
        $clearResponse->assertStatus(200);
        $this->assertNull($clearResponse->json('item.assigned_user_id'));
        $this->assertNull($clearResponse->json('item.assigned_user'));
    }

    public function test_twin_card_checklist_sync_preserves_assigned_user(): void
    {
        $samnang = User::factory()->create(['name' => 'Samnang', 'is_active' => true]);
        $vouchky = User::factory()->create(['name' => 'Vouchky', 'is_active' => true]);

        $workspace = Workspace::create(['name' => 'Marketing', 'owner_id' => $samnang->id, 'is_active' => true]);
        $planningBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'SMM Planning Board', 'created_by' => $samnang->id]);
        $workflowBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board', 'created_by' => $samnang->id]);

        $pList = BoardList::create(['board_id' => $planningBoard->id, 'name' => 'Week 1', 'position' => 1]);
        $wList = BoardList::create(['board_id' => $workflowBoard->id, 'name' => 'Draft', 'position' => 1]);

        $cardA = Card::create([
            'board_id' => $planningBoard->id,
            'board_list_id' => $pList->id,
            'title' => 'Weekly Campaign',
            'created_by' => $samnang->id,
        ]);
        $cardB = Card::create([
            'board_id' => $workflowBoard->id,
            'board_list_id' => $wList->id,
            'title' => 'Weekly Campaign',
            'created_by' => $samnang->id,
            'source_card_id' => $cardA->id,
        ]);

        $checklistA = CardChecklist::create([
            'card_id' => $cardA->id,
            'title' => 'Assets',
            'position' => 1,
            'sync_id' => 'sync-chk-1',
        ]);
        $checklistB = CardChecklist::create([
            'card_id' => $cardB->id,
            'title' => 'Assets',
            'position' => 1,
            'sync_id' => 'sync-chk-1',
        ]);

        // Create item on Card A with assigned_user_id
        $itemA = CardChecklistItem::create([
            'checklist_id' => $checklistA->id,
            'title' => 'Video Short : 16TH-OCT',
            'assigned_user_id' => $samnang->id,
            'is_completed' => false,
            'position' => 1,
        ]);

        // Check if item synced to twin checklist on Card B
        $twinItem = CardChecklistItem::where('checklist_id', $checklistB->id)
            ->where('content', 'Video Short : 16TH-OCT')
            ->first();

        $this->assertNotNull($twinItem);
        $this->assertEquals($samnang->id, $twinItem->assigned_user_id);

        // Update itemA assignment to Vouchky
        $itemA->update(['assigned_user_id' => $vouchky->id]);
        $twinItem->refresh();
        $this->assertEquals($vouchky->id, $twinItem->assigned_user_id);
    }

    public function test_listing_and_description_auto_assigns_chhay_on_card(): void
    {
        $chhay = User::factory()->create(['name' => 'Mr. Chhay', 'username' => 'chhay', 'is_active' => true]);
        $sarak = User::factory()->create(['name' => 'Mr. Sarak', 'username' => 'sarak', 'is_active' => true]);
        $pich  = User::factory()->create(['name' => 'Mr. Pich',  'username' => 'pich',  'is_active' => true]);

        $workspace = Workspace::create(['name' => 'Digital Dept', 'owner_id' => $chhay->id, 'is_active' => true]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Workflow board', 'created_by' => $chhay->id]);
        $list = BoardList::create(['board_id' => $board->id, 'name' => 'Week 2', 'position' => 1]);

        $card = Card::create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => 'Project: Content for 0121G Pro',
            'created_by' => $chhay->id,
        ]);
        $card->assignees()->attach([$chhay->id, $sarak->id, $pich->id]);
        $card->load('assignees');

        // Check detectUserIdForCard
        $this->assertEquals($chhay->id, CardChecklistItem::detectUserIdForCard('Description', $card));
        $this->assertEquals($chhay->id, CardChecklistItem::detectUserIdForCard('Listing', $card));
        $this->assertEquals($sarak->id, CardChecklistItem::detectUserIdForCard('Video', $card));
        $this->assertEquals($pich->id,  CardChecklistItem::detectUserIdForCard('Graphic', $card));

        $checklist = CardChecklist::create([
            'card_id' => $card->id,
            'title' => 'Content for 0121G Pro',
            'position' => 1,
        ]);

        // Creating item without explicit assignment auto-detects Chhay
        $item = CardChecklistItem::create([
            'checklist_id' => $checklist->id,
            'content' => 'Description',
            'position' => 1,
        ]);

        $this->assertEquals([$chhay->id], $item->effectiveAssigneeIds());
        $assignedUsers = $item->assigned_users;
        $this->assertCount(1, $assignedUsers);
        $this->assertEquals($chhay->id, $assignedUsers[0]['id']);

        // Posting via API with title 'Description' auto-detects Chhay
        $response = $this->actingAs($chhay)->postJson(
            "/boards/cards/{$card->id}/checklists/{$checklist->id}/items",
            ['title' => 'Description']
        );
        $response->assertCreated();
        $this->assertEquals($chhay->id, $response->json('item.assigned_user_id'));
    }
}
