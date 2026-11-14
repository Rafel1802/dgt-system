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
use Tests\TestCase;

class ChecklistUploadIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $daraUser;
    protected Workspace $workspace;
    protected Board $board;
    protected BoardList $list;
    protected Card $card;
    protected CardChecklist $checklist;
    protected CardChecklistItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'name'     => 'Super Admin',
            'username' => 'admin',
            'email'    => 'admin@digital.test',
        ]);

        $this->daraUser = User::factory()->create([
            'name'     => 'Dara SMM',
            'username' => 'dara',
            'email'    => 'dara@digital.test',
        ]);

        $this->workspace = Workspace::create([
            'name'        => 'Marketing Workspace',
            'slug'        => 'marketing-ws',
            'owner_id'    => $this->adminUser->id,
            'is_personal' => false,
        ]);

        $this->board = Board::create([
            'name'         => 'SMM Workflow Board',
            'slug'         => 'smm-workflow-board',
            'workspace_id' => $this->workspace->id,
            'created_by'   => $this->adminUser->id,
        ]);

        $this->list = BoardList::create([
            'board_id' => $this->board->id,
            'name'     => 'Draft',
            'position' => 1,
        ]);

        $this->card = Card::create([
            'board_id'      => $this->board->id,
            'board_list_id' => $this->list->id,
            'title'         => 'Poster & Video Campaign 2026',
            'position'      => 1,
            'created_by'    => $this->adminUser->id,
        ]);

        $this->checklist = CardChecklist::create([
            'card_id'  => $this->card->id,
            'title'    => 'Deliverables Checklist',
            'position' => 1,
        ]);

        $this->item = CardChecklistItem::create([
            'checklist_id' => $this->checklist->id,
            'content'      => 'Poster 1 - 2026-11-15',
            'position'     => 1,
        ]);
    }

    public function test_default_upload_state_is_unuploaded(): void
    {
        $this->assertFalse((bool) $this->item->is_uploaded);
        $this->assertNull($this->item->uploaded_by);
        $this->assertNull($this->item->uploaded_at);
    }

    public function test_upload_can_be_checked_and_persisted(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->patchJson(route('boards.cards.checklists.items.upload', [
                'card'      => $this->card->id,
                'checklist' => $this->checklist->id,
                'item'      => $this->item->id,
            ]), [
                'is_uploaded' => true,
            ]);

        $response->assertOk()
            ->assertJson([
                'success'     => true,
                'is_uploaded' => true,
            ]);

        $this->item->refresh();
        $this->assertTrue((bool) $this->item->is_uploaded);
        $this->assertEquals($this->adminUser->id, $this->item->uploaded_by);
        $this->assertNotNull($this->item->uploaded_at);
    }

    public function test_upload_can_be_unchecked_and_persisted(): void
    {
        $this->item->update([
            'is_uploaded' => true,
            'uploaded_by' => $this->adminUser->id,
            'uploaded_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->patchJson(route('boards.cards.checklists.items.upload', [
                'card'      => $this->card->id,
                'checklist' => $this->checklist->id,
                'item'      => $this->item->id,
            ]), [
                'is_uploaded' => false,
            ]);

        $response->assertOk()
            ->assertJson([
                'success'     => true,
                'is_uploaded' => false,
            ]);

        $this->item->refresh();
        $this->assertFalse((bool) $this->item->is_uploaded);
        $this->assertNull($this->item->uploaded_by);
        $this->assertNull($this->item->uploaded_at);
    }

    public function test_upload_and_check_operate_completely_independently(): void
    {
        // 1. Mark item as approved (Check column)
        $this->actingAs($this->daraUser)
            ->patchJson(route('boards.cards.checklists.items.review', [
                'card'      => $this->card->id,
                'checklist' => $this->checklist->id,
                'item'      => $this->item->id,
            ]), [
                'action' => 'mark',
            ])->assertOk();

        $this->item->refresh();
        $this->assertTrue((bool) $this->item->is_marked);
        $this->assertFalse((bool) $this->item->is_uploaded); // Upload not affected

        // 2. Toggle Upload to checked
        $this->actingAs($this->adminUser)
            ->patchJson(route('boards.cards.checklists.items.upload', [
                'card'      => $this->card->id,
                'checklist' => $this->checklist->id,
                'item'      => $this->item->id,
            ]), [
                'is_uploaded' => true,
            ])->assertOk();

        $this->item->refresh();
        $this->assertTrue((bool) $this->item->is_marked); // Check still marked approved
        $this->assertTrue((bool) $this->item->is_uploaded); // Upload now checked

        // 3. Flag an error in Check column
        $this->actingAs($this->daraUser)
            ->patchJson(route('boards.cards.checklists.items.review', [
                'card'      => $this->card->id,
                'checklist' => $this->checklist->id,
                'item'      => $this->item->id,
            ]), [
                'action' => 'issue',
            ])->assertOk();

        $this->item->refresh();
        $this->assertTrue((bool) $this->item->has_issue);
        $this->assertFalse((bool) $this->item->is_marked);
        $this->assertTrue((bool) $this->item->is_uploaded); // Upload still checked

        // 4. Toggle Upload back to unchecked
        $this->actingAs($this->adminUser)
            ->patchJson(route('boards.cards.checklists.items.upload', [
                'card'      => $this->card->id,
                'checklist' => $this->checklist->id,
                'item'      => $this->item->id,
            ]), [
                'is_uploaded' => false,
            ])->assertOk();

        $this->item->refresh();
        $this->assertTrue((bool) $this->item->has_issue); // Error preserved
        $this->assertFalse((bool) $this->item->is_uploaded); // Upload now unchecked
    }

    public function test_upload_syncs_across_twin_cards_in_same_sync_group(): void
    {
        $syncGroupId = 'twin-sync-group-upload-test';
        $this->card->update(['sync_group_id' => $syncGroupId]);

        // Create a twin card on another board
        $board2 = Board::create([
            'name'         => 'Team B Board',
            'slug'         => 'team-b-board',
            'workspace_id' => $this->workspace->id,
            'created_by'   => $this->adminUser->id,
        ]);
        $list2 = BoardList::create([
            'board_id' => $board2->id,
            'name'     => 'Draft',
            'position' => 1,
        ]);
        $twinCard = Card::create([
            'board_id'      => $board2->id,
            'board_list_id' => $list2->id,
            'title'         => 'Poster & Video Campaign 2026',
            'sync_group_id' => $syncGroupId,
            'position'      => 1,
            'created_by'    => $this->adminUser->id,
        ]);
        $twinChecklist = CardChecklist::create([
            'card_id'  => $twinCard->id,
            'title'    => 'Deliverables Checklist',
            'position' => 1,
        ]);
        $twinItem = CardChecklistItem::create([
            'checklist_id' => $twinChecklist->id,
            'content'      => 'Poster 1 - 2026-11-15',
            'sync_id'      => $this->item->sync_id,
            'position'     => 1,
        ]);

        // Toggle Upload on the original card's item
        $this->actingAs($this->adminUser)
            ->patchJson(route('boards.cards.checklists.items.upload', [
                'card'      => $this->card->id,
                'checklist' => $this->checklist->id,
                'item'      => $this->item->id,
            ]), [
                'is_uploaded' => true,
            ])->assertOk();

        $twinItem->refresh();
        $this->assertTrue((bool) $twinItem->is_uploaded);
        $this->assertEquals($this->adminUser->id, $twinItem->uploaded_by);
    }

    public function test_card_modal_data_includes_upload_fields(): void
    {
        $this->item->update([
            'is_uploaded' => true,
            'uploaded_by' => $this->adminUser->id,
            'uploaded_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('boards.cards.show', $this->card->id));

        $response->assertOk();
        $json = $response->json();
        $this->assertArrayHasKey('card', $json);
        $cardData = $json['card'];
        $this->assertIsArray($cardData['checklists']);
        $this->assertNotEmpty($cardData['checklists']);
        $itemData = $cardData['checklists'][0]['items'][0];
        $this->assertTrue((bool) $itemData['is_uploaded']);
        $this->assertEquals($this->adminUser->id, (int) $itemData['uploaded_by']);
    }

    public function test_reports_and_exports_exclude_upload_information(): void
    {
        $this->item->update([
            'is_completed' => true,
            'completed_by' => $this->adminUser->id,
            'completed_at' => now(),
            'is_marked'    => true,
            'marked_by'    => $this->daraUser->id,
            'marked_at'    => now(),
            'is_uploaded'  => true,
            'uploaded_by'  => $this->adminUser->id,
            'uploaded_at'  => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('boards.export.csv', $this->board->slug));

        $response->assertOk();
        $content = $response->getContent();

        // Must include existing checklist and approval info
        $this->assertStringContainsString('Poster 1 - 2026-11-15', $content);

        // Must NOT include upload headers or upload indicators
        $headersLine = explode("\n", $content)[0] ?? '';
        $this->assertStringNotContainsString('Upload', $headersLine);
        $this->assertStringNotContainsString('Uploaded', $headersLine);
        $this->assertStringNotContainsString('is_uploaded', $content);
    }
}
