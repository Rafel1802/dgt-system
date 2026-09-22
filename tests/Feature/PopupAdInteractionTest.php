<?php

namespace Tests\Feature;

use App\Models\PopupAd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PopupAdInteractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_model_has_popup_ads_relationship(): void
    {
        $user = User::factory()->create();
        $ad = PopupAd::create([
            'title' => 'Test Announcement',
            'is_active' => true,
        ]);

        $this->assertTrue(method_exists($user, 'popupAds'));

        $user->popupAds()->attach($ad->id, [
            'last_shown_at' => now(),
            'is_clicked' => false,
        ]);

        $this->assertCount(1, $user->popupAds);
        $this->assertEquals($ad->id, $user->popupAds->first()->id);
    }

    public function test_popup_ad_check_returns_active_ad(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ad = PopupAd::create([
            'title' => 'Welcome Promo',
            'is_active' => true,
            'interval_minutes' => 10,
        ]);

        $response = $this->getJson(route('popup-ads.check'));
        $response->assertSuccessful();
        $response->assertJsonPath('ad.id', $ad->id);
        $response->assertJsonPath('ad.title', 'Welcome Promo');
    }

    public function test_mark_shown_updates_interaction_record(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ad = PopupAd::create([
            'title' => 'Notice',
            'is_active' => true,
        ]);

        $response = $this->postJson(route('popup-ads.mark-shown'), [
            'ad_id' => $ad->id,
        ]);

        $response->assertSuccessful();
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('popup_ad_user', [
            'user_id' => $user->id,
            'popup_ad_id' => $ad->id,
        ]);
    }

    public function test_mark_clicked_updates_interaction_record(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ad = PopupAd::create([
            'title' => 'Sale Announcement',
            'is_active' => true,
        ]);

        $response = $this->postJson(route('popup-ads.mark-clicked'), [
            'ad_id' => $ad->id,
        ]);

        $response->assertSuccessful();
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('popup_ad_user', [
            'user_id' => $user->id,
            'popup_ad_id' => $ad->id,
            'is_clicked' => 1,
        ]);
    }
}
