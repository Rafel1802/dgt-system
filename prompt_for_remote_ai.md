# Prompt for your kiuq.kiuq.net AI Assistant

*Copy everything below the line and paste it into the AI assistant that manages your `kiuq.kiuq.net` system:*

---

**Act as an Expert Laravel Developer.** 

We have a central dashboard that needs to securely pull an "All Websites Summary" from this system via an API endpoint (`GET /api/v1/websites-summary`). 

I have a template controller code below that outputs the exact JSON structure the central dashboard expects. However, the database queries in this template are just generic examples.

**Your task:**
1. Examine our actual database schema in this project. Find the correct tables/models that store our websites (or brand profiles), tasks, quality check issues, and followup logs.
2. Adapt the `WebsitesSummaryController` template below so that it queries our REAL database tables while maintaining the EXACT SAME output JSON structure.
3. Register the `/api/v1/websites-summary` route in `routes/api.php` and secure it with a middleware that ensures `$request->bearerToken() === env('WEBSITE_API_SECRET')`.

**Here is the exact JSON structure the central dashboard expects for each website:**
```json
{
  "id": 1,
  "name": "Electric Forklift",
  "domain": "electricforklift.com",
  "logo_url": "...",
  "metrics": {
    "to_do": 5,
    "in_progress": 2,
    "completed": 10,
    "issues_new": 1,
    "issues_fixed": 3,
    "issues_approved": 2
  },
  "followups": {
    "ebay_clicks": true,
    "optimization": false,
    "blogs": true,
    "plugins": true
  },
  "recent_tasks": [
    { "id": 101, "name": "Fix homepage banner", "status": "In Progress", "due_date": "2026-06-15" }
  ]
}
```

**Here is the Controller Template to adapt:**

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
        // 1. Get query filters from the central system
        $dateFrom = $request->get('start_date');
        $dateTo = $request->get('end_date');
        $range = $request->get('range'); // 'day', 'week', 'month'

        // TODO: Query our ACTUAL database to get the websites
        $websites = DB::table('websites')->get(); 
        
        $payload = [];

        foreach ($websites as $site) {
            
            // TODO: Query our ACTUAL tasks/logs related to this website
            $tasksQuery = DB::table('tasks')->where('website_id', $site->id);
                
            if ($dateFrom && $dateTo) {
                $tasksQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
            }
            
            $tasks = $tasksQuery->get();

            // Structure the data exactly how the central system expects it
            $payload[] = [
                'id' => $site->id,
                'name' => ucfirst($site->name),
                'domain' => strtolower(str_replace(' ', '', $site->name)) . '.com',
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
                    'ebay_clicks' => true,      // TODO: Calculate based on actual tables
                    'optimization' => false,    // TODO: Calculate based on actual tables
                    'blogs' => true,            // TODO: Calculate based on actual tables
                    'plugins' => true           // TODO: Calculate based on actual tables
                ],
                'recent_tasks' => $tasks->take(5)->map(function($t) {
                    return [
                        'id' => $t->id,
                        'name' => $t->title ?? $t->name,
                        'status' => $t->status,
                        'due_date' => $t->due_date ?? $t->created_at
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
