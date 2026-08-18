<?php
// Clear cache
Illuminate\Support\Facades\Cache::flush();
DB::enableQueryLog();

$user = App\Models\User::whereHas('roles', fn($q) => $q->where('name', 'super-admin'))->first();

$urls = [
    '/crm/dashboard',
    '/crm/logistics/shipments',
    '/crm/logistics/process-trucking',
    '/crm/logistics/loaded',
    '/crm/logistics/delivered',
    '/crm/website',
    '/crm/ebay',
    '/crm/reports',
];

echo "Performance Cold Cache Test Results:\n";
echo str_repeat("-", 90) . "\n";
printf("%-45s | %-12s | %-15s\n", "URL", "Query Count", "Time (ms)");
echo str_repeat("-", 90) . "\n";

$kernel = app()->make(\Illuminate\Contracts\Http\Kernel::class);

foreach ($urls as $url) {
    DB::flushQueryLog();
    
    $start = microtime(true);
    
    $request = Illuminate\Http\Request::create($url, 'GET');
    $request->setUserResolver(fn() => $user);
    
    auth()->login($user);
    
    $response = $kernel->handle($request);
    $time = (microtime(true) - $start) * 1000;
    
    $queries = count(DB::getQueryLog());
    
    printf("%-45s | %-12d | %-15.2f\n", $url, $queries, $time);
}
echo str_repeat("-", 90) . "\n";
