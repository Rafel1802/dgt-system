<?php

namespace App\Http\Controllers\Api;

use App\Enums\CardStatus;
use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Card;
use App\Models\Customer;
use App\Models\EbayOffer;
use App\Models\Lead;
use App\Models\Logistic;
use App\Models\Note;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $myCards = Card::query()
            ->where('is_archived', false)
            ->whereHas('assignees', fn ($query) => $query->where('users.id', $user->id));

        return response()->json([
            'hero' => [
                'title' => 'DGT System',
                'subtitle' => 'Company workspace, CRM and operations synced from Laravel.',
                'user_name' => $user->name,
                'unread_notifications' => $user->unreadNotifications()->count(),
            ],
            'stats' => [
                'active_members' => User::active()->count(),
                'boards' => Board::where('is_archived', false)->count(),
                'open_cards' => Card::where('is_archived', false)->whereNotIn('status', [CardStatus::Done->value, CardStatus::Approved->value])->count(),
                'my_cards' => (clone $myCards)->count(),
                'websites' => Website::where('is_archived', false)->count(),
                'crm_customers' => Customer::count(),
                'logistics' => Logistic::count(),
                'shipments' => Shipment::count(),
                'ebay_offers' => EbayOffer::count(),
                'website_leads' => Lead::active()->count(),
                'notes' => Note::where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)->orWhere('type', 'team');
                })->count(),
            ],
            'my_cards' => (clone $myCards)
                ->with(['board:id,name,slug', 'boardList:id,name', 'assignees:id,name,avatar'])
                ->latest('updated_at')
                ->limit(8)
                ->get(),
            'recent_notifications' => $user->notifications()
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn ($notification) => app(\App\Http\Controllers\Api\NotificationController::class)->format($notification))
                ->values(),
        ]);
    }
}
