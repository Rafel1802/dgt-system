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

echo "Performance Cache Test Results:\n";
echo str_repeat("-", 110) . "\n";
printf("%-40s | %-12s | %-12s | %-12s | %-12s\n", "URL", "Q Count 1", "Time 1 (ms)", "Q Count 2", "Time 2 (ms)");
echo str_repeat("-", 110) . "\n";

$kernel = app()->make(\Illuminate\Contracts\Http\Kernel::class);

foreach ($urls as $url) {
    // Run 1
    DB::flushQueryLog();
    $start1 = microtime(true);
    $request1 = Illuminate\Http\Request::create($url, 'GET');
    $request1->setUserResolver(fn() => $user);
    auth()->login($user);
    $response1 = $kernel->handle($request1);
    $time1 = (microtime(true) - $start1) * 1000;
    $queries1 = count(DB::getQueryLog());

    // Run 2
    DB::flushQueryLog();
    $start2 = microtime(true);
    $request2 = Illuminate\Http\Request::create($url, 'GET');
    $request2->setUserResolver(fn() => $user);
    auth()->login($user);
    $response2 = $kernel->handle($request2);
    $time2 = (microtime(true) - $start2) * 1000;
    $queries2 = count(DB::getQueryLog());
    
    printf("%-40s | %-12d | %-12.2f | %-12d | %-12.2f\n", $url, $queries1, $time1, $queries2, $time2);
}
echo str_repeat("-", 110) . "\n";
