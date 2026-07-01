<?php
$content = file_get_contents('resources/views/websites/index.blade.php');

$content = preg_replace('/<div class="card border border-dashed[^>]+>/', '<div class="card border border-dashed border-slate-200 dark:border-slate-700 p-16 text-center">', $content);

$content = str_replace(
    '<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @php',
    '<div>
            @php',
    $content
);
$content = str_replace(
    '<div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            @php',
    '<div>
            @php',
    $content
);
$content = str_replace(
    '<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @php',
    '<div>
            @php',
    $content
);

file_put_contents('resources/views/websites/index.blade.php', $content);
