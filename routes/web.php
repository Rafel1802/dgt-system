<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\LabelController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\EmailController;
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
use App\Http\Controllers\CRM\LogisticCrmController;
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
Route::middleware(['auth', 'ensure.active', 'role:super-admin'])->get('/seed-automations', function () {
    $boards = \App\Models\Board::all();
    $count = 0;
    foreach ($boards as $board) {
        $name = strtolower($board->name);
        $isWorkflow = str_contains($name, 'workflow');
        $isPlanning = str_contains($name, 'planning');
        
        if ($isWorkflow) {
            \App\Models\BoardAutomation::where('board_id', $board->id)
                ->where('trigger_board_id', $board->id)
                ->where('target_board_id', $board->id)
                ->delete();

            $lists = $board->lists()->get()->keyBy('name');
            $rules = [
                ['Draft', 'Team approved', 'Head Review', 'Standard Member'],
                ['Head Review', 'Head approved', 'Text (QC) Review (Mr. Dara)', 'Standard Member'],
                ['Text (QC) Review (Mr. Dara)', 'QC approved', 'Supervisor Review (Ms. Somalika)', 'QC'],
                ['Text (QC) Review (Mr. Dara)', 'Error', 'Draft', 'QC'],
                ['Supervisor Review (Ms. Somalika)', 'Approved', 'Approved', 'Supervisor'],
                ['Supervisor Review (Ms. Somalika)', 'Rejected', 'Block/Waiting', 'Supervisor'],
            ];
            foreach ($rules as $rule) {
                if (isset($lists[$rule[0]]) && isset($lists[$rule[2]])) {
                    \App\Models\BoardAutomation::create([
                        'board_id' => $board->id,
                        'trigger_type' => 'keyword',
                        'trigger_word' => $rule[1],
                        'trigger_board_id' => $board->id,
                        'trigger_list_id' => $lists[$rule[0]]->id,
                        'target_board_id' => $board->id,
                        'target_list_id' => $lists[$rule[2]]->id,
                        'action_type' => 'move',
                        'target_assignee_role' => $rule[3]
                    ]);
                    $count++;
                }
            }
        } elseif ($isPlanning) {
            $suffix = trim(str_ireplace('Planning board', '', $board->name));
            $workflowName = trim("Workflow board " . $suffix);
            $workflowBoard = \App\Models\Board::where('workspace_id', $board->workspace_id)
                ->where('name', $workflowName)->first() 
                ?? \App\Models\Board::where('workspace_id', $board->workspace_id)->where('name', 'like', '%Workflow board%')->latest()->first();

            if ($workflowBoard) {
                $draftList = $workflowBoard->lists()->where('name', 'like', '%Draft%')->first();
                if ($draftList) {
                    \App\Models\BoardAutomation::firstOrCreate([
                        'board_id' => $board->id,
                        'trigger_type' => 'keyword',
                        'trigger_word' => 'ready',
                        'trigger_board_id' => $board->id,
                        'trigger_list_id' => null,
                        'target_board_id' => $workflowBoard->id,
                        'target_list_id' => $draftList->id,
                        'action_type' => 'copy'
                    ]);
                    $count++;
                }
            }
        }
    }
    return "Seeded $count automations for existing boards!";
});

