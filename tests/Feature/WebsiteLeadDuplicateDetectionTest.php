<?php

namespace Tests\Feature;

use App\Enums\InquirySource;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class WebsiteLeadDuplicateDetectionTest extends TestCase
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

    private function makeLead(array $attrs): Lead
    {
        return Lead::create(array_merge([
            'handled_by'  => $this->user->id,
            'source'      => InquirySource::Website->value,
            'status'      => 'new_inquiry',
            'received_at' => now(),
        ], $attrs));
    }

    public function test_duplicate_name_and_phone_is_flagged(): void
    {
        // DB lead
        $this->makeLead([
            'client_name'  => 'John Doe',
            'client_phone' => '+1 (555) 123-4567',
        ]);

        // Current page lead
        $this->makeLead([
            'client_name'  => 'John Doe',
            'client_phone' => '+1 (555) 123-4567',
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.website.index'));

        $response->assertOk();
        $response->assertSee('bg-red-50 text-red-700'); // Red badge for definite duplicate
    }

    public function test_duplicate_name_and_email_is_flagged(): void
    {
        // DB lead
        $this->makeLead([
            'client_name'  => 'Jane Doe',
            'client_email' => 'jane@example.com',
        ]);

        // Current page lead
        $this->makeLead([
            'client_name'  => 'Jane Doe',
            'client_email' => 'jane@example.com',
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.website.index'));

        $response->assertOk();
        $response->assertSee('bg-red-50 text-red-700'); // Red badge for definite duplicate
    }

    public function test_phone_only_duplicate_is_flagged_as_possible(): void
    {
        // DB lead
        $this->makeLead([
            'client_name'  => 'John Original',
            'client_phone' => '+1 (555) 123-4567',
        ]);

        // Current page lead with same phone but different name
        $this->makeLead([
            'client_name'  => 'John Clone',
            'client_phone' => '+1 (555) 123-4567',
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.website.index'));

        $response->assertOk();
        $response->assertSee('bg-slate-100 text-slate-500'); // Gray badge for possible duplicate
    }

    public function test_email_only_duplicate_is_flagged_as_possible(): void
    {
        // DB lead
        $this->makeLead([
            'client_name'  => 'Jane Original',
            'client_email' => 'jane@example.com',
        ]);

        // Current page lead with same email but different name
        $this->makeLead([
            'client_name'  => 'Jane Clone',
            'client_email' => 'jane@example.com',
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.website.index'));

        $response->assertOk();
        $response->assertSee('bg-slate-100 text-slate-500'); // Gray badge for possible duplicate
    }

    public function test_non_duplicates_are_not_flagged(): void
    {
        $this->makeLead([
            'client_name'  => 'Alex Smith',
            'client_phone' => '+1 (555) 999-9999',
            'client_email' => 'alex@example.com',
        ]);

        $this->makeLead([
            'client_name'  => 'Bill Jones',
            'client_phone' => '+1 (555) 888-8888',
            'client_email' => 'bill@example.com',
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.website.index'));

        $response->assertOk();
        $response->assertDontSee('bg-red-50 text-red-700');
        $response->assertDontSee('bg-slate-100 text-slate-500');
    }

    public function test_within_page_duplicates_are_flagged(): void
    {
        // Two identical leads created
        $this->makeLead([
            'client_name'  => 'Double Trouble',
            'client_phone' => '+1 (555) 777-7777',
        ]);

        $this->makeLead([
            'client_name'  => 'Double Trouble',
            'client_phone' => '+1 (555) 777-7777',
        ]);

        $response = $this->actingAs($this->user)->get(route('crm.website.index'));

        $response->assertOk();
        $response->assertSee('bg-red-50 text-red-700');
    }
}
