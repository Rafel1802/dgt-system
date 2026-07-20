<?php

namespace Tests\Feature;

use App\Models\EbayCustomerRecord;
use App\Models\TechSupportCase;
use App\Models\User;
use App\Services\CrmCustomerMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EbayCustomerNegativeFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);

        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole('super-admin');
    }

    public function test_negative_feedback_causes_and_resolution_can_be_saved(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEGATIVES,
            'buyer_name' => 'Nancy Drew',
        ]);

        $this->actingAs($this->user)->put(
            route('crm.ebay.customers.update', $record),
            [
                'tab_type' => $record->tab_type,
                'username' => 'nancy_d',
                'buyer_name' => 'Nancy Drew',
                'informations' => 'Buyer left negative feedback about shipping delay.',
                'negative_feedback_causes' => ['Logistic issues'],
                'negative_feedback_resolved' => '1',
            ]
        )->assertRedirect(route('crm.logistics.issues.index'));

        $record->refresh();
        $this->assertEquals(['Logistic issues'], $record->negative_feedback_causes);
        $this->assertTrue($record->negative_feedback_resolved);
        $this->assertNotNull($record->negative_feedback_resolved_at);
    }

    // ── Negative-feedback-cause routing ─────────────────────────────────────

    public function test_technical_cause_routes_staff_to_tech_support(): void
    {
        $record = EbayCustomerRecord::create(['tab_type' => EbayCustomerRecord::TAB_NEW_ORDER, 'username' => 'cause_tech']);

        $this->actingAs($this->user)->put(
            route('crm.ebay.customers.update', $record),
            [
                'tab_type' => EbayCustomerRecord::TAB_NEGATIVES,
                'username' => 'cause_tech',
                'informations' => 'Buyer says the machine does not turn on.',
                'negative_feedback_causes' => ['Technical'],
            ]
        )->assertRedirect(route('crm.tech-support.index'));
    }

    public function test_logistic_issues_cause_routes_staff_to_logistic_issues(): void
    {
        $record = EbayCustomerRecord::create(['tab_type' => EbayCustomerRecord::TAB_NEW_ORDER, 'username' => 'cause_logistic']);

        $this->actingAs($this->user)->put(
            route('crm.ebay.customers.update', $record),
            [
                'tab_type' => EbayCustomerRecord::TAB_POT_NEGATIVES,
                'username' => 'cause_logistic',
                'informations' => 'Buyer says the package never arrived.',
                'negative_feedback_causes' => ['Logistic issues'],
            ]
        )->assertRedirect(route('crm.logistics.issues.index'));
    }

    /** Customer service has no dedicated queue of its own, so it falls through to the normal list. */
    public function test_customer_service_cause_falls_through_to_the_normal_list_redirect(): void
    {
        $record = EbayCustomerRecord::create(['tab_type' => EbayCustomerRecord::TAB_NEW_ORDER, 'username' => 'cause_cs']);

        $this->actingAs($this->user)->put(
            route('crm.ebay.customers.update', $record),
            [
                'tab_type' => EbayCustomerRecord::TAB_NEGATIVES,
                'username' => 'cause_cs',
                'informations' => 'Buyer unhappy with how the last email was handled.',
                'negative_feedback_causes' => ['Customer service'],
            ]
        )->assertRedirect(route('crm.ebay.customers.index', ['tab_type' => EbayCustomerRecord::TAB_NEGATIVES]));
    }

    /** With no cause selected at all, same fallback applies. */
    public function test_no_cause_selected_falls_through_to_the_normal_list_redirect(): void
    {
        $record = EbayCustomerRecord::create(['tab_type' => EbayCustomerRecord::TAB_NEW_ORDER, 'username' => 'cause_none']);

        $this->actingAs($this->user)->put(
            route('crm.ebay.customers.update', $record),
            [
                'tab_type' => EbayCustomerRecord::TAB_NEGATIVES,
                'username' => 'cause_none',
                'informations' => 'General negative feedback, cause not yet identified.',
            ]
        )->assertRedirect(route('crm.ebay.customers.index', ['tab_type' => EbayCustomerRecord::TAB_NEGATIVES]));
    }

    /** Technical wins over Logistic issues over Customer service when more than one cause is checked. */
    public function test_technical_takes_priority_when_multiple_causes_are_selected(): void
    {
        $record = EbayCustomerRecord::create(['tab_type' => EbayCustomerRecord::TAB_NEW_ORDER, 'username' => 'cause_multi']);

        $this->actingAs($this->user)->put(
            route('crm.ebay.customers.update', $record),
            [
                'tab_type' => EbayCustomerRecord::TAB_NEGATIVES,
                'username' => 'cause_multi',
                'informations' => 'Multiple issues reported.',
                'negative_feedback_causes' => ['Customer service', 'Logistic issues', 'Technical'],
            ]
        )->assertRedirect(route('crm.tech-support.index'));
    }

    public function test_logistic_issues_takes_priority_over_customer_service(): void
    {
        $record = EbayCustomerRecord::create(['tab_type' => EbayCustomerRecord::TAB_NEW_ORDER, 'username' => 'cause_multi2']);

        $this->actingAs($this->user)->put(
            route('crm.ebay.customers.update', $record),
            [
                'tab_type' => EbayCustomerRecord::TAB_NEGATIVES,
                'username' => 'cause_multi2',
                'informations' => 'Multiple issues reported.',
                'negative_feedback_causes' => ['Customer service', 'Logistic issues'],
            ]
        )->assertRedirect(route('crm.logistics.issues.index'));
    }

    /** A cause-based redirect only applies while the record is actually in one of the negative-feedback categories. */
    public function test_cause_routing_does_not_apply_outside_negative_feedback_categories(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEGATIVES,
            'username'  => 'cause_leaving',
            'negative_feedback_causes' => ['Technical'],
        ]);

        $this->actingAs($this->user)->put(
            route('crm.ebay.customers.update', $record),
            ['tab_type' => EbayCustomerRecord::TAB_RESOLVED, 'username' => 'cause_leaving']
        )->assertRedirect(route('crm.ebay.customers.index', ['tab_type' => EbayCustomerRecord::TAB_RESOLVED]));
    }

    public function test_creating_a_record_directly_as_negative_feedback_routes_by_cause(): void
    {
        $this->actingAs($this->user)->post(route('crm.ebay.customers.store'), [
            'tab_type' => EbayCustomerRecord::TAB_NEGATIVES,
            'username' => 'new_negative',
            'informations' => 'Immediate negative feedback on creation.',
            'negative_feedback_causes' => ['Technical'],
        ])->assertRedirect(route('crm.tech-support.index'));
    }

    public function test_creating_a_record_logs_its_initial_status(): void
    {
        $response = $this->actingAs($this->user)->post(route('crm.ebay.customers.store'), [
            'tab_type' => EbayCustomerRecord::TAB_NEW_ORDER,
            'username' => 'nancy_d',
            'order_id' => 'ORD-1',
            'order_date' => now()->toDateString(),
            'products' => [['name' => 'Widget', 'price' => '9.99']],
        ]);
        $response->assertSessionDoesntHaveErrors();

        $record = EbayCustomerRecord::where('username', 'nancy_d')->firstOrFail();

        $this->assertDatabaseHas('ebay_customer_status_history', [
            'ebay_customer_record_id' => $record->id,
            'status' => EbayCustomerRecord::TAB_NEW_ORDER,
        ]);
    }

    public function test_changing_status_is_recorded_in_status_history(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEW_ORDER,
            'username' => 'nancy_d',
        ]);

        $response = $this->actingAs($this->user)->put(
            route('crm.ebay.customers.update', $record),
            ['tab_type' => EbayCustomerRecord::TAB_CANCELATION, 'username' => 'nancy_d']
        );
        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('ebay_customer_status_history', [
            'ebay_customer_record_id' => $record->id,
            'status' => EbayCustomerRecord::TAB_CANCELATION,
        ]);
        $this->assertEquals(EbayCustomerRecord::TAB_CANCELATION, $record->fresh()->tab_type);
    }

    public function test_invalid_cause_is_rejected(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEGATIVES,
            'buyer_name' => 'Nancy Drew',
        ]);

        $this->actingAs($this->user)->put(
            route('crm.ebay.customers.update', $record),
            ['tab_type' => $record->tab_type, 'username' => 'nancy_d', 'buyer_name' => 'Nancy Drew', 'negative_feedback_causes' => ['Not A Real Cause']]
        )->assertSessionHasErrors('negative_feedback_causes.0');
    }

    public function test_a_note_is_required_for_technical_and_negative_feedback_categories(): void
    {
        foreach ([EbayCustomerRecord::TAB_TECHNICAL, EbayCustomerRecord::TAB_POT_NEGATIVES, EbayCustomerRecord::TAB_NEGATIVES] as $tab) {
            $record = EbayCustomerRecord::create(['tab_type' => EbayCustomerRecord::TAB_NEW_ORDER, 'username' => 'issue_'.$tab]);

            $response = $this->actingAs($this->user)->put(
                route('crm.ebay.customers.update', $record),
                ['tab_type' => $tab, 'username' => 'issue_'.$tab]
            );

            $response->assertSessionHasErrors('informations');
            $this->assertEquals(EbayCustomerRecord::TAB_NEW_ORDER, $record->fresh()->tab_type);
        }
    }

    public function test_a_note_is_not_required_for_other_categories(): void
    {
        $record = EbayCustomerRecord::create(['tab_type' => EbayCustomerRecord::TAB_URGENT, 'username' => 'no_note_needed']);

        $response = $this->actingAs($this->user)->put(
            route('crm.ebay.customers.update', $record),
            ['tab_type' => EbayCustomerRecord::TAB_NEW_ORDER, 'username' => 'no_note_needed', 'order_id' => 'ORD-1', 'order_date' => now()->toDateString(), 'products' => [['name' => 'Widget', 'price' => '9.99']]]
        );

        $response->assertSessionDoesntHaveErrors();
    }

    // ── Negative-feedback causes actually surface on the real department pages ──

    public function test_technical_cause_creates_a_real_tech_support_case(): void
    {
        $record = EbayCustomerRecord::create(['tab_type' => EbayCustomerRecord::TAB_NEW_ORDER, 'username' => 'real_case_tech']);

        $this->actingAs($this->user)->put(
            route('crm.ebay.customers.update', $record),
            [
                'tab_type' => EbayCustomerRecord::TAB_NEGATIVES,
                'username' => 'real_case_tech',
                'informations' => 'Machine does not power on.',
                'negative_feedback_causes' => ['Technical'],
            ]
        );

        $case = TechSupportCase::where('source_type', EbayCustomerRecord::class)->where('source_id', $record->id)->first();
        $this->assertNotNull($case, 'Expected a real TechSupportCase to be created.');
        $this->assertEquals(TechSupportCase::STATUS_NEW, $case->status);

        // And it genuinely shows up on the Tech Support index, not just an empty redirect target.
        $tech = User::factory()->create(['is_active' => true]);
        $tech->assignRole('tech-support');
        $this->actingAs($tech)->get(route('crm.tech-support.index'))->assertSee('real_case_tech');
    }

    /** Saving the same technical negative feedback again must not spawn a second case. */
    public function test_resaving_the_same_technical_cause_does_not_duplicate_the_case(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEGATIVES,
            'username' => 'no_dupe_case',
            'negative_feedback_causes' => ['Technical'],
        ]);
        $this->assertEquals(1, TechSupportCase::count());

        $this->actingAs($this->user)->put(
            route('crm.ebay.customers.update', $record),
            [
                'tab_type' => EbayCustomerRecord::TAB_NEGATIVES,
                'username' => 'no_dupe_case',
                'informations' => 'Still an issue, adding more detail.',
                'negative_feedback_causes' => ['Technical'],
            ]
        );

        $this->assertEquals(1, TechSupportCase::count());
    }

    public function test_logistic_issues_cause_appears_on_the_logistic_issues_page(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEGATIVES,
            'username' => 'logistic_cause_customer',
            'buyer_name' => 'Logistic Cause Customer',
            'negative_feedback_causes' => ['Logistic issues'],
        ]);

        $directory = app(CrmCustomerMatchService::class)->buildUnifiedDirectory();
        $row = $directory->firstWhere('id', $record->id);

        $this->assertNotNull($row);
        $this->assertEquals('shipment_delay', $row['category']);
        $this->assertEquals('Logistic issues', $row['status_label']);

        $this->actingAs($this->user)->get(route('crm.logistics.issues.index'))->assertSee('Logistic Cause Customer');
    }

    /** Unchecking the cause (or moving off the negative-feedback status) must stop matching — it's computed live, not a stored flag. */
    public function test_removing_the_logistic_cause_removes_it_from_the_logistic_issues_page(): void
    {
        $record = EbayCustomerRecord::create([
            'tab_type' => EbayCustomerRecord::TAB_NEGATIVES,
            'username' => 'logistic_cause_removed',
            'buyer_name' => 'No Longer Logistic',
            'negative_feedback_causes' => ['Logistic issues'],
        ]);

        $this->actingAs($this->user)->put(
            route('crm.ebay.customers.update', $record),
            [
                'tab_type' => EbayCustomerRecord::TAB_NEGATIVES,
                'username' => 'logistic_cause_removed',
                'informations' => 'Turned out to be a customer service matter.',
                'negative_feedback_causes' => ['Customer service'],
            ]
        );

        $row = app(CrmCustomerMatchService::class)->buildUnifiedDirectory()->firstWhere('id', $record->id);
        $this->assertEquals('negative_feedback', $row['category']);
    }
}
