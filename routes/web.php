<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\LabelController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\GoogleScriptWebhookController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Kanban\KanbanController;
use App\Http\Controllers\Kanban\CardCommentController;
use App\Http\Controllers\Kanban\CardChecklistController;
use App\Http\Controllers\Kanban\CardFileController;
use App\Http\Controllers\CRM\CustomerController;
use App\Http\Controllers\CRM\CrmDashboardController;
use App\Http\Controllers\CRM\WebsiteCrmController;
use App\Http\Controllers\CRM\EbayCrmController;
use App\Http\Controllers\CRM\EbayStoreController;
use App\Http\Controllers\CRM\TruckingCompanyController;
use App\Http\Controllers\CRM\ShipmentController;
use App\Http\Controllers\CRM\ProductController;
use App\Http\Controllers\CRM\EbayCustomerController;
use App\Http\Controllers\CRM\CrmExternalLinkController;
use App\Http\Controllers\CRM\CrmReportController;
use App\Http\Controllers\CRM\EbayReportController;
use App\Http\Controllers\CRM\TechSupportController;
use App\Http\Controllers\CRM\CrmStaffReportController;
use App\Http\Controllers\Board\BoardController;
use App\Http\Controllers\Board\CardController as BoardCardController;
use App\Http\Controllers\Board\BoardImportController;
use App\Http\Controllers\SocialMedia\SocialMediaClassController;
use App\Http\Controllers\SocialMedia\SocialMediaAnalyticsController;
use App\Http\Controllers\SocialMedia\SocialMediaItemController;
use App\Http\Controllers\SocialMedia\SocialMediaDashboardController;
use App\Http\Controllers\SocialMedia\SocialMediaPostController;
use App\Http\Controllers\SocialMedia\SocialMediaReportController;
use App\Http\Controllers\RouteClosureController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// ── Auth Routes ────────────────────────────────────────────────────────────
// These two utility routes were previously public with no auth check at all
// — /debug-log dumped raw application log contents to anyone, and
// /seed-automations let anyone mutate Board automation data with a GET
// request. Both are now restricted to logged-in super-admins only.
Route::middleware(['auth', 'ensure.active', 'role:super-admin'])->get('/seed-automations', [RouteClosureController::class, 'seedAutomations']);

Route::middleware(['auth', 'ensure.active', 'role:super-admin'])->get('/debug-log', [RouteClosureController::class, 'debugLog']);

