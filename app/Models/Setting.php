<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function externalToolDefinitions(): array
    {
        return [
            [
                'key' => 'hosting_image_url',
                'label' => 'Hosting Image',
                'short_label' => 'Hosting Image',
                'description' => 'Open your hosted image manager.',
                'group' => 'board',
                'icon' => 'image',
                'tone' => 'sky',
            ],
            [
                'key' => 'backup_server_url',
                'label' => 'Backup Server (Img host)',
                'short_label' => 'Backup Server',
                'description' => 'Open the image backup server.',
                'group' => 'board',
                'icon' => 'server',
                'tone' => 'emerald',
            ],
            [
                'key' => 'ebay_template_generator_url',
                'label' => 'eBay Template Generator',
                'short_label' => 'eBay Template',
                'description' => 'Launch the eBay template generator.',
                'group' => 'board',
                'icon' => 'template',
                'tone' => 'indigo',
            ],
            [
                'key' => 'poster_prompt_url',
                'label' => 'Poster Prompt',
                'short_label' => 'Poster Prompt',
                'description' => 'Create poster prompts faster.',
                'group' => 'generator',
                'icon' => 'sparkles',
                'tone' => 'pink',
            ],
            [
                'key' => 'selling_point_url',
                'label' => 'Selling Point',
                'short_label' => 'Selling Point',
                'description' => 'Open selling point generator.',
                'group' => 'generator',
                'icon' => 'target',
                'tone' => 'amber',
            ],
            [
                'key' => 'youtube_thumbnail_url',
                'label' => 'YouTube Thumbnail',
                'short_label' => 'YouTube Thumbnail',
                'description' => 'Launch thumbnail generator.',
                'group' => 'generator',
                'icon' => 'play',
                'tone' => 'red',
            ],
            [
                'key' => 'spec_converter_url',
                'label' => 'Spec Converter',
                'short_label' => 'Spec Converter',
                'description' => 'Convert product specs.',
                'group' => 'generator',
                'icon' => 'convert',
                'tone' => 'blue',
            ],
            [
                'key' => 'google_drive_url',
                'label' => 'Google Drive',
                'short_label' => 'Google Drive',
                'description' => 'Open Google Drive with specific profile.',
                'group' => 'workspace',
                'icon' => 'server',
                'tone' => 'blue',
            ],
            [
                'key' => 'google_docs_url',
                'label' => 'Google Docs',
                'short_label' => 'Google Docs',
                'description' => 'Open Google Docs with specific profile.',
                'group' => 'workspace',
                'icon' => 'document',
                'tone' => 'blue',
            ],
            [
                'key' => 'google_sheets_url',
                'label' => 'Google Sheets',
                'short_label' => 'Google Sheets',
                'description' => 'Open Google Sheets with specific profile.',
                'group' => 'workspace',
                'icon' => 'template',
                'tone' => 'emerald',
            ],
            [
                'key' => 'google_translate_url',
                'label' => 'Google Translate',
                'short_label' => 'Google Translate',
                'description' => 'Open Google Translate with specific profile.',
                'group' => 'workspace',
                'icon' => 'sparkles',
                'tone' => 'indigo',
            ],
            [
                'key' => 'weekly_report_url',
                'label' => 'Weekly Report',
                'short_label' => 'Weekly Report',
                'description' => 'Weekly report update link.',
                'group' => 'board',
                'icon' => 'document',
                'tone' => 'pink',
            ],
            [
                'key' => 'whatsapp_url',
                'label' => 'WhatsApp Web',
                'short_label' => 'WhatsApp',
                'description' => 'Open WhatsApp Web.',
                'group' => 'workspace',
                'icon' => 'chat',
                'tone' => 'emerald',
            ],
            [
                'key' => 'ai_tool_1',
                'label' => 'AI Tool 1',
                'short_label' => 'AI Tool 1',
                'description' => 'Configure custom AI Tool 1',
                'group' => 'ai',
                'icon' => 'sparkles',
                'tone' => 'indigo',
            ],
            [
                'key' => 'ai_tool_2',
                'label' => 'AI Tool 2',
                'short_label' => 'AI Tool 2',
                'description' => 'Configure custom AI Tool 2',
                'group' => 'ai',
                'icon' => 'sparkles',
                'tone' => 'indigo',
            ],
            [
                'key' => 'ai_tool_3',
                'label' => 'AI Tool 3',
                'short_label' => 'AI Tool 3',
                'description' => 'Configure custom AI Tool 3',
                'group' => 'ai',
                'icon' => 'sparkles',
                'tone' => 'indigo',
            ],
            [
                'key' => 'ai_tool_4',
                'label' => 'AI Tool 4',
                'short_label' => 'AI Tool 4',
                'description' => 'Configure custom AI Tool 4',
                'group' => 'ai',
                'icon' => 'sparkles',
                'tone' => 'indigo',
            ],
            [
                'key' => 'ai_tool_5',
                'label' => 'AI Tool 5',
                'short_label' => 'AI Tool 5',
                'description' => 'Configure custom AI Tool 5',
                'group' => 'ai',
                'icon' => 'sparkles',
                'tone' => 'indigo',
            ],
        ];
    }

    public static function externalToolKeys(): array
    {
        return array_column(self::externalToolDefinitions(), 'key');
    }

    public static function externalTools(): array
    {
        return array_map(function (array $tool): array {
            $tool['url'] = self::get($tool['key']);
            $tool['icon_url'] = self::get($tool['key'] . '_icon');

            $customLabel = self::get($tool['key'] . '_label');
            if (filled($customLabel)) {
                $tool['label'] = $customLabel;
                $tool['short_label'] = $customLabel;
            }

            return $tool;
        }, self::externalToolDefinitions());
    }

    public static function externalToolsForGroup(string $group, bool $configuredOnly = false): array
    {
        $tools = array_values(array_filter(
            self::externalTools(),
            fn (array $tool): bool => $tool['group'] === $group
        ));

        $customToolsKey = "custom_{$group}_tools";
        $customToolsJson = self::get($customToolsKey, '[]');
        $customTools = json_decode($customToolsJson, true) ?: [];

        foreach ($customTools as &$ct) {
            $ct['group'] = $group;
        }

        $tools = array_merge($tools, $customTools);

        // Sort by saved group-specific order
        $orderKey = $group . '_tools_order';
        $savedOrderJson = self::get($orderKey, '[]');
        $savedOrder = json_decode($savedOrderJson, true) ?: [];

        if (!empty($savedOrder)) {
            usort($tools, function ($a, $b) use ($savedOrder) {
                $keyA = $a['key'] ?? $a['custom_id'] ?? null;
                $keyB = $b['key'] ?? $b['custom_id'] ?? null;

                $posA = $keyA ? array_search($keyA, $savedOrder) : false;
                $posB = $keyB ? array_search($keyB, $savedOrder) : false;

                $posA = ($posA !== false) ? $posA : 9999;
                $posB = ($posB !== false) ? $posB : 9999;

                return $posA <=> $posB;
            });
        }

        if (! $configuredOnly) {
            return $tools;
        }

        return array_values(array_filter(
            $tools,
            fn (array $tool): bool => filled($tool['url'] ?? null)
        ));
    }

    /**
     * Get a setting value by key, or return default.
     */
    public static function get(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }
}
