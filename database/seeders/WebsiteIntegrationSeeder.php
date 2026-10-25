<?php

namespace Database\Seeders;

use App\Models\Website;
use App\Models\WebsiteIntegration;
use Illuminate\Database\Seeder;

class WebsiteIntegrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $websites = Website::whereDoesntHave('integration')->get();

        foreach ($websites as $website) {
            $keys = WebsiteIntegration::createDefaultCredentials();

            WebsiteIntegration::create([
                'website_id'         => $website->id,
                'connector_type'     => WebsiteIntegration::CONNECTOR_WP_PLUGIN,
                'api_key'            => $keys['api_key'],
                'api_secret'         => $keys['api_secret'],
                'webhook_secret'     => $keys['webhook_secret'],
                'verification_token' => $keys['verification_token'],
                'hostinger_account'  => 'Hostinger Account ' . (($website->id % 3) + 1),
                'cms_status'         => WebsiteIntegration::CMS_STATUS_DISCONNECTED,
                'analytics_status'   => WebsiteIntegration::ANALYTICS_STATUS_NOT_CONFIGURED,
                'is_active'          => true,
            ]);
        }
    }
}
