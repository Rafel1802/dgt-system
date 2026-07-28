"All Websites" Management Hub API Upgrade Walkthrough
We have created the PHP/MySQL database schemas, Eloquent models, routes, and REST API controller needed to support the All Websites management hub on your main Laravel system.
The complete codebase has been generated and saved under laravel-api-upgrade/ for easy deployment.

File Structure & Links
Below are the generated files ready for copy-paste or migration integration:
MySQL Schema Migration: create_all_tables.php
Eloquent Models:
Profile.php - User profiles
Website.php - Brand profile sites
BlogFollowupSchedule.php - Schedules (weekly/monthly/custom)
BlogUploadSlot.php - Blog audit log checklist
PluginUpdateSlot.php - Weekly plugin logs
SpeedOptimizationSlot.php - Weekly speed optimization checklist
EbayClickFollowup.php - Daily eBay checklist entries
PromptAssignment.php - Work board assignments (Tasks)
QualityIssue.php - Trello-style website fix tracker
REST Controller: WebsitesSummaryController.php
API Routes: api.php

REST API Specification
Endpoint
GET /api/v1/websites-summary
Headers
Authorization: Bearer <sanctum-token>
Accept: application/json
Query Parameters
range: Optional. Filter date range relative to today. Values: day, week, month, all.
start_date: Optional. Custom start date (e.g., YYYY-MM-DD).
end_date: Optional. Custom end date (e.g., YYYY-MM-DD). Must be after or equal to start_date.
JSON Response Structure
json
{
 "success": true,
 "count": 1,
 "data": [
   {
     "id": "e5bfa782-b7e1-455b-80df-2cd98c2514ff",
     "name": "Mini Excavator",
     "domain": "miniexcavator.org",
     "status": "active",
     "created_by": {
       "id": "a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d",
       "name": "John Doe",
       "email": "john@kiuq.net"
     },
     "followup_schedule": {
       "type": "weekly",
       "weekdays": [2, 6],
       "monthly_days": null
     },
     "followups": {
       "ebay_click_logs": [
         {
           "id": "993cf117-910a-482a-9e1e-28b98a3cbbff",
           "date": "2026-06-09",
           "day_name": "Tuesday",
           "status": "done",
           "completed_at": "2026-06-09T13:25:04+07:00"
         }
       ],
       "blog_audits": [
         {
           "id": "d748f321-cc54-4aa2-944a-e45f9c424a73",
           "scheduled_date": "2026-06-09",
           "status": "done",
           "blog_title": "Best Mini Excavators of 2026",
           "blog_url": "https://miniexcavator.org/best-2026"
         }
       ],
       "plugin_updates": [
         {
           "id": "c1f7b7f1-8cb4-49c7-951b-5e608034aefb",
           "week_start": "2026-06-08",
           "status": "done",
           "check_result": "no_updates_needed",
           "updated_count": 0
         }
       ],
       "website_optimizations": [
         {
           "id": "f84b9c1d-15e8-466d-ad02-3b7c53641b02",
           "week_start": "2026-06-08",
           "status": "done",
           "before_score": 85,
           "after_score": 98
         }
       ]
     },
     "tasks": [
       {
         "id": "31bdf742-acdf-4bb2-b5de-de56ca634ff3",
         "title": "Create Contact Page",
         "status": "In Progress",
         "assigned_member": {
           "id": "a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d",
           "name": "John Doe"
         }
       }
     ],
     "quality_check": {
       "new_issues_count": 2,
       "fixed_count": 5,
       "approved_count": 12
     }
   }
 ]
}

Production Deployment Steps (kiuq.kiuq.net)
To deploy these changes to your production system:
Copy Files to Project: Move files from docs/laravel-api-upgrade/ into the matching directories of your Laravel project on the server.
Run Migrations: Run the command below in your server's root terminal:
bash
php artisan migrate
Install Laravel Sanctum (If not already configured): If Sanctum isn't set up yet, run:
bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
Add Sanctum's middleware to app/Http/Kernel.php if you are using stateful SPAs, or ensure Sanctum config has the user model configured.
Issue API Token: Issue a personal access token for your client to authenticate calls:
php
$user = User::where('email', 'admin@kiuq.net')->first();
$token = $user->createToken('hub-delivery-token')->plainTextToken;
// Save this token to authenticate requests!
Clear Route Caches (CRITICAL): Ensure Laravel loads the new API route mapping by clearing caches:
bash
php artisan route:clear
php artisan route:cache

