<?php

use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BoardController;
use App\Http\Controllers\Api\CardController;
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

// ─── Versioned Website Connector API (v1) ───────────────────────────────────
Route::prefix('v1/connector')
    ->middleware(['check.ip.ban', 'auth.connector', 'throttle:60,1'])
    ->group(function () {
        Route::get('/handshake', [\App\Http\Controllers\Api\V1\WebsiteConnectorController::class, 'handshake'])->name('api.v1.connector.handshake');
        Route::post('/verify', [\App\Http\Controllers\Api\V1\WebsiteConnectorController::class, 'verify'])->name('api.v1.connector.verify');
        Route::post('/ping', [\App\Http\Controllers\Api\V1\WebsiteConnectorController::class, 'ping'])->name('api.v1.connector.ping');
        Route::post('/sync/posts', [\App\Http\Controllers\Api\V1\WebsiteConnectorController::class, 'syncPosts'])->name('api.v1.connector.sync.posts');
    });

// ─── Universal Direct View Tracker (v1) ─────────────────────────────────────
Route::prefix('v1')
    ->middleware(['throttle:120,1'])
    ->group(function () {
        Route::post('/track', [\App\Http\Controllers\Api\V1\UniversalTrackController::class, 'track'])->name('api.v1.track');
        Route::get('/track.gif', [\App\Http\Controllers\Api\V1\UniversalTrackController::class, 'pixel'])->name('api.v1.track.pixel');
    });


