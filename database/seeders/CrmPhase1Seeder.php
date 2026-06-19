<?php

namespace Database\Seeders;

use App\Enums\InquirySource;
use App\Enums\LeadTemperature;
use App\Enums\ProductCategory;
use App\Enums\WebsiteLeadStatus;
use App\Enums\EbayLeadStatus;
use App\Enums\LogisticStatus;
use App\Enums\AuthorizationStatus;
use App\Models\Customer;
use App\Models\EbayOffer;
use App\Models\Lead;
use App\Models\Logistic;
use App\Models\LogisticUpdate;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class CrmPhase1Seeder extends Seeder
{
    public function run(): void
    {
        $admin = User::role('admin')->first() ?? User::first();
        if (! $admin) { $this->command->warn('No users found. Run AdminUserSeeder first.'); return; }

        // ── Products ─────────────────────────────────────────────────────────
        $products = [
            ['name' => 'CAT 320 Excavator', 'category' => ProductCategory::Excavator, 'brand' => 'CAT', 'model' => '320', 'year' => '2019', 'price' => 185000],
            ['name' => 'Bobcat S650 Skid Steer', 'category' => ProductCategory::SkidSteer, 'brand' => 'Bobcat', 'model' => 'S650', 'year' => '2020', 'price' => 65000],
            ['name' => 'Toyota 3t Forklift', 'category' => ProductCategory::Forklift, 'brand' => 'Toyota', 'model' => '8FG30', 'year' => '2018', 'price' => 28000],
            ['name' => 'Hydraulic Pump Parts', 'category' => ProductCategory::Parts, 'brand' => 'Rexroth', 'model' => 'A10VO', 'year' => null, 'price' => 3500],
            ['name' => 'Komatsu PC200-8 Excavator', 'category' => ProductCategory::Excavator, 'brand' => 'Komatsu', 'model' => 'PC200-8', 'year' => '2021', 'price' => 215000],
        ];

        $productModels = [];
        foreach ($products as $p) {
            $productModels[] = Product::firstOrCreate(
                ['name' => $p['name']],
                [...$p, 'category' => $p['category']->value, 'condition' => 'used', 'is_active' => true, 'created_by' => $admin->id]
            );
        }

        // ── Demo Customers (update with new fields) ───────────────────────────
        $customer1 = Customer::first();

        // ── Website CRM Leads ─────────────────────────────────────────────────
        $leads = [
            [
                'client_name'    => 'Michael Zhang',
                'client_phone'   => '+61 412 111 001',
                'client_email'   => 'michael.z@gmail.com',
                'source'         => InquirySource::Facebook->value,
                'product_interested' => 'Excavator',
                'product_id'     => $productModels[0]->id,
                'inquiry_details'=> 'Interested in CAT 320, asking about pricing and finance options.',
                'status'         => WebsiteLeadStatus::Nurturing->value,
                'temperature'    => LeadTemperature::Hot->value,
                'follow_up_date' => today()->addDays(1)->format('Y-m-d'),
                'next_action'    => 'Call to discuss finance package',
                'received_at'    => now()->subDays(2),
            ],
            [
                'client_name'    => 'Sarah Park',
                'client_phone'   => '+61 412 111 002',
                'client_email'   => 'sarah.park@business.com.au',
                'source'         => InquirySource::Website->value,
                'product_interested' => 'Forklift',
                'product_id'     => $productModels[2]->id,
                'inquiry_details'=> 'Looking for a 3T forklift for warehouse use.',
                'status'         => WebsiteLeadStatus::Contacted->value,
                'temperature'    => LeadTemperature::Warm->value,
                'follow_up_date' => today()->addDays(3)->format('Y-m-d'),
                'received_at'    => now()->subDays(1),
            ],
            [
                'client_name'    => 'Tom Wilson',
                'client_phone'   => '+61 412 111 003',
                'source'         => InquirySource::Phone->value,
                'product_interested' => 'Skid Steer',
                'inquiry_details'=> 'Cold call, asked about Bobcat skid steer availability.',
                'status'         => WebsiteLeadStatus::NewLead->value,
                'temperature'    => LeadTemperature::Cold->value,
                'received_at'    => now()->subHours(3),
            ],
        ];

        foreach ($leads as $l) {
            Lead::firstOrCreate(
                ['client_phone' => $l['client_phone'] ?? null, 'client_name' => $l['client_name']],
                [...$l, 'handled_by' => $admin->id]
            );
        }

        // ── eBay Offers ──────────────────────────────────────────────────────
        $offers = [
            [
                'ebay_username'        => 'heavymach_buyer99',
                'ebay_item_id'         => 'EBAY-ITEM-334455',
                'client_name'          => 'Peter Nguyen',
                'client_email'         => 'peter.n@trademe.com',
                'product_id'           => $productModels[0]->id,
                'offer_details'        => 'Offering $172,000 for CAT 320, can pickup in 2 weeks.',
                'offer_amount'         => 172000,
                'status'               => EbayLeadStatus::WaitingAuthorization->value,
                'authorization_status' => AuthorizationStatus::Pending->value,
                'received_at'          => now()->subHours(5),
                'inquiry_notes'        => 'Buyer contacted via eBay messages. Serious buyer.',
            ],
            [
                'ebay_username'        => 'forklift_deals_au',
                'ebay_item_id'         => 'EBAY-ITEM-556677',
                'client_name'          => 'Amy Lee',
                'client_email'         => 'amy.lee@warehouse.com.au',
                'product_id'           => $productModels[2]->id,
                'offer_details'        => 'Interested in Toyota forklift. Offer: $24,000',
                'offer_amount'         => 24000,
                'final_amount'         => 25500,
                'status'               => EbayLeadStatus::Authorized->value,
                'authorization_status' => AuthorizationStatus::Approved->value,
                'authorized_by'        => $admin->id,
                'authorized_at'        => now()->subHours(2),
                'received_at'          => now()->subDays(1),
            ],
        ];

        $offerModels = [];
        foreach ($offers as $o) {
            $offerModels[] = EbayOffer::firstOrCreate(
                ['ebay_item_id' => $o['ebay_item_id']],
                [...$o, 'handled_by' => $admin->id]
            );
        }

        // ── Logistics ────────────────────────────────────────────────────────
        if ($customer1) {
            $logistic = Logistic::firstOrCreate(
                ['order_id' => 'ORD-2026-001'],
                [
                    'customer_id'       => $customer1->id,
                    'product_id'        => $productModels[0]->id,
                    'created_by'        => $admin->id,
                    'assigned_to'       => $admin->id,
                    'order_id'          => 'ORD-2026-001',
                    'shipping_address'  => '45 Industrial Drive, Dandenong VIC 3175',
                    'recipient_name'    => $customer1->name,
                    'recipient_phone'   => '+61 412 000 001',
                    'truck_company'     => 'OzFreight Pty Ltd',
                    'driver_name'       => 'John Driver',
                    'driver_phone'      => '+61 400 888 001',
                    'shipping_budget'   => 4500,
                    'final_shipping_cost'=> 4200,
                    'tracking_number'   => 'TRK-AU-2026-88441',
                    'status'            => LogisticStatus::InTransit->value,
                    'estimated_arrival' => today()->addDays(2)->format('Y-m-d'),
                    'pickup_datetime'   => now()->subDays(1),
                ]
            );

            LogisticUpdate::firstOrCreate(
                ['logistic_id' => $logistic->id, 'status' => LogisticStatus::InTransit->value],
                [
                    'user_id'     => $admin->id,
                    'notes'       => 'Truck departed depot. ETA 2 days.',
                    'occurred_at' => now()->subHours(12),
                ]
            );
        }

        $this->command->info('✅ CRM Phase 1 seeder complete — products, leads, eBay offers, and logistics created.');
    }
}
