<?php

namespace Database\Seeders;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\DealStage;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

class CrmSeeder extends Seeder
{
    public function run(): void
    {
        $staff = User::role(['sales-crm', 'admin-crm'])->first()
               ?? User::role('super-admin')->first()
               ?? User::first();

        if (! $staff) return;

        $customers = [
            [
                'name' => 'James Holden', 'email' => 'j.holden@rocinante.au', 'phone' => '+61 412 000 001',
                'company' => 'Rocinante Pty Ltd', 'job_title' => 'CEO',
                'status' => CustomerStatus::Active->value, 'source' => CustomerSource::Referral->value,
                'pipeline_stage' => DealStage::Won->value, 'has_purchased' => true,
                'lifetime_value' => 8500.00, 'total_orders' => 3,
                'first_purchase_date' => '2025-09-01', 'last_purchase_date' => '2026-02-15',
                'product_interests' => ['Website Creation', 'SEO', 'Social Media'],
                'tags' => ['vip', 'repeat-customer'],
                'country' => 'AU', 'city' => 'Sydney',
            ],
            [
                'name' => 'Naomi Nagata', 'email' => 'naomi@beratnas.com', 'phone' => '+61 412 000 002',
                'company' => 'Beratnas Gas', 'job_title' => 'Operations Manager',
                'status' => CustomerStatus::Prospect->value, 'source' => CustomerSource::Ebay->value,
                'pipeline_stage' => DealStage::ProposalSent->value, 'has_purchased' => false,
                'lifetime_value' => 0, 'total_orders' => 0,
                'product_interests' => ['eBay Listing', 'Product Photography'],
                'tags' => ['follow-up'],
                'country' => 'AU', 'city' => 'Melbourne',
            ],
            [
                'name' => 'Amos Burton', 'email' => 'amos@burton.net', 'phone' => '+61 412 000 003',
                'company' => null, 'job_title' => 'Freelancer',
                'status' => CustomerStatus::Lead->value, 'source' => CustomerSource::SocialMedia->value,
                'pipeline_stage' => DealStage::Contacted->value, 'has_purchased' => false,
                'lifetime_value' => 0, 'total_orders' => 0,
                'product_interests' => ['Graphic Design', 'Video Production'],
                'tags' => ['new-lead'],
                'country' => 'AU', 'city' => 'Brisbane',
            ],
            [
                'name' => 'Alex Kamal', 'email' => 'alex.k@martian.com.au', 'phone' => '+61 412 000 004',
                'company' => 'Martian Congressional Republic', 'job_title' => 'Marketing Director',
                'status' => CustomerStatus::Active->value, 'source' => CustomerSource::ColdCall->value,
                'pipeline_stage' => DealStage::Negotiating->value, 'has_purchased' => true,
                'lifetime_value' => 3200.00, 'total_orders' => 1,
                'first_purchase_date' => '2026-01-10', 'last_purchase_date' => '2026-01-10',
                'product_interests' => ['Marketing', 'Social Media', 'Video Production'],
                'tags' => ['enterprise'],
                'country' => 'AU', 'city' => 'Perth',
            ],
            [
                'name' => 'Chrisjen Avasarala', 'email' => 'c.avasarala@un.gov', 'phone' => '+61 412 000 005',
                'company' => 'United Nations', 'job_title' => 'Secretary-General',
                'status' => CustomerStatus::Active->value, 'source' => CustomerSource::Website->value,
                'pipeline_stage' => DealStage::Won->value, 'has_purchased' => true,
                'lifetime_value' => 22000.00, 'total_orders' => 7,
                'first_purchase_date' => '2025-03-01', 'last_purchase_date' => '2026-04-01',
                'product_interests' => ['Website Creation', 'SEO', 'Graphic Design', 'Video Production'],
                'tags' => ['vip', 'enterprise', 'priority'],
                'country' => 'AU', 'city' => 'Canberra',
            ],
        ];

        foreach ($customers as $data) {
            $customer = Customer::firstOrCreate(
                ['email' => $data['email']],
                array_merge($data, ['created_by' => $staff->id, 'assigned_to' => $staff->id])
            );

            // Add a sample interaction
            $customer->interactions()->firstOrCreate(
                ['type' => 'note', 'user_id' => $staff->id],
                [
                    'subject' => 'Initial contact',
                    'content' => "First contact established with {$customer->name}. Customer is interested in our services.",
                    'outcome' => 'positive',
                    'interacted_at' => now()->subDays(rand(1, 30)),
                ]
            );

        }

        // --- Seed eBay Stores ---
        $stores = [
            ['store_name' => 'TechGadgetsStore', 'store_url' => 'https://ebay.com/usr/techgadgets', 'notes' => 'Top Rated Seller'],
            ['store_name' => 'VintageCollectibles', 'store_url' => 'https://ebay.com/usr/vintageco', 'notes' => 'Collectibles and antiques'],
        ];
        foreach ($stores as $s) {
            \App\Models\EbayStore::firstOrCreate(['store_name' => $s['store_name']], $s);
        }

        // --- Seed Trucking Companies ---
        $truckingCompanies = [
            ['company_name' => 'FastFreight Logistics', 'pic_name' => 'John Doe', 'phone' => '123-456-7890', 'email' => 'dispatch@fastfreight.com', 'is_active' => true],
            ['company_name' => 'Reliable Transport', 'pic_name' => 'Jane Smith', 'phone' => '098-765-4321', 'email' => 'info@reliablet.com', 'is_active' => true],
        ];
        foreach ($truckingCompanies as $tc) {
            \App\Models\TruckingCompany::firstOrCreate(['company_name' => $tc['company_name']], $tc);
        }

        // --- Seed Shipments ---
        if (\App\Models\Customer::count() > 0) {
            $trucking = \App\Models\TruckingCompany::first();
            $customer1 = \App\Models\Customer::first();
            $customer2 = \App\Models\Customer::skip(1)->first();

            $shipment = \App\Models\Shipment::firstOrCreate(
                ['shipment_code' => 'SHP-10001'],
                [
                    'trucking_company_id' => $trucking->id ?? null,
                    'estimated_arrival' => now()->addDays(3),
                    'status' => 'pending',
                    'assigned_to' => $staff->id,
                    'created_by' => $staff->id,
                    'notes' => 'Sample shipment',
                ]
            );

            // Attach customers to shipment
            if ($customer1) {
                \App\Models\ShipmentCustomer::firstOrCreate([
                    'shipment_id' => $shipment->id,
                    'customer_id' => $customer1->id,
                ], [
                    'recipient_name' => $customer1->name,
                    'recipient_phone' => $customer1->phone,
                    'shipping_address' => '123 Main St, Sydney',
                    'status' => 'pending'
                ]);
            }
            if ($customer2) {
                \App\Models\ShipmentCustomer::firstOrCreate([
                    'shipment_id' => $shipment->id,
                    'customer_id' => $customer2->id,
                ], [
                    'recipient_name' => $customer2->name,
                    'recipient_phone' => $customer2->phone,
                    'shipping_address' => '456 Market St, Melbourne',
                    'status' => 'pending'
                ]);
            }
        }

        $this->command->info('✅ CRM seed complete — 5 customers + deals, stores, trucking, and shipments created.');
    }
}
