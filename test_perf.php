<?php
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

echo "Performance Test Results:\n";
echo str_repeat("-", 90) . "\n";
printf("%-45s | %-12s | %-15s | %-6s\n", "URL", "Query Count", "Time (ms)", "Status");
echo str_repeat("-", 90) . "\n";

$kernel = app()->make(\Illuminate\Contracts\Http\Kernel::class);

foreach ($urls as $url) {
    DB::flushQueryLog();
    
    $start = microtime(true);
    
    $request = Illuminate\Http\Request::create($url, 'GET');
    $request->setUserResolver(function () use ($user) {
        return $user;
    });
    
    auth()->login($user);
    
    try {
        $response = $kernel->handle($request);
        $time = (microtime(true) - $start) * 1000;
        
        $queries = count(DB::getQueryLog());
        
        printf("%-45s | %-12d | %-15.2f | %-6d\n", $url, $queries, $time, $response->getStatusCode());
    } catch (\Exception $e) {
        printf("%-45s | %-12s | %-15s | %-6s\n", $url, "ERROR", $e->getMessage(), "-");
    }
}
echo str_repeat("-", 90) . "\n";
