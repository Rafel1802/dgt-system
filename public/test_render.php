<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::create('/websites?tab=follow-up')
);
// bypass auth for testing
auth()->loginUsingId(1);
echo view('websites.index', [
    'tab' => 'follow-up',
    'stats' => [],
    'allWebsites' => collect(),
    'groupedWebsites' => collect(),
    'orderArray' => [],
    'buildWebsites' => collect(),
    'buildProgressWebsites' => collect(),
    'liveWebsites' => collect(),
    'maintenanceWebsites' => collect(),
    'followUps' => \App\Models\WebsiteFollowUp::paginate(50),
    'followUpFilter' => [],
    'users' => collect(),
    'allClasses' => [],
    'websiteMembers' => collect(),
    'memberRolesMap' => [],
    'qcCheckingWebsites' => collect(),
    'supervisorCheckingWebsites' => collect(),
    'qcErrorWebsites' => collect(),
    'supervisorErrorWebsites' => collect(),
    'websiteTeamMembers' => collect(),
    'reportUsers' => collect()
])->render();
