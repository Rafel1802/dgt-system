<?php

use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BoardController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\CrmController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SocialMediaController;
use App\Http\Controllers\Api\WebsiteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['check.ip.ban'])->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/dashboard', DashboardController::class);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread', [NotificationController::class, 'unread']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

        Route::apiResource('boards', BoardController::class)->names('api.boards');
        Route::apiResource('cards', CardController::class)->except(['show'])->names('api.cards');

        Route::get('/crm/customers', [CrmController::class, 'customers']);
        Route::get('/crm/logistics', [CrmController::class, 'logistics']);
        Route::post('/crm/logistics', [CrmController::class, 'storeLogistic']);
        Route::put('/crm/logistics/{logistic}', [CrmController::class, 'updateLogistic']);
        Route::delete('/crm/logistics/{logistic}', [CrmController::class, 'deleteLogistic']);
        Route::get('/crm/shipments', [CrmController::class, 'shipments']);
        Route::get('/crm/ebay/stores', [CrmController::class, 'ebayStores']);
        Route::get('/crm/ebay/offers', [CrmController::class, 'ebayOffers']);
        Route::get('/crm/ebay/customers', [CrmController::class, 'ebayCustomerRecords']);
        Route::get('/crm/website/leads', [CrmController::class, 'websiteLeads']);

        Route::get('/notes/team', [NoteController::class, 'index'])->defaults('type', 'team');
        Route::get('/notes/private', [NoteController::class, 'index'])->defaults('type', 'private');
        Route::get('/notes', [NoteController::class, 'index']);
        Route::post('/notes', [NoteController::class, 'store']);
        Route::put('/notes/{note}', [NoteController::class, 'update']);
        Route::delete('/notes/{note}', [NoteController::class, 'destroy']);

        Route::get('/approvals', [ApprovalController::class, 'index']);
        Route::get('/websites', [WebsiteController::class, 'index']);
        Route::get('/websites/statuses', [WebsiteController::class, 'statuses']);
        Route::get('/websites/follow-ups', [WebsiteController::class, 'followUps']);
        Route::get('/websites/weekly-report', [WebsiteController::class, 'weeklyReport']);

        Route::get('/members', [MemberController::class, 'index']);
        Route::get('/members/search', [MemberController::class, 'search']);
        Route::get('/social-media', [SocialMediaController::class, 'dashboard']);
    });
});
