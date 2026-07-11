<?php

namespace Tests\Feature;

use App\Models\EbayCustomerRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EbayCustomerHandlerHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole('super-admin');

        $this->otherUser = User::factory()->create(['is_active' => true]);
        $this->otherUser->assignRole('sales-crm');
    }

    public function test_creating_a_record_opens_an_initial_handler_history_entry(): void
    {
        $this->actingAs($this->user)->post(route('crm.ebay.customers.store'), [
            'tab_type' => 'urgent_client',
            'buyer_name' => 'Tom Baker',
            'username' => 'tbaker_88',
        ]);

        $record = EbayCustomerRecord::firstOrFail();

        $this->assertDatabaseHas('ebay_customer_handler_history', [
            'ebay_customer_record_id' => $record->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertEquals($this->user->id, $record->current_handler->id);
    }

    public function test_switching_handler_closes_the_previous_entry_and_opens_a_new_one(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_URGENT,
            'buyer_name' => 'Tom Baker',
        ]);

        \App\Models\EbayCustomerHandlerHistory::create([
            'ebay_customer_record_id' => $record->id,
            'user_id' => $this->user->id,
            'started_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->user)->postJson(
            route('crm.ebay.customers.switch-handler', $record),
            ['user_id' => $this->otherUser->id]
        );

        $response->assertOk();

        $record->refresh();
        $this->assertEquals($this->otherUser->id, $record->current_handler->id);
        $this->assertEquals(2, $record->handlerHistory()->count());
        $this->assertNotNull($record->handlerHistory()->where('user_id', $this->user->id)->first()->ended_at);
    }

    public function test_a_new_handler_assignment_starts_unconfirmed(): void
    {
        $record = EbayCustomerRecord::create(['tab_type' => EbayCustomerRecord::TAB_URGENT, 'buyer_name' => 'Tom Baker']);

        $this->actingAs($this->user)->postJson(
            route('crm.ebay.customers.switch-handler', $record),
            ['user_id' => $this->otherUser->id]
        )->assertOk();

        $entry = \App\Models\EbayCustomerHandlerHistory::where('ebay_customer_record_id', $record->id)
            ->where('user_id', $this->otherUser->id)->firstOrFail();

        $this->assertNull($entry->confirmed_at);
        $this->assertEquals(1, \App\Models\EbayCustomerHandlerHistory::pendingConfirmation()->where('user_id', $this->otherUser->id)->count());
    }

    public function test_the_assigned_handler_can_confirm_their_assignment(): void
    {
        $record = EbayCustomerRecord::create(['tab_type' => EbayCustomerRecord::TAB_URGENT, 'buyer_name' => 'Tom Baker']);
        $entry = \App\Models\EbayCustomerHandlerHistory::create([
            'ebay_customer_record_id' => $record->id,
            'user_id' => $this->otherUser->id,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->otherUser)->post(route('crm.ebay.customers.handler-history.confirm', $entry));

        $response->assertRedirect(route('crm.ebay.customers.show', $record));
        $this->assertNotNull($entry->fresh()->confirmed_at);
    }

    public function test_a_different_user_cannot_confirm_someone_elses_assignment(): void
    {
        $record = EbayCustomerRecord::create(['tab_type' => EbayCustomerRecord::TAB_URGENT, 'buyer_name' => 'Tom Baker']);
        $entry = \App\Models\EbayCustomerHandlerHistory::create([
            'ebay_customer_record_id' => $record->id,
            'user_id' => $this->otherUser->id,
            'started_at' => now(),
        ]);

        $this->actingAs($this->user)->post(route('crm.ebay.customers.handler-history.confirm', $entry))
            ->assertForbidden();

        $this->assertNull($entry->fresh()->confirmed_at);
    }

    public function test_pending_handler_confirmation_shows_in_the_profile_dropdown_with_confirm_and_view_actions(): void
    {
        $record = EbayCustomerRecord::create(['tab_type' => EbayCustomerRecord::TAB_URGENT, 'buyer_name' => 'Dropdown Test Customer']);
        $entry = \App\Models\EbayCustomerHandlerHistory::create([
            'ebay_customer_record_id' => $record->id,
            'user_id' => $this->otherUser->id,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->otherUser)->get(route('crm.ebay.customers.index'));

        $response->assertOk();
        $response->assertSee('Dropdown Test Customer');
        $response->assertSee(route('crm.ebay.customers.handler-history.confirm', $entry), false);
        $response->assertSee(route('crm.ebay.customers.show', $record), false);
    }

    public function test_confirmed_handler_assignment_no_longer_shows_in_the_profile_dropdown(): void
    {
        $record = EbayCustomerRecord::create(['tab_type' => EbayCustomerRecord::TAB_URGENT, 'buyer_name' => 'Already Confirmed Customer']);
        $entry = \App\Models\EbayCustomerHandlerHistory::create([
            'ebay_customer_record_id' => $record->id,
            'user_id' => $this->otherUser->id,
            'started_at' => now(),
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($this->otherUser)->get(route('crm.ebay.customers.index'));

        $response->assertOk();
        // The customer's name legitimately appears in the main list table on
        // this same page — check the dropdown-only marker (its "New Handler
        // Assignment" header and the Confirm form's action URL) is absent
        // instead of the customer name, which would give a false failure.
        $response->assertDontSee('New Handler Assignment');
        $response->assertDontSee(route('crm.ebay.customers.handler-history.confirm', $entry), false);
    }
}
