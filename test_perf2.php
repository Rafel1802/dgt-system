<?php
DB::enableQueryLog();

$user = App\Models\User::whereHas('roles', fn($q) => $q->where('name', 'super-admin'))->first();
$url = '/crm/reports';
$kernel = app()->make(\Illuminate\Contracts\Http\Kernel::class);

DB::flushQueryLog();

$request = Illuminate\Http\Request::create($url, 'GET');
$request->setUserResolver(fn() => $user);
auth()->login($user);

$kernel->handle($request);

$queries = DB::getQueryLog();
$output = "";
foreach ($queries as $q) {
    $output .= $q['query'] . " [" . implode(", ", $q['bindings']) . "]\n";
}
file_put_contents('scratch/queries.txt', $output);
echo "Logged " . count($queries) . " queries for $url to scratch/queries.txt\n";
