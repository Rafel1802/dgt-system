<?php

return [
    /*
    |--------------------------------------------------------------------------
    | kiuq.kiuq.net API Configuration
    |--------------------------------------------------------------------------
    | This is the base URL and token for the kiuq.kiuq.net management API.
    | The token can be set here in .env or overridden via the Admin Settings UI.
    */
    'api_url'  => env('KIUQ_API_URL', 'https://kiuq.kiuq.net'),
    'token'    => env('KIUQ_API_TOKEN', ''),
    'timeout'  => (int) env('KIUQ_API_TIMEOUT', 15),
    'db_connection' => env('KIUQ_DB_CONNECTION', 'kiuq_mysql'),
];
