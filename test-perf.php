<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

function measure($name, $callback) {
    $start = microtime(true);
    $res = $callback();
    $time = round((microtime(true) - $start) * 1000, 2);
    echo "[$name] took {$time}ms\n";
    return $res;
}

$board = \App\Models\Board::first();
if ($board) {
    measure("Load Board Relationships", function() use ($board) {
        $board->load([
            'workspace.members',
            'labels',
            'members',
        ]);
        $board->load(['activeLists.cards' => function ($query) {
            $query->with([
                'creator:id,name,avatar,username',
                'assignees:id,name,avatar,username',
                'labels',
                'checklists' => function ($q) {
                    $q->select('id', 'card_id')->withCount([
                        'items as checklist_total',
                        'items as checklist_done' => function ($query) {
                            $query->where('is_completed', true);
                        }
                    ]);
                }
            ])->withCount([
                'files', 
                'comments',
            ]);
        }]);
    });
}

$user = \App\Models\User::first();
auth()->login($user);

$crmService = app(\App\Services\CrmService::class);
measure("CRM Dashboard Stats", function() use ($crmService) {
    $crmService->getDashboardStats();
});

$matcher = app(\App\Services\CrmCustomerMatchService::class);
measure("CRM Unified Directory", function() use ($matcher) {
    $matcher->buildUnifiedDirectoryRaw([
        'search'  => null,
        'sort_by' => null, 
    ]);
});