Route::post('/export/download-pdf-base64', [RouteClosureController::class, 'downloadPdfBase64'])->name('export.download-pdf-base64')->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
Route::post('/export/save-pdf-temp', [RouteClosureController::class, 'savePdfTemp'])->name('export.save-pdf-temp')->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
Route::middleware(['web', 'check.ip.ban'])->group(function () {

    // Guest-only routes
    Route::middleware('guest')->group(function () {
        Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
    });

    // Public share links — no login required; access is gated by the
    // unguessable token itself, not by auth middleware.
    Route::get('/share/call-reports/{token}', [\App\Http\Controllers\Public\CallReportShareController::class, 'show'])
        ->name('public.call-reports.show');
    Route::get('/share/staff-report/{token}', [\App\Http\Controllers\Public\ReportShareController::class, 'showStaff'])
        ->name('public.staff-report.show');
    Route::get('/share/team-report/{token}', [\App\Http\Controllers\Public\ReportShareController::class, 'showTeam'])
        ->name('public.team-report.show');

    // Authenticated routes
    Route::middleware(['auth', 'ensure.active', 'log.activity'])->group(function () {

        // Logout
        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

        // Dashboard
        Route::get('/fix-followups', function () {
            $count = \App\Models\WebsiteFollowUp::whereDate('created_at', '2026-08-14')->update(['created_at' => '2026-08-15 00:00:00']);
            return "Updated $count";
        });

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::view('/mac-app', 'downloads.mac-app')->name('downloads.mac-app');
        // Internal-only: validates Livewire + Turbo coexist safely. Not linked from any menu. Remove after Livewire rollout is verified stable.
        Route::view('/internal/livewire-test', 'internal.livewire-test')->name('internal.livewire-test');
        Route::get('/mac-app/download', [RouteClosureController::class, 'downloadMacApp'])->name('downloads.mac-app.file');

        // Polymorphic attachments download/delete/view
        Route::get('/attachments/{attachment}/download', [\App\Http\Controllers\AttachmentController::class, 'download'])->name('attachments.download');
        Route::get('/attachments/{attachment}/view', [\App\Http\Controllers\AttachmentController::class, 'view'])->name('attachments.view');
        Route::delete('/attachments/{attachment}', [\App\Http\Controllers\AttachmentController::class, 'destroy'])->name('attachments.destroy');

        // ── All Members Directory (read-only — data from users table) ────────
        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::get('/members/search', [MemberController::class, 'search'])->name('members.search');

        // All Websites (Digital Team)
        // ── All Websites Executive Dashboard ──────────────────────────────────
        Route::middleware(['maintenance:all_websites'])->group(function () {
            Route::get('/websites/dashboard', [\App\Http\Controllers\WebsitesDashboardController::class, 'index'])->name('websites.dashboard');
            Route::get('/websites/dashboard/export', [\App\Http\Controllers\WebsitesDashboardController::class, 'export'])->name('websites.dashboard.export');

            // Category management
            Route::post('/websites/category', [\App\Http\Controllers\WebsiteController::class, 'storeCategory'])->name('websites.storeCategory');
            Route::put('/websites/category/rename', [\App\Http\Controllers\WebsiteController::class, 'renameCategory'])->name('websites.renameCategory');
            Route::put('/websites/category/reorder', [\App\Http\Controllers\WebsiteController::class, 'reorderCategory'])->name('websites.reorderCategory');
            Route::delete('/websites/category', [\App\Http\Controllers\WebsiteController::class, 'destroyCategory'])->name('websites.destroyCategory');

            // New action routes (must be before resource to avoid conflicts)
            Route::get('/websites/export', [\App\Http\Controllers\WebsiteController::class, 'exportReport'])->name('websites.export');
            Route::post('/websites/{website}/progress', [\App\Http\Controllers\WebsiteController::class, 'updateProgress'])->name('websites.progress.update');
            Route::post('/websites/{website}/approve-qc', [\App\Http\Controllers\WebsiteController::class, 'approveQc'])->name('websites.qc.approve');
            Route::post('/websites/{website}/revert-qc', [\App\Http\Controllers\WebsiteController::class, 'revertQc'])->name('websites.qc.revert');
            Route::post('/websites/{website}/approve-supervisor', [\App\Http\Controllers\WebsiteController::class, 'approveSupervisor'])->name('websites.supervisor.approve');
            Route::post('/websites/{website}/qc-error', [\App\Http\Controllers\WebsiteController::class, 'qcError'])->name('websites.qc.error');
            Route::post('/websites/{website}/supervisor-error', [\App\Http\Controllers\WebsiteController::class, 'supervisorError'])->name('websites.supervisor.error');
            Route::post('/websites/{website}/error-progress', [\App\Http\Controllers\WebsiteController::class, 'updateErrorProgress'])->name('websites.error.progress');
            Route::post('/websites/{website}/complete-qc-error', [\App\Http\Controllers\WebsiteController::class, 'completeQcError'])->name('websites.qc.error.complete');
            Route::post('/websites/{website}/complete-supervisor-error', [\App\Http\Controllers\WebsiteController::class, 'completeSupervisorError'])->name('websites.supervisor.error.complete');
            Route::get('/websites/{website}/error-attachment/view', [\App\Http\Controllers\WebsiteController::class, 'viewErrorAttachment'])->name('websites.error-attachment.view');
            Route::get('/websites/{website}/error-attachment/download', [\App\Http\Controllers\WebsiteController::class, 'downloadErrorAttachment'])->name('websites.error-attachment.download');
            Route::post('/websites/members', [\App\Http\Controllers\WebsiteController::class, 'storeMember'])->name('websites.members.store');
            Route::delete('/websites/members/{member}', [\App\Http\Controllers\WebsiteController::class, 'destroyMember'])->name('websites.members.destroy');
            Route::post('/websites/{website}/start-maintenance', [\App\Http\Controllers\WebsiteController::class, 'startMaintenance'])->name('websites.maintenance.start');
            Route::post('/websites/{website}/maintenance-progress', [\App\Http\Controllers\WebsiteController::class, 'updateMaintenanceProgress'])->name('websites.maintenance.update');

            Route::get('/websites/history-logs/{id}/attachment/view', [\App\Http\Controllers\WebsiteController::class, 'viewHistoryAttachment'])->name('websites.history-logs.attachment.view');
            Route::get('/websites/history-logs/{id}/attachment/download', [\App\Http\Controllers\WebsiteController::class, 'downloadHistoryAttachment'])->name('websites.history-logs.attachment.download');
            Route::delete('/websites/history-logs/{id}', [\App\Http\Controllers\WebsiteController::class, 'destroyHistoryLog'])->name('websites.history-logs.destroy');
            Route::put('/websites/history-logs/{id}', [\App\Http\Controllers\WebsiteController::class, 'updateHistoryLog'])->name('websites.history-logs.update');
            Route::post('/websites/history-logs/{id}/attachments', [\App\Http\Controllers\WebsiteController::class, 'addHistoryAttachments'])->name('websites.history-logs.attachments.store');
            Route::delete('/websites/history-logs/{id}/attachments/{fileId}', [\App\Http\Controllers\WebsiteController::class, 'destroyHistoryAttachment'])->name('websites.history-logs.attachments.destroy');
            Route::post('/websites/history-logs/{id}/attachment', [\App\Http\Controllers\WebsiteController::class, 'updateHistoryAttachment'])->name('websites.history-logs.attachment.update');
            Route::delete('/websites/history-logs/{id}/attachment', [\App\Http\Controllers\WebsiteController::class, 'destroyHistoryAttachment'])->name('websites.history-logs.attachment.destroy');
            Route::get('/websites/{website}/ping', [\App\Http\Controllers\WebsiteController::class, 'ping'])->name('websites.ping');
            Route::get('/websites/{website}/history', [\App\Http\Controllers\WebsiteController::class, 'getHistory'])->name('websites.history');
            Route::post('/websites/{website}/history-logs/comment', [\App\Http\Controllers\WebsiteController::class, 'addHistoryComment'])->name('websites.history-logs.comment.store');

            // Website CRUD resource
            Route::resource('websites', \App\Http\Controllers\WebsiteController::class)->except(['create', 'show', 'edit']);

            // Follow Ups
            Route::post('/websites/follow-ups/{websiteFollowUp}/qc', [\App\Http\Controllers\WebsiteFollowUpController::class, 'qcCheck'])->name('websites.followups.qc');
            Route::resource('websites/follow-ups', \App\Http\Controllers\WebsiteFollowUpController::class, [
                'as'        => 'websites',
                'names'     => [
                    'store'   => 'websites.followups.store',
                    'update'  => 'websites.followups.update',
                    'destroy' => 'websites.followups.destroy',
                ],
                'parameters' => ['follow-ups' => 'websiteFollowUp'],
            ])->only(['store', 'update', 'destroy']);
        });

        Route::put('/dashboard/appearance', [DashboardController::class, 'updateAppearance'])->name('dashboard.appearance.update');

        // Notifications: the real /notifications routes live further down,
        // registered against Admin\NotificationController (see "Notifications"
        // section near Profile & Settings) — that registration wins for the
        // actual GET /notifications dispatch since Laravel's route collection
        // keys routes by method+URI, so a second registration of the exact
        // same URI silently overwrites this one. Do not re-add a duplicate
        // registration here; it will be dead code again.

        // ── Workspaces & Trello-style Boards (Phase Board-1) ─────────────────
        Route::prefix('boards')->name('boards.')->middleware('maintenance:boards')->group(function () {
            Route::get('/', [BoardController::class, 'workspaces'])->name('workspaces');
            Route::post('/workspaces', [BoardController::class, 'storeWorkspace'])->name('workspaces.store');
            Route::post('/workspaces/{workspace}/move-up', [BoardController::class, 'moveUpWorkspace'])->name('workspaces.moveUp');
            Route::post('/workspaces/{workspace}/move-down', [BoardController::class, 'moveDownWorkspace'])->name('workspaces.moveDown');
            Route::put('/workspaces/{workspace}', [BoardController::class, 'updateWorkspace'])->name('workspaces.update');
            Route::delete('/workspaces/{workspace}', [BoardController::class, 'destroyWorkspace'])->name('workspaces.destroy');
            Route::post('/workspaces/{id}/restore', [BoardController::class, 'restoreWorkspace'])->name('workspaces.restore');
            Route::delete('/workspaces/{id}/force', [BoardController::class, 'forceDeleteWorkspace'])->name('workspaces.forceDelete');
            Route::post('/workspaces/{workspace}/boards/reorder', [BoardController::class, 'reorderWorkspaceBoards'])->name('workspaces.boards.reorder');
            Route::post('/', [BoardController::class, 'store'])->name('store');
            Route::post('/{board}/basic-update', [BoardController::class, 'updateBoardBasic'])->name('basic-update');
            Route::get('/personal-report', [\App\Http\Controllers\Board\BoardExportController::class, 'personalReport'])->name('reports.personal');
            Route::get('/personal-report/export', [\App\Http\Controllers\Board\BoardExportController::class, 'exportPersonalReport'])->name('reports.personal.export');
            Route::get('/personal-report/social-media/export', [\App\Http\Controllers\SocialMedia\SocialMediaReportController::class, 'exportPersonalReport'])->name('reports.personal.social_media.export');
            Route::get('/personal-report/website/export', [\App\Http\Controllers\WebsiteController::class, 'exportPersonalReport'])->name('reports.personal.website.export');
            Route::get('/personal-report/follow-up/export', [\App\Http\Controllers\WebsiteFollowUpController::class, 'exportPersonalReport'])->name('reports.personal.follow_up.export');
            Route::get('/{board:slug}', [BoardController::class, 'show'])->name('show');
            Route::get('/{board:slug}/snapshot', [BoardController::class, 'snapshot'])->name('snapshot');
            Route::patch('/{board:slug}', [BoardController::class, 'update'])->name('update');
            Route::patch('/{board:slug}/toggle-hidden', [BoardController::class, 'toggleHidden'])->name('toggle-hidden');
            Route::delete('/{board:slug}', [BoardController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/restore', [BoardController::class, 'restore'])->name('restore');
            Route::delete('/{id}/force', [BoardController::class, 'forceDelete'])->name('forceDelete');
            Route::post('/{board:slug}/copy', [BoardController::class, 'copy'])->name('copy');
            Route::get('/{board:slug}/archived', [BoardController::class, 'archivedItems'])->name('archived');
            Route::get('/{board:slug}/trash', [BoardController::class, 'getTrash'])->name('trash');
            Route::post('/{board:slug}/trash/restore', [BoardController::class, 'restoreTrash'])->name('trash.restore');
            Route::delete('/{board:slug}/trash/force', [BoardController::class, 'forceDeleteTrash'])->name('trash.force');
            Route::post('/{board:slug}/watch', [BoardController::class, 'toggleWatch'])->name('watch');
            Route::get('/{board:slug}/export/csv', [\App\Http\Controllers\Board\BoardExportController::class, 'exportCsv'])->name('export.csv');
            Route::get('/{board:slug}/export/pdf', [\App\Http\Controllers\Board\BoardExportController::class, 'exportPdf'])->name('export.pdf');
            Route::post('/{board:slug}/background', [BoardController::class, 'uploadBackground'])->name('background.upload');
            Route::post('/{board:slug}/labels', [BoardController::class, 'createLabel'])->name('labels.create');

            // Workspace member management
            Route::post('/workspaces/{workspace}/members', [BoardController::class, 'addWorkspaceMember'])->name('workspaces.members.add');
            Route::delete('/workspaces/{workspace}/members/{user}', [BoardController::class, 'removeWorkspaceMember'])->name('workspaces.members.remove');

            // List (column) management — AJAX
            Route::post('/{board:slug}/lists', [BoardController::class, 'storeList'])->name('lists.store');
            Route::patch('/lists/{list}', [BoardController::class, 'updateList'])->name('lists.update');
            Route::post('/{board:slug}/lists/reorder', [BoardController::class, 'reorderLists'])->name('lists.reorder');
            Route::delete('/lists/{list}', [BoardController::class, 'destroyList'])->name('lists.destroy');
            Route::delete('/lists/{list}/clear', [BoardController::class, 'clearList'])->name('lists.clear');

            // Card management — AJAX
            Route::post('/{board:slug}/cards', [BoardCardController::class, 'store'])->name('cards.store');
            Route::post('/{board:slug}/cards/reorder', [BoardController::class, 'reorderCards'])->name('cards.reorder');
            Route::get('/cards/{card}', [BoardCardController::class, 'show'])->name('cards.show');
            Route::patch('/cards/{card}', [BoardCardController::class, 'update'])->name('cards.update');
            Route::delete('/cards/{card}', [BoardCardController::class, 'destroy'])->name('cards.destroy');
            Route::post('/cards/{card}/move', [BoardCardController::class, 'move'])->name('cards.move');
            Route::post('/cards/{card}/copy', [BoardCardController::class, 'copy'])->name('cards.copy');
            Route::post('/cards/{card}/block-complete', [BoardCardController::class, 'completeBlock'])->name('cards.block-complete');
            Route::post('/cards/{card}/toggle-approve', [BoardCardController::class, 'toggleApprove'])->name('cards.toggle-approve');
            Route::post('/cards/{card}/members', [BoardCardController::class, 'toggleMember'])->name('cards.members');
            Route::post('/cards/{card}/labels', [BoardCardController::class, 'toggleLabel'])->name('cards.labels');

            // Checklists — Trello Board
            Route::post('/cards/{card}/checklists', [BoardCardController::class, 'storeChecklist'])->name('cards.checklists.store');
            Route::patch('/cards/{card}/checklists/{checklist}', [BoardCardController::class, 'updateChecklist'])->name('cards.checklists.update');
            Route::delete('/cards/{card}/checklists/{checklist}', [BoardCardController::class, 'destroyChecklist'])->name('cards.checklists.destroy');
            Route::post('/cards/{card}/checklists/{checklist}/items', [BoardCardController::class, 'storeChecklistItem'])->name('cards.checklists.items.store');
            Route::patch('/cards/{card}/checklists/{checklist}/items/{item}', [BoardCardController::class, 'toggleChecklistItem'])->name('cards.checklists.items.toggle');
            Route::delete('/cards/{card}/checklists/{checklist}/items/{item}', [BoardCardController::class, 'destroyChecklistItem'])->name('cards.checklists.items.destroy');

            // Comments — Trello Board
            Route::post('/cards/{card}/comments', [BoardCardController::class, 'storeComment'])->name('cards.comments.store');
            Route::patch('/cards/{card}/comments/{comment}', [BoardCardController::class, 'updateComment'])->name('cards.comments.update');
            Route::delete('/cards/{card}/comments/{comment}', [BoardCardController::class, 'destroyComment'])->name('cards.comments.destroy');
            Route::post('/cards/comments/{comment}/react', [\App\Http\Controllers\Board\CommentReactionController::class, 'toggle'])->name('cards.comments.react');

            // File uploads — Trello Board
            Route::post('/cards/{card}/files', [BoardCardController::class, 'uploadFile'])->name('cards.files.store');
            Route::post('/cards/{card}/files/{file}/update', [BoardCardController::class, 'updateFile'])->name('cards.files.update');
            Route::get('/cards/{card}/files/{file}/preview', [BoardCardController::class, 'previewFile'])->name('cards.files.preview');
            Route::get('/cards/{card}/files/{file}/download', [BoardCardController::class, 'downloadFile'])->name('cards.files.download');
            Route::delete('/cards/{card}/files/{file}', [BoardCardController::class, 'deleteFile'])->name('cards.files.destroy');

            // Board Members Management
            Route::post('/{board:slug}/members', [BoardController::class, 'addMember'])->name('members.add');
            Route::delete('/{board:slug}/members/{user}', [BoardController::class, 'removeMember'])->name('members.remove');

            // Board Activities Feed
            Route::get('/{board:slug}/activities', [BoardController::class, 'activities'])->name('activities');

            // Member picker search (JSON)
            Route::get('/{board:slug}/members/search', [BoardController::class, 'searchMembers'])->name('members.search');
            // Board Automations
            Route::get('/{board:slug}/automations', [\App\Http\Controllers\Board\BoardAutomationController::class, 'index'])->name('automations.index');
            Route::post('/{board:slug}/automations', [\App\Http\Controllers\Board\BoardAutomationController::class, 'store'])->name('automations.store');
            Route::put('/{board:slug}/automations/{automation}', [\App\Http\Controllers\Board\BoardAutomationController::class, 'update'])->name('automations.update');
            Route::delete('/{board:slug}/automations/{automation}', [\App\Http\Controllers\Board\BoardAutomationController::class, 'destroy'])->name('automations.destroy');

            // Board Import
            Route::get('/{board:slug}/import/template', [BoardImportController::class, 'template'])->name('import.template');
            Route::post('/{board:slug}/import/preview',  [BoardImportController::class, 'preview'])->name('import.preview');
            Route::post('/{board:slug}/import/confirm',  [BoardImportController::class, 'confirm'])->name('import.confirm');
        });

        // ── Kanban Board ─────────────────────────────────────────────────
        Route::middleware(['role:super-admin|admin-digital|digital-team|boss', 'maintenance:boards'])
            ->prefix('kanban')
            ->name('kanban.')
            ->group(function () {

                // Board view
                Route::get('/', [KanbanController::class, 'index'])->name('index');

                // Card CRUD (JSON API)
                Route::post('/cards', [KanbanController::class, 'store'])->name('cards.store');
                Route::get('/cards/{card}', [KanbanController::class, 'show'])->name('cards.show');
                Route::put('/cards/{card}', [KanbanController::class, 'update'])->name('cards.update');
                Route::delete('/cards/{card}', [KanbanController::class, 'destroy'])->name('cards.destroy');

                // Drag-drop & reorder
                Route::patch('/cards/{card}/move', [KanbanController::class, 'move'])->name('cards.move');
                Route::post('/cards/reorder', [KanbanController::class, 'reorder'])->name('cards.reorder');

                // Approval workflow
                Route::post('/cards/{card}/approve', [KanbanController::class, 'approve'])->name('cards.approve');
                Route::post('/cards/{card}/toggle-approve', [KanbanController::class, 'toggleApprove'])->name('cards.toggle-approve');
                Route::post('/cards/{card}/reject', [KanbanController::class, 'reject'])->name('cards.reject');

                // Sub-labels (dynamic form helper)
                Route::get('/sub-labels', [KanbanController::class, 'subLabels'])->name('sub-labels');

                // Comments
                Route::post('/cards/{card}/comments', [CardCommentController::class, 'store'])->name('comments.store');
                Route::delete('/cards/{card}/comments/{comment}', [CardCommentController::class, 'destroy'])->name('comments.destroy');

                // Checklists
                Route::post('/cards/{card}/checklists', [CardChecklistController::class, 'store'])->name('checklists.store');
                Route::delete('/cards/{card}/checklists/{checklist}', [CardChecklistController::class, 'destroy'])->name('checklists.destroy');
                Route::post('/cards/{card}/checklists/{checklist}/items', [CardChecklistController::class, 'storeItem'])->name('checklists.items.store');
                Route::patch('/cards/{card}/checklists/{checklist}/items/{item}/toggle', [CardChecklistController::class, 'toggleItem'])->name('checklists.items.toggle');
                Route::delete('/cards/{card}/checklists/{checklist}/items/{item}', [CardChecklistController::class, 'destroyItem'])->name('checklists.items.destroy');

                // Files
                Route::post('/cards/{card}/files', [CardFileController::class, 'store'])->name('files.store');
                Route::get('/files/{file}/download', [CardFileController::class, 'download'])->name('files.download');
                Route::delete('/cards/{card}/files/{file}', [CardFileController::class, 'destroy'])->name('files.destroy');
            });

        // ── SMM Planning Boards ───────────────────────────────────────────
        Route::prefix('smm-boards')
            ->name('smm-boards.')
            ->middleware(['auth', 'ensure.active', 'role:super-admin|admin-digital|social_admin|social_qc|boss|digital-team', 'maintenance:social_media'])
            ->group(function () {
                Route::get('/', [\App\Http\Controllers\SocialMedia\SmmPlanningBoardController::class, 'index'])->name('index');
                Route::post('/', [\App\Http\Controllers\SocialMedia\SmmPlanningBoardController::class, 'store'])->name('store');
                Route::post('/{board}/duplicate', [\App\Http\Controllers\SocialMedia\SmmPlanningBoardController::class, 'duplicate'])->name('duplicate');
                Route::patch('/{board}/toggle-active', [\App\Http\Controllers\SocialMedia\SmmPlanningBoardController::class, 'toggleActive'])->name('toggle-active');
                Route::patch('/{board}/toggle-hidden', [\App\Http\Controllers\SocialMedia\SmmPlanningBoardController::class, 'toggleHidden'])->name('toggle-hidden');
                Route::delete('/{board}', [\App\Http\Controllers\SocialMedia\SmmPlanningBoardController::class, 'destroy'])->name('destroy');
                
                // Import
                Route::get('/{board:slug}/import/template', [\App\Http\Controllers\SocialMedia\SmmImportController::class, 'template'])->name('import.template');
                Route::post('/{board:slug}/import/preview', [\App\Http\Controllers\SocialMedia\SmmImportController::class, 'preview'])->name('import.preview');
                Route::post('/{board:slug}/import/confirm', [\App\Http\Controllers\SocialMedia\SmmImportController::class, 'confirm'])->name('import.confirm');
                
                // Export
                Route::get('/{board:slug}/export/csv', [\App\Http\Controllers\Board\BoardExportController::class, 'exportCsv'])->name('export.csv');
                Route::get('/{board:slug}/export/pdf', [\App\Http\Controllers\Board\BoardExportController::class, 'exportPdf'])->name('export.pdf');
            });

        // ── Settings ────────────────────────────────────────────────────────
        Route::middleware(['role:super-admin|admin-digital'])->prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [SettingController::class, 'index'])->name('index');
            Route::post('/', [SettingController::class, 'store'])->name('store');
        });
        Route::prefix('notes')->name('notes.')->middleware('maintenance:notes')->group(function () {
            // Views
            Route::get('/private', [\App\Http\Controllers\Note\NoteController::class, 'privateIndex'])->name('private');
            Route::get('/team', [\App\Http\Controllers\Note\NoteController::class, 'teamIndex'])->name('team');
            
            // API
            Route::get('/api/fetch', [\App\Http\Controllers\Note\NoteController::class, 'fetchNotes'])->name('api.fetch');
            Route::post('/api/store', [\App\Http\Controllers\Note\NoteController::class, 'store'])->name('api.store');
            Route::post('/api/folders', [\App\Http\Controllers\Note\NoteController::class, 'storeFolder'])->name('api.folder.store');
            Route::put('/api/folders/{folder}', [\App\Http\Controllers\Note\NoteController::class, 'updateFolder'])->name('api.folder.update');
            Route::delete('/api/folders/{folder}', [\App\Http\Controllers\Note\NoteController::class, 'destroyFolder'])->name('api.folder.destroy');
            Route::get('/api/folders/{folder}/download', [\App\Http\Controllers\Note\NoteController::class, 'downloadFolder'])->name('api.folder.download');
            Route::post('/api/download', [\App\Http\Controllers\Note\NoteController::class, 'downloadSelected'])->name('api.download');
            Route::post('/api/bulk-delete', [\App\Http\Controllers\Note\NoteController::class, 'bulkDestroy'])->name('api.bulk-destroy');
            Route::put('/api/{noteId}/restore', [\App\Http\Controllers\Note\NoteController::class, 'restore'])->name('api.restore');
            Route::delete('/api/{noteId}/force', [\App\Http\Controllers\Note\NoteController::class, 'forceDestroy'])->name('api.force-destroy');
            Route::put('/api/{note}', [\App\Http\Controllers\Note\NoteController::class, 'update'])->name('api.update');
            Route::delete('/api/{note}', [\App\Http\Controllers\Note\NoteController::class, 'destroy'])->name('api.destroy');
        });

        // ── Admin: User Management ────────────────────────────────────────────
        Route::middleware(['role:super-admin|admin-digital|admin-crm'])->prefix('admin')->name('admin.')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
            Route::patch('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
            Route::post('/users/bulk-action', [UserController::class, 'bulkAction'])->name('users.bulk-action');
            Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::post('labels/reorder', [LabelController::class, 'reorder'])->name('labels.reorder');
            Route::patch('popup-ads/{popupAd}/toggle-active', [\App\Http\Controllers\Admin\PopupAdController::class, 'toggleActive'])->name('popup-ads.toggle-active');
            Route::resource('popup-ads', \App\Http\Controllers\Admin\PopupAdController::class);
            Route::resource('labels', LabelController::class)->except(['create', 'show', 'edit']);
            Route::post('smm-classes/reorder', [\App\Http\Controllers\Admin\SmmClassController::class, 'reorder'])->name('smm-classes.reorder');
            Route::resource('smm-classes', \App\Http\Controllers\Admin\SmmClassController::class)->except(['create', 'show', 'edit']);

            // ── System Settings ───────────────────────────────────────────
            Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
            Route::post('/settings', [SettingController::class, 'store'])->name('settings.store');
            
            // ── Maintenance System ────────────────────────────────────────
            Route::get('/maintenance', [\App\Http\Controllers\Admin\MaintenanceController::class, 'index'])->name('maintenance.index');
            Route::post('/maintenance', [\App\Http\Controllers\Admin\MaintenanceController::class, 'store'])->name('maintenance.store');

            // ── Security Management ───────────────────────────────────────
            Route::get('/security', [SecurityController::class, 'index'])->name('security.index');
            Route::post('/security/settings', [SecurityController::class, 'storeSettings'])->name('security.settings');
            Route::post('/security/ban-ip', [SecurityController::class, 'banIp'])->name('security.ban-ip');
            Route::delete('/security/unban-ip/{ipBan}', [SecurityController::class, 'unbanIp'])->name('security.unban-ip');
            Route::post('/security/users/{user}/unblock', [SecurityController::class, 'unblockUser'])->name('security.unblock-user');
            Route::delete('/security/activity/clear', [SecurityController::class, 'clearActivity'])->name('security.activity.clear');
        });

        // ── Supervisor Approval Panel ─────────────────────────────────────
        Route::middleware(['role:super-admin|admin-digital|admin-crm|boss', 'maintenance:approvals'])
            ->prefix('approvals')
            ->name('approvals.')
            ->group(function () {
                Route::get('/', [ApprovalController::class, 'index'])->name('index');
                Route::post('/custom-range', [ApprovalController::class, 'customRange'])->name('custom-range');
            });



        // ── Reports — Phase 5 ─────────────────────────────────────────────
        Route::middleware(['role:super-admin|admin-digital|admin-crm|sales-crm|boss'])
            ->prefix('reports')
            ->name('reports.')
            ->group(function () {
                Route::get('/', [ReportController::class, 'index'])->name('index');
            });

        // ── Popup Ads (Frontend API) ──────────────────────────────────────
        Route::get('/api/popup-ads/check', [\App\Http\Controllers\Api\PopupAdInteractionController::class, 'check'])->name('popup-ads.check');
        Route::post('/api/popup-ads/mark-shown', [\App\Http\Controllers\Api\PopupAdInteractionController::class, 'markShown'])->name('popup-ads.mark-shown');
        Route::post('/api/popup-ads/mark-clicked', [\App\Http\Controllers\Api\PopupAdInteractionController::class, 'markClicked'])->name('popup-ads.mark-clicked');

        // ── Profile & Settings ────────────────────────────────────────────
        Route::get('/profile', [App\Http\Controllers\Auth\ProfileController::class, 'show'])->name('profile.show');
        Route::put('/profile', [App\Http\Controllers\Auth\ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/sound', [App\Http\Controllers\Auth\ProfileController::class, 'uploadSound'])->name('profile.sound.upload');
        Route::delete('/profile/sound/{sound}', [App\Http\Controllers\Auth\ProfileController::class, 'deleteSound'])->name('profile.sound.delete');
        Route::get('/settings', [App\Http\Controllers\Auth\ProfileController::class, 'settings'])->name('settings');
        Route::put('/settings/password', [App\Http\Controllers\Auth\ProfileController::class, 'updatePassword'])->name('settings.password');

        // ── Notifications ─────────────────────────────────────────────────
        Route::get('/notifications', [App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{id}/read', [App\Http\Controllers\Admin\NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [App\Http\Controllers\Admin\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::post('/notifications/clear-crm', [App\Http\Controllers\Admin\NotificationController::class, 'clearCrm'])->name('notifications.clear-crm');

        // ── Social Media Team ─────────────────────────────────────────────
        Route::prefix('social-media')
            ->name('social-media.')
            ->middleware(['auth', 'ensure.active', 'maintenance:social_media']) // Add ensure.active if needed or just use standard middleware
            ->group(function () {

                // Dashboard & Class Table (read-only views) — accessible to SM roles and digital-team (view-only)
                Route::middleware('role:super-admin|admin-digital|social_admin|social_qc|boss|digital-team')
                    ->group(function () {
                        Route::get('/', [SocialMediaDashboardController::class, 'index'])->name('dashboard');
                        Route::get('/class/{class}', [SocialMediaPostController::class, 'show'])->name('class.show');
                    });

                // Post updates and Reports (controls/management) — SM roles only
                Route::middleware('role:super-admin|admin-digital|social_admin|social_qc|boss')
                    ->group(function () {
                        // AJAX post actions
                        Route::post('/posts/upsert', [SocialMediaPostController::class, 'storeOrUpdate'])->name('posts.upsert');
                        Route::patch('/posts/{post}/complete', [SocialMediaPostController::class, 'markCompleted'])->name('posts.complete');

                        // Reports
                        Route::middleware('role:super-admin|admin-digital|supervisor|social_qc')->group(function () {
                            Route::get('/reports', [SocialMediaReportController::class, 'index'])->name('reports.index');
                            // Route::get('/reports/export/csv', [SocialMediaReportController::class, 'exportCsv'])->name('reports.export.csv');
                            // Route::get('/reports/export/pdf', [SocialMediaReportController::class, 'exportPdf'])->name('reports.export.pdf');
                            Route::post('/reports/export/zip', [SocialMediaReportController::class, 'exportZip'])->name('reports.export.zip');
                        });
                        
                        // Analytics View/Download/Store
                        Route::middleware('role:super-admin|admin-digital|social_admin|social_qc|boss|digital-team')->group(function () {
                            Route::get('/analytics', [SocialMediaAnalyticsController::class, 'index'])->name('analytics.index');
                            Route::get('/analytics/{analytic}/download', [SocialMediaAnalyticsController::class, 'download'])->name('analytics.download');
                            Route::get('/analytics/{analytic}/preview', [SocialMediaAnalyticsController::class, 'preview'])->name('analytics.preview');
                            Route::post('/analytics', [SocialMediaAnalyticsController::class, 'store'])->name('analytics.store');
                            Route::delete('/analytics/{analytic}', [SocialMediaAnalyticsController::class, 'destroy'])->name('analytics.destroy');
                        });
                    });

                // Admin/QC actions (excluding Boss)
                Route::middleware('role:super-admin|admin-digital|social_qc')->group(function () {
                    Route::patch('/posts/{post}/check', [SocialMediaPostController::class, 'markChecked'])->name('posts.check');
                    Route::patch('/posts/{post}/unlock', [SocialMediaPostController::class, 'unlock'])->name('posts.unlock');
                });

                // Quick create SMM Class from Board
                Route::post('/classes/quick-create', [\App\Http\Controllers\SocialMedia\SocialMediaClassController::class, 'quickStore'])->name('classes.quick-store');

                // Class & Item Management — platform admins and Social QC only
                Route::middleware('role:super-admin|admin-digital|social_qc')->group(function () {
                    // Class
                    Route::get('/manage', [SocialMediaClassController::class, 'index'])->name('manage');
                    Route::post('/classes', [SocialMediaClassController::class, 'store'])->name('classes.store');
                    Route::put('/classes/{class}', [SocialMediaClassController::class, 'update'])->name('classes.update');
                    Route::delete('/classes/{class}', [SocialMediaClassController::class, 'destroy'])->name('classes.destroy');
                    Route::patch('/classes/{class}/toggle', [SocialMediaClassController::class, 'toggleStatus'])->name('classes.toggle');
                    
                    // User Assignments
                    Route::post('/classes/{class}/assign', [SocialMediaClassController::class, 'assignUsers'])->name('classes.assign');
                    Route::delete('/classes/{class}/users/{user}', [SocialMediaClassController::class, 'removeUser'])->name('classes.remove-user');
                    Route::post('/users/roles/bulk', [SocialMediaClassController::class, 'updateBulkUserRoles'])->name('users.roles.bulk');

                    // Items
                    Route::post('/classes/{class}/items/template', [SocialMediaItemController::class, 'storeTemplate'])->name('items.store-template');
                    Route::post('/classes/{class}/items', [SocialMediaItemController::class, 'store'])->name('items.store');
                    Route::put('/items/{item}', [SocialMediaItemController::class, 'update'])->name('items.update');
                    Route::delete('/items/{item}', [SocialMediaItemController::class, 'destroy'])->name('items.destroy');
                    Route::patch('/items/{item}/toggle', [SocialMediaItemController::class, 'toggleStatus'])->name('items.toggle');
                    Route::post('/items/reorder', [SocialMediaItemController::class, 'reorder'])->name('items.reorder');
                });
            });

    });
});

// Blog Reports
Route::middleware(['auth'])->group(function () {
    Route::get('/blog-reports', [\App\Http\Controllers\BlogReportController::class, 'index'])->name('blog-reports.index');
    Route::get('/blog-reports/preview', function () {
        return redirect()->route('blog-reports.index');
    });
    Route::post('/blog-reports/preview', [\App\Http\Controllers\BlogReportController::class, 'preview'])->name('blog-reports.preview');
    Route::post('/blog-reports/export', [\App\Http\Controllers\BlogReportController::class, 'export'])->name('blog-reports.export');
    Route::post('/blog-reports/csv', [\App\Http\Controllers\BlogReportController::class, 'csv'])->name('blog-reports.csv');
});

// Root redirect
Route::get('/', [RouteClosureController::class, 'index'])->name('home');

// ── Public Webhook: Google Apps Script Email Push ─────────────────────────
// No auth needed — secured by secret key validated inside the controller
Route::post('/webhook/google-script', [GoogleScriptWebhookController::class, 'receive'])
    ->name('webhook.google-script')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/webhook/google-script/sync-trash', [GoogleScriptWebhookController::class, 'syncTrash'])
    ->name('webhook.google-script.sync-trash')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// ── Fallback route for storage files on shared hosting (symlink alternative) ──
Route::get('storage/{path}', function (string $path) {
    $fullPath = storage_path('app/public/' . $path);
    if (! file_exists($fullPath)) {
        abort(404);
    }
    return response()->file($fullPath);
})->where('path', '.*');
