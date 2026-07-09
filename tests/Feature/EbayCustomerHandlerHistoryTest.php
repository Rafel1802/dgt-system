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
}