Route::middleware(['auth', 'ensure.active', 'role:super-admin'])->get('/debug-log', function() {
    $logPath = storage_path('logs/laravel.log');
    if (!file_exists($logPath)) return "No log file.";
    $lines = array_slice(file($logPath), -200);
    return "<pre>" . implode("", $lines) . "</pre>";
});

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

    // Authenticated routes
    Route::middleware(['auth', 'ensure.active', 'log.activity'])->group(function () {

        // Logout
        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::view('/mac-app', 'downloads.mac-app')->name('downloads.mac-app');
        Route::get('/mac-app/download', function () {
            $version = '1.0.1';

            return redirect(asset("downloads/KIUQ-SYSTEM-{$version}.dmg"));
        })->name('downloads.mac-app.file');

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
            Route::post('/websites/{website}/approve-supervisor', [\App\Http\Controllers\WebsiteController::class, 'approveSupervisor'])->name('websites.supervisor.approve');
            Route::post('/websites/{website}/qc-error', [\App\Http\Controllers\WebsiteController::class, 'qcError'])->name('websites.qc.error');
            Route::post('/websites/{website}/supervisor-error', [\App\Http\Controllers\WebsiteController::class, 'supervisorError'])->name('websites.supervisor.error');
            Route::post('/websites/{website}/error-progress', [\App\Http\Controllers\WebsiteController::class, 'updateErrorProgress'])->name('websites.error.progress');
            Route::post('/websites/{website}/complete-qc-error', [\App\Http\Controllers\WebsiteController::class, 'completeQcError'])->name('websites.qc.error.complete');
            Route::post('/websites/{website}/complete-supervisor-error', [\App\Http\Controllers\WebsiteController::class, 'completeSupervisorError'])->name('websites.supervisor.error.complete');
            Route::post('/websites/members', [\App\Http\Controllers\WebsiteController::class, 'storeMember'])->name('websites.members.store');
            Route::delete('/websites/members/{member}', [\App\Http\Controllers\WebsiteController::class, 'destroyMember'])->name('websites.members.destroy');
            Route::post('/websites/{website}/start-maintenance', [\App\Http\Controllers\WebsiteController::class, 'startMaintenance'])->name('websites.maintenance.start');
            Route::post('/websites/{website}/maintenance-progress', [\App\Http\Controllers\WebsiteController::class, 'updateMaintenanceProgress'])->name('websites.maintenance.update');

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

        // Notifications
        Route::get('/notifications', [\App\Http\Controllers\Board\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{id}/read', [\App\Http\Controllers\Board\NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [\App\Http\Controllers\Board\NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');

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
            Route::get('/{board:slug}', [BoardController::class, 'show'])->name('show');
            Route::get('/{board:slug}/snapshot', [BoardController::class, 'snapshot'])->name('snapshot');
            Route::patch('/{board:slug}', [BoardController::class, 'update'])->name('update');
            Route::patch('/{board:slug}/toggle-hidden', [BoardController::class, 'toggleHidden'])->name('toggle-hidden');
            Route::delete('/{board:slug}', [BoardController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/restore', [BoardController::class, 'restore'])->name('restore');
            Route::delete('/{id}/force', [BoardController::class, 'forceDelete'])->name('forceDelete');
            Route::post('/{board:slug}/copy', [BoardController::class, 'copy'])->name('copy');
            Route::get('/{board:slug}/archived', [BoardController::class, 'archivedItems'])->name('archived');
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

            // Card management — AJAX
            Route::post('/{board:slug}/cards', [BoardCardController::class, 'store'])->name('cards.store');
            Route::post('/{board:slug}/cards/reorder', [BoardController::class, 'reorderCards'])->name('cards.reorder');
            Route::get('/cards/{card}', [BoardCardController::class, 'show'])->name('cards.show');
            Route::patch('/cards/{card}', [BoardCardController::class, 'update'])->name('cards.update');
            Route::delete('/cards/{card}', [BoardCardController::class, 'destroy'])->name('cards.destroy');
            Route::post('/cards/{card}/move', [BoardCardController::class, 'move'])->name('cards.move');
            Route::post('/cards/{card}/copy', [BoardCardController::class, 'copy'])->name('cards.copy');
            Route::post('/cards/{card}/block-complete', [BoardCardController::class, 'completeBlock'])->name('cards.block-complete');
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
            Route::resource('labels', LabelController::class)->except(['create', 'show', 'edit']);

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

        // ── All Mails (Accessible to CRM roles) ───────────────────────────
        Route::middleware(['role:super-admin|admin-crm|sales-crm|boss'])->prefix('admin')->name('admin.')->group(function () {
            Route::get('/emails', [EmailController::class, 'index'])->name('emails.index');
            Route::get('/emails/accounts', [EmailController::class, 'accounts'])->name('emails.accounts');
            Route::post('/emails/accounts', [EmailController::class, 'storeAccount'])->name('emails.accounts.store');
            Route::delete('/emails/accounts/{account}', [EmailController::class, 'destroyAccount'])->name('emails.accounts.destroy');
            Route::get('/emails/{email}', [EmailController::class, 'show'])->name('emails.show');
            Route::patch('/emails/{email}/toggle-read', [EmailController::class, 'toggleRead'])->name('emails.toggle-read');
            Route::patch('/emails/{email}/toggle-star', [EmailController::class, 'toggleStar'])->name('emails.toggle-star');
            Route::delete('/emails/bulk', [EmailController::class, 'bulkDestroy'])->name('emails.bulk-destroy');
            Route::delete('/emails/{email}', [EmailController::class, 'destroy'])->name('emails.destroy');
        });

        // ── Supervisor Approval Panel ─────────────────────────────────────
        Route::middleware(['role:super-admin|admin-digital|admin-crm|boss', 'maintenance:approvals'])
            ->prefix('approvals')
            ->name('approvals.')
            ->group(function () {
                Route::get('/', [ApprovalController::class, 'index'])->name('index');
                Route::post('/custom-range', [ApprovalController::class, 'customRange'])->name('custom-range');
            });

        // ── CRM — Phase 4 ─────────────────────────────────────────────────
        Route::middleware(['role:super-admin|admin-crm|sales-crm|boss|tech-support|ebay-supervisor|logistic-supervisor', 'maintenance:crm'])
            ->prefix('crm')
            ->name('crm.')
            ->group(function () {
                // Customer database
                Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
                Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
                Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
                Route::post('/customers/quick-create', [CustomerController::class, 'quickCreate'])->name('customers.quick-create');
                Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
                Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
                Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
                Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

                // Interaction & purchase endpoints (AJAX)
                Route::post('/customers/{customer}/interactions', [CustomerController::class, 'logInteraction'])->name('customers.interactions');
                Route::post('/customers/{customer}/purchase', [CustomerController::class, 'recordPurchase'])->name('customers.purchase');
                Route::patch('/customers/{customer}/stage', [CustomerController::class, 'updateStage'])->name('customers.stage');
                Route::post('/customers/{customer}/attachments', [CustomerController::class, 'uploadAttachment'])->name('customers.attachments.upload');

                // ── CRM Dashboard (3-panel) ───────────────────────────────
                Route::get('/dashboard', [CrmDashboardController::class, 'index'])->name('dashboard');
                Route::get('/export/{type}', [CrmReportController::class, 'export'])->name('export');

                // ── Website CRM ───────────────────────────────────────────
                Route::prefix('website')->name('website.')->group(function () {
                    Route::get('/', [WebsiteCrmController::class, 'index'])->name('index');
                    Route::get('/create', [WebsiteCrmController::class, 'create'])->name('create');
                    Route::post('/', [WebsiteCrmController::class, 'store'])->name('store');
                    Route::get('/call-reports', [WebsiteCrmController::class, 'callReportsIndex'])->name('call-reports.index');
                    Route::post('/call-reports', [WebsiteCrmController::class, 'storeCallReport'])->name('call-reports.store');
                    Route::post('/call-reports/export', [WebsiteCrmController::class, 'exportCallReports'])->name('call-reports.export');
                    Route::post('/call-reports/share', [WebsiteCrmController::class, 'shareCallReports'])->name('call-reports.share');
                    Route::get('/call-requests', [WebsiteCrmController::class, 'callRequestsIndex'])->name('call-requests.index');
                    Route::post('/call-requests/{callRequest}/fulfill', [WebsiteCrmController::class, 'fulfillCallRequest'])->name('call-requests.fulfill');
                    Route::get('/{lead}', [WebsiteCrmController::class, 'show'])->name('show');
                    Route::get('/{lead}/edit', [WebsiteCrmController::class, 'edit'])->name('edit');
                    Route::put('/{lead}', [WebsiteCrmController::class, 'update'])->name('update');
                    Route::delete('/{lead}', [WebsiteCrmController::class, 'destroy'])->name('destroy');
                    Route::post('/{lead}/follow-up', [WebsiteCrmController::class, 'logFollowUp'])->name('follow-up');
                    Route::delete('/{lead}/follow-up/{followUp}', [WebsiteCrmController::class, 'destroyFollowUp'])->name('follow-up.destroy');
                    Route::patch('/{lead}/status', [WebsiteCrmController::class, 'updateStatus'])->name('status');
                    Route::post('/{lead}/orders', [WebsiteCrmController::class, 'storeOrder'])->name('orders.store');
                });

                // ── eBay CRM ──────────────────────────────────────────────
                Route::prefix('ebay')->name('ebay.')->group(function () {
                    Route::get('/', [EbayCrmController::class, 'index'])->name('index');
                    Route::get('/create', [EbayCrmController::class, 'create'])->name('create');
                    Route::post('/', [EbayCrmController::class, 'store'])->name('store');
                    // eBay Stores
                    Route::prefix('stores')->name('stores.')->group(function () {
                        Route::get('/', [EbayStoreController::class, 'index'])->name('index');
                        Route::get('/create', [EbayStoreController::class, 'create'])->name('create');
                        Route::post('/', [EbayStoreController::class, 'store'])->name('store');
                        Route::get('/{store}', [EbayStoreController::class, 'show'])->name('show');
                        Route::get('/{store}/edit', [EbayStoreController::class, 'edit'])->name('edit');
                        Route::put('/{store}', [EbayStoreController::class, 'update'])->name('update');
                        Route::delete('/{store}', [EbayStoreController::class, 'destroy'])->name('destroy');
                        Route::get('/{store}/export', [EbayStoreController::class, 'exportReport'])->name('export');
                    });

                    Route::get('/report', [EbayReportController::class, 'index'])->name('report');
                    Route::get('/report/export/pdf', [EbayReportController::class, 'exportPdf'])->name('report.export.pdf');
                    Route::get('/report/export/csv', [EbayReportController::class, 'exportCsv'])->name('report.export.csv');

                    // eBay Manage Customer — one combined list and one combined form;
                    // Category is a form field / filter, not a URL segment.
                    Route::prefix('customers')->name('customers.')->group(function () {
                        Route::get('/', [EbayCustomerController::class, 'index'])->name('index');
                        Route::get('/create', [EbayCustomerController::class, 'create'])->name('create');
                        Route::post('/', [EbayCustomerController::class, 'store'])->name('store');
                        // Must stay above the /{record} catch-all below.
                        Route::get('/handler-history', [EbayCustomerController::class, 'handlerHistory'])->name('handler-history.index');
                        Route::get('/{record}/edit', [EbayCustomerController::class, 'edit'])->name('edit');
                        Route::get('/{record}', [EbayCustomerController::class, 'show'])->name('show');
                        Route::put('/{record}', [EbayCustomerController::class, 'update'])->name('update');
                        Route::delete('/{record}', [EbayCustomerController::class, 'destroy'])->name('destroy');
                        Route::post('/{record}/switch-handler', [EbayCustomerController::class, 'switchHandler'])->name('switch-handler');
                        Route::post('/{record}/follow-up', [EbayCustomerController::class, 'logFollowUp'])->name('follow-up');
                        Route::delete('/{record}/follow-up/{followUp}', [EbayCustomerController::class, 'destroyFollowUp'])->name('follow-up.destroy');
                        Route::post('/{record}/orders', [EbayCustomerController::class, 'storeOrder'])->name('orders.store');
                        Route::post('/handler-history/{entry}/confirm', [EbayCustomerController::class, 'confirmHandler'])->name('handler-history.confirm');
                    });

                    Route::get('/{offer}', [EbayCrmController::class, 'show'])->name('show');
                    Route::get('/{offer}/edit', [EbayCrmController::class, 'edit'])->name('edit');
                    Route::put('/{offer}', [EbayCrmController::class, 'update'])->name('update');
                    Route::delete('/{offer}', [EbayCrmController::class, 'destroy'])->name('destroy');
                    Route::post('/{offer}/authorize', [EbayCrmController::class, 'authorizeOffer'])->name('authorize');
                    Route::post('/{offer}/convert', [EbayCrmController::class, 'convertToOrder'])->name('convert');
                });

                // ── Logistic CRM ──────────────────────────────────────────
                Route::prefix('logistics')->name('logistics.')->group(function () {
                    Route::get('/', [LogisticCrmController::class, 'index'])->name('index');
                    Route::get('/create', [LogisticCrmController::class, 'create'])->name('create');
                    Route::post('/', [LogisticCrmController::class, 'store'])->name('store');
                    // AJAX search + quick-create helpers
                    Route::get('/search/customers', [LogisticCrmController::class, 'searchCustomers'])->name('search.customers');
                    Route::get('/search/products', [LogisticCrmController::class, 'searchProducts'])->name('search.products');
                    Route::post('/quick/customer', [LogisticCrmController::class, 'quickCreateCustomer'])->name('quick.customer');
                    Route::post('/quick/product', [LogisticCrmController::class, 'quickCreateProduct'])->name('quick.product');
                    // Logistic Issues — every customer currently flagged with a shipment problem
                    Route::get('/issues', [ShipmentController::class, 'issues'])->name('issues.index');

                    // Trucking Company Management
                    Route::prefix('trucking')->name('trucking.')->group(function () {
                        Route::get('/', [TruckingCompanyController::class, 'index'])->name('index');
                        Route::get('/create', [TruckingCompanyController::class, 'create'])->name('create');
                        Route::post('/', [TruckingCompanyController::class, 'store'])->name('store');
                        Route::get('/{truckingCompany}', [TruckingCompanyController::class, 'show'])->name('show');
                        Route::get('/{truckingCompany}/edit', [TruckingCompanyController::class, 'edit'])->name('edit');
                        Route::put('/{truckingCompany}', [TruckingCompanyController::class, 'update'])->name('update');
                        Route::delete('/{truckingCompany}', [TruckingCompanyController::class, 'destroy'])->name('destroy');
                        Route::post('/{truckingCompany}/drivers', [TruckingCompanyController::class, 'storeDriver'])->name('drivers.store');
                        Route::delete('/{truckingCompany}/drivers/{driver}', [TruckingCompanyController::class, 'destroyDriver'])->name('drivers.destroy');
                    });

                    // Shipment Management
                    Route::prefix('shipments')->name('shipments.')->group(function () {
                        Route::get('/', [ShipmentController::class, 'index'])->name('index');
                        Route::get('/create', [ShipmentController::class, 'create'])->name('create');
                        Route::post('/', [ShipmentController::class, 'store'])->name('store');
                        Route::get('/{shipment}', [ShipmentController::class, 'show'])->name('show');
                        Route::get('/{shipment}/edit', [ShipmentController::class, 'edit'])->name('edit');
                        Route::put('/{shipment}', [ShipmentController::class, 'update'])->name('update');
                        Route::delete('/{shipment}', [ShipmentController::class, 'destroy'])->name('destroy');
                        // Customer sub-routes (edited via the inline modal on the shipment show page)
                        Route::post('/{shipment}/customers', [ShipmentController::class, 'addCustomer'])->name('customers.add');
                        Route::put('/{shipment}/customers/{customer}', [ShipmentController::class, 'updateCustomer'])->name('customers.update');
                        Route::delete('/{shipment}/customers/{customer}', [ShipmentController::class, 'removeCustomer'])->name('customers.remove');
                    });

                    Route::get('/{logistic}', [LogisticCrmController::class, 'show'])->name('show');
                    Route::get('/{logistic}/edit', [LogisticCrmController::class, 'edit'])->name('edit');
                    Route::put('/{logistic}', [LogisticCrmController::class, 'update'])->name('update');
                    Route::delete('/{logistic}', [LogisticCrmController::class, 'destroy'])->name('destroy');
                    Route::post('/{logistic}/status', [LogisticCrmController::class, 'pushStatus'])->name('status');
                    Route::post('/{logistic}/proof', [LogisticCrmController::class, 'uploadProof'])->name('proof');
                });

                // ── Products ──────────────────────────────────────────────
                Route::prefix('products')->name('products.')->group(function () {
                    Route::get('/', [ProductController::class, 'index'])->name('index');
                    Route::get('/create', [ProductController::class, 'create'])->name('create');
                    Route::post('/', [ProductController::class, 'store'])->name('store');
                    Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
                    Route::put('/{product}', [ProductController::class, 'update'])->name('update');
                    Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
                    Route::post('/categories', [ProductController::class, 'storeCategory'])->name('categories.store');
                    Route::patch('/categories/{category}/reorder', [ProductController::class, 'reorderCategory'])->name('categories.reorder');
                    Route::put('/categories/{category}', [ProductController::class, 'updateCategory'])->name('categories.update');
                    Route::delete('/categories/{category}', [ProductController::class, 'destroyCategory'])->name('categories.destroy');
                    Route::get('/import/template', [ProductController::class, 'downloadTemplate'])->name('import.template');
                    Route::get('/import/template-xlsx', [ProductController::class, 'downloadGoogleSheetsTemplate'])->name('import.template.xlsx');
                    Route::post('/import', [ProductController::class, 'import'])->name('import');
                    Route::get('/export', [ProductController::class, 'export'])->name('export');
                    Route::get('/search', [ProductController::class, 'search'])->name('search');
                });

                // ── CRM External Links ─────────────────────────────────────
                Route::prefix('links')->name('links.')->group(function () {
                    Route::get('/', [CrmExternalLinkController::class, 'index'])->name('index');
                    Route::post('/', [CrmExternalLinkController::class, 'store'])->name('store');
                    Route::post('/bulk-update', [CrmExternalLinkController::class, 'bulkUpdate'])->name('bulkUpdate');
                    Route::put('/{link}', [CrmExternalLinkController::class, 'update'])->name('update');
                    Route::delete('/{link}', [CrmExternalLinkController::class, 'destroy'])->name('destroy');
                });

                // ── Tech Support ────────────────────────────────────────────
                Route::prefix('tech-support')->name('tech-support.')->group(function () {
                    Route::get('/', [TechSupportController::class, 'index'])->name('index');
                    Route::get('/{case}', [TechSupportController::class, 'show'])->name('show');

                    // Managing a case (status/assign/follow-up/call) is restricted to Technical Support + admin overrides
                    Route::middleware('role:super-admin|admin-crm|tech-support')->group(function () {
                        Route::patch('/{case}/status', [TechSupportController::class, 'updateStatus'])->name('status');
                        Route::post('/{case}/assign', [TechSupportController::class, 'assign'])->name('assign');
                        Route::post('/{case}/follow-up', [TechSupportController::class, 'storeFollowUp'])->name('follow-up');
                        Route::delete('/{case}/follow-up/{log}', [TechSupportController::class, 'destroyFollowUp'])->name('follow-up.destroy');
                        Route::post('/{case}/request-call', [TechSupportController::class, 'requestCall'])->name('request-call');
                    });
                });

                // ── All Customers — merged into the Customer Database page ──
                Route::get('/directory', fn () => redirect()->route('crm.customers.index'))
                    ->name('directory.index');

                // ── Staff Performance / Team Reports ────────────────────────
                Route::prefix('reports')->name('reports.')->group(function () {
                    Route::get('/', [CrmStaffReportController::class, 'index'])->name('index');
                    Route::get('/export/pdf', [CrmStaffReportController::class, 'exportTeamPdf'])->name('export.pdf');
                    Route::get('/export/csv', [CrmStaffReportController::class, 'exportTeamCsv'])->name('export.csv');
                    Route::get('/staff', [CrmStaffReportController::class, 'staff'])->name('staff');
                    Route::get('/{user}', [CrmStaffReportController::class, 'show'])->name('show');
                    Route::get('/{user}/export', [CrmStaffReportController::class, 'export'])->name('export');
                });
            });

        // ── Reports — Phase 5 ─────────────────────────────────────────────
        Route::middleware(['role:super-admin|admin-digital|admin-crm|sales-crm|boss'])
            ->prefix('reports')
            ->name('reports.')
            ->group(function () {
                Route::get('/', [ReportController::class, 'index'])->name('index');
            });

        // ── Profile & Settings ────────────────────────────────────────────
        Route::get('/emails/poll', [App\Http\Controllers\Admin\EmailController::class, 'pollNewEmails'])->name('admin.emails.poll');
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
                        Route::get('/reports', [SocialMediaReportController::class, 'index'])->name('reports.index');
                        Route::get('/reports/export/csv', [SocialMediaReportController::class, 'exportCsv'])->name('reports.export.csv');
                        Route::get('/reports/export/pdf', [SocialMediaReportController::class, 'exportPdf'])->name('reports.export.pdf');
                        Route::post('/reports/export/zip', [SocialMediaReportController::class, 'exportZip'])->name('reports.export.zip');
                    });

                // Analytics View/Download (including Boss)
                Route::middleware('role:super-admin|admin-digital|social_qc|boss')->group(function () {
                    Route::get('/analytics', [SocialMediaAnalyticsController::class, 'index'])->name('analytics.index');
                    Route::get('/analytics/{analytic}/download', [SocialMediaAnalyticsController::class, 'download'])->name('analytics.download');
                    Route::get('/analytics/{analytic}/preview', [SocialMediaAnalyticsController::class, 'preview'])->name('analytics.preview');
                });

                // Admin/QC actions (excluding Boss)
                Route::middleware('role:super-admin|admin-digital|social_qc')->group(function () {
                    Route::patch('/posts/{post}/check', [SocialMediaPostController::class, 'markChecked'])->name('posts.check');
                    Route::patch('/posts/{post}/unlock', [SocialMediaPostController::class, 'unlock'])->name('posts.unlock');
                    Route::post('/analytics', [SocialMediaAnalyticsController::class, 'store'])->name('analytics.store');
                    Route::delete('/analytics/{analytic}', [SocialMediaAnalyticsController::class, 'destroy'])->name('analytics.destroy');
                });


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

// Root redirect

Route::get('/', function () {
    if (auth()->check()) {
        if (auth()->user()->hasRole('admin-crm')) {
            return redirect()->route('crm.dashboard');
        }
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
})->name('home');

// ── Public Webhook: Google Apps Script Email Push ─────────────────────────
// No auth needed — secured by secret key validated inside the controller
Route::post('/webhook/google-script', [GoogleScriptWebhookController::class, 'receive'])
    ->name('webhook.google-script')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/webhook/google-script/sync-trash', [GoogleScriptWebhookController::class, 'syncTrash'])
    ->name('webhook.google-script.sync-trash')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
