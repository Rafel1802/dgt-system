<?php

namespace Database\Seeders;

use App\Models\CrmExternalLink;
use Illuminate\Database\Seeder;

class CrmExternalLinkSeeder extends Seeder
{
    public function run(): void
    {
        $links = [
            [
                'name'        => 'WhatsApp',
                'url'         => 'https://web.whatsapp.com',
                'icon'        => 'whatsapp',
                'icon_url'    => 'https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg',
                'description' => 'WhatsApp Web for CRM team',
                'sort_order'  => 1,
                'is_active'   => true,
            ],
            [
                'name'        => 'Google Docs',
                'url'         => 'https://docs.google.com',
                'icon'        => 'docs',
                'icon_url'    => 'https://upload.wikimedia.org/wikipedia/commons/0/01/Google_Docs_logo_%282014-2020%29.svg',
                'description' => 'Google Docs — shared documents',
                'sort_order'  => 2,
                'is_active'   => true,
            ],
            [
                'name'        => 'Google Sheets',
                'url'         => 'https://sheets.google.com',
                'icon'        => 'sheets',
                'icon_url'    => 'https://upload.wikimedia.org/wikipedia/commons/3/30/Google_Sheets_logo_%282014-2020%29.svg',
                'description' => 'Google Sheets — spreadsheets & eBay templates',
                'sort_order'  => 3,
                'is_active'   => true,
            ],
            [
                'name'        => 'Google Translate',
                'url'         => 'https://translate.google.com',
                'icon'        => 'translate',
                'icon_url'    => 'https://upload.wikimedia.org/wikipedia/commons/d/d7/Google_Translate_logo.svg',
                'description' => 'Google Translate',
                'sort_order'  => 4,
                'is_active'   => true,
            ],
        ];

        foreach ($links as $link) {
            CrmExternalLink::firstOrCreate(
                ['name' => $link['name']],
                $link
            );
        }

        $this->command->info('CRM External Links seeded: ' . count($links) . ' links.');
    }
}
