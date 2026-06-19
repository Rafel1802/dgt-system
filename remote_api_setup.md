# External API Setup Guide (kiuq.kiuq.net)

Since `kiuq.kiuq.net` is a completely separate system, you need to add an API endpoint there so your local DGT System can securely pull the data. Here is the step-by-step guide and the code you need to copy into the `kiuq.kiuq.net` project.

## Step 1: Create the API Secret Token

You need a secure password (token) so only your systems can talk to each other.

1. Open the `.env` file on **kiuq.kiuq.net** and add this line:
```env
WEBSITE_API_SECRET="KiuQ-Secure-Api-Token-2026"
```

2. Open the `.env` file on your **local DGT System** and add the exact same token so it knows how to authenticate:
```env
KIUQ_API_TOKEN="KiuQ-Secure-Api-Token-2026"
```

## Step 2: Create the Security Middleware (kiuq.kiuq.net)

This protects the API so nobody else can access it.
Run this command in the `kiuq.kiuq.net` terminal:
`php artisan make:middleware ApiTokenMiddleware`

Then, update `app/Http/Middleware/ApiTokenMiddleware.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Check if the provided bearer token matches the one in our .env
        $token = $request->bearerToken();
        
        if (!$token || $token !== env('WEBSITE_API_SECRET')) {
            return response()->json(['error' => 'Unauthorized Access'], 401);
        }

        return $next($request);
    }
}
```

Register this middleware in `app/Http/Kernel.php` under `$routeMiddleware` (or in Laravel 11 `bootstrap/app.php`):
```php
protected $routeMiddleware = [
    // ...
    'api.token' => \App\Http\Middleware\ApiTokenMiddleware::class,
];
```

## Step 3: Register the API Route (kiuq.kiuq.net)

Open `routes/api.php` and add your secure endpoint:

```php
use App\Http\Controllers\Api\WebsitesSummaryController;

Route::prefix('v1')->middleware(['api', 'api.token'])->group(function () {
    Route::get('/websites-summary', [WebsitesSummaryController::class, 'index']);
});
```

## Step 4: Create the API Controller (kiuq.kiuq.net)

Run this command:
`php artisan make:controller Api/WebsitesSummaryController`

Then, paste this code into `app/Http/Controllers/Api/WebsitesSummaryController.php`. *(Note: You may need to adjust the Model names like `Website`, `Task` based on your exact database tables in kiuq.kiuq.net)*:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebsitesSummaryController extends Controller
{
    public function index(Request $request)
    {
        // 1. Get query filters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $memberId = $request->get('member_id');

        // 2. Query your actual database. This is a generic structure matching your prompt.
        // Replace 'websites' and related tables with your actual table names.
        
        $websites = DB::table('websites')->where('is_active', true)->get();
        
        $payload = [];

        foreach ($websites as $site) {
            
            // Example: Count tasks from operations board
            $tasksQuery = DB::table('tasks')
                ->where('website_id', $site->id);
                
            if ($dateFrom && $dateTo) {
                $tasksQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
            }
            if ($memberId) {
                $tasksQuery->where('assigned_to', $memberId);
            }
            
            $tasks = $tasksQuery->get();

            // Structure the data exactly how the DGT System expects it
            $payload[] = [
                'id' => $site->id,
                'name' => $site->name,
                'logo_url' => $site->logo_url ?? null,
                'metrics' => [
                    'to_do' => $tasks->where('status', 'To Do')->count(),
                    'in_progress' => $tasks->where('status', 'In Progress')->count(),
                    'completed' => $tasks->where('status', 'Completed')->count(),
                    'issues_new' => $tasks->where('status', 'New Issue')->count(),
                    'issues_fixed' => $tasks->where('status', 'Fixed')->count(),
                    'issues_approved' => $tasks->where('status', 'Approved')->count(),
                ],
                'followups' => [
                    'ebay_clicks' => true,      // Calculate actual status
                    'optimization' => false,    // Calculate actual status
                    'blogs' => true,            // Calculate actual status
                    'plugins' => true           // Calculate actual status
                ],
                'recent_tasks' => $tasks->take(5)->map(function($t) {
                    return [
                        'id' => $t->id,
                        'name' => $t->title,
                        'status' => $t->status,
                        'due_date' => $t->due_date
                    ];
                })
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $payload
        ]);
    }
}
```

## Summary
Once you deploy these changes to **kiuq.kiuq.net**, your local DGT system will use the `KIUQ_API_TOKEN` to securely fetch the live JSON data and automatically populate the beautiful "All Websites" dashboard!
