<?php

namespace Tests\Feature;

use App\Models\Ai\AiTranslation;
use App\Models\User;
use App\Services\Ai\GroqClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class KiuqAiTranslationStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_translation_endpoints(): void
    {
        $this->getJson('/ai/translate/languages')->assertStatus(401);
        $this->postJson('/ai/translate', [
            'text'            => 'Hello world',
            'source_language' => 'en-US',
            'target_language' => 'km',
        ])->assertStatus(401);
    }

    public function test_language_catalog_returns_46_locales_and_8_modes(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->getJson('/ai/translate/languages');

        $response->assertOk()
            ->assertJson(['success' => true]);

        $languages = $response->json('languages');
        $modes = $response->json('modes');

        $this->assertCount(46, $languages);
        $this->assertCount(8, $modes);

        $codes = array_column($languages, 'code');
        $this->assertContains('en-US', $codes);
        $this->assertContains('km', $codes);
        $this->assertContains('zh-CN', $codes);
        $this->assertContains('zh-TW', $codes);
        $this->assertContains('ar', $codes);
    }

    public function test_auto_detect_is_conservative_on_short_latin_and_confident_on_distinct_scripts(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        // Short ambiguous Latin input should NOT falsely claim certainty
        $ambiguous = $this->actingAs($user)->postJson('/ai/translate/detect', [
            'text' => 'ok',
        ]);
        $ambiguous->assertOk()
            ->assertJson([
                'success'   => true,
                'detection' => [
                    'reliable'   => false,
                    'confidence' => 'ambiguous',
                ],
            ]);

        // Distinct Khmer Unicode script should be detected with high confidence
        $khmer = $this->actingAs($user)->postJson('/ai/translate/detect', [
            'text' => 'សួស្តីពិភពលោក',
        ]);
        $khmer->assertOk()
            ->assertJson([
                'success'   => true,
                'detection' => [
                    'detected_code' => 'km',
                    'confidence'    => 'high',
                    'reliable'      => true,
                ],
            ]);
    }

    public function test_translation_studio_translates_persists_history_favorites_and_deletes_per_user(): void
    {
        $userA = User::factory()->create(['is_active' => true]);
        $userB = User::factory()->create(['is_active' => true]);

        $this->mock(GroqClientService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('generateContent')
                ->once()
                ->andReturn([
                    'text' => 'សួស្តីក្រុមការងារ KIUQ SYSTEM!',
                ]);
        });

        $translateRes = $this->actingAs($userA)->postJson('/ai/translate', [
            'text'             => 'Hello KIUQ SYSTEM team!',
            'source_language'  => 'auto',
            'target_language'  => 'km',
            'mode'             => 'professional',
            'advanced_options' => [
                'tone'     => 'Executive & polite',
                'glossary' => 'KIUQ SYSTEM',
            ],
        ]);

        $translateRes->assertOk()
            ->assertJson([
                'success'         => true,
                'translated_text' => 'សួស្តីក្រុមការងារ KIUQ SYSTEM!',
                'provider_model'  => 'llama-3.3-70b-versatile',
            ]);

        $historyId = $translateRes->json('history_item.id');
        $this->assertNotNull($historyId);
        $this->assertDatabaseHas('ai_translations', [
            'id'               => $historyId,
            'user_id'          => $userA->id,
            'target_language'  => 'km',
            'translation_mode' => 'professional',
        ]);

        // User B cannot see or favorite User A's translation history
        $historyB = $this->actingAs($userB)->getJson('/ai/translate/history');
        $historyB->assertOk()->assertJsonCount(0, 'history');

        $this->actingAs($userB)
            ->postJson("/ai/translate/history/{$historyId}/favorite")
            ->assertStatus(404);

        // User A can favorite, search, and delete their own history record
        $favRes = $this->actingAs($userA)->postJson("/ai/translate/history/{$historyId}/favorite");
        $favRes->assertOk()->assertJson([
            'success'     => true,
            'is_favorite' => true,
        ]);

        $searchRes = $this->actingAs($userA)->getJson('/ai/translate/history?favorites=1&q=KIUQ');
        $searchRes->assertOk()->assertJsonCount(1, 'history');

        $delRes = $this->actingAs($userA)->deleteJson("/ai/translate/history/{$historyId}");
        $delRes->assertOk()->assertJson(['success' => true]);

        $this->assertNull(AiTranslation::find($historyId));
    }

    public function test_translation_studio_caches_identical_requests(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->mock(GroqClientService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            // Must only be called once; the second identical request will be served from cache
            $mock->shouldReceive('generateContent')
                ->once()
                ->andReturn([
                    'text' => 'TYPHON STOMP V900 履带式装载机',
                ]);
        });

        $payload = [
            'text'            => 'TYPHON STOMP V900 Track Loader',
            'source_language' => 'en-US',
            'target_language' => 'zh-CN',
            'mode'            => 'standard',
        ];

        // 1st request - hits generator and caches
        $res1 = $this->actingAs($user)->postJson('/ai/translate', $payload);
        $res1->assertOk()
            ->assertJson([
                'success'         => true,
                'translated_text' => 'TYPHON STOMP V900 履带式装载机',
            ]);

        // 2nd identical request - hits cache immediately
        $res2 = $this->actingAs($user)->postJson('/ai/translate', $payload);
        $res2->assertOk()
            ->assertJson([
                'success'         => true,
                'translated_text' => 'TYPHON STOMP V900 履带式装载机',
            ]);
    }
}
