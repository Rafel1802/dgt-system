<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WebRTC STUN & TURN Relay Configuration (KIUQ SYSTEM Chat)
    |--------------------------------------------------------------------------
    |
    | Supports both Coturn ephemeral HMAC-SHA1 credentials (WEBRTC_TURN_SECRET)
    | and Managed TURN static credentials (WEBRTC_TURN_USERNAME / CREDENTIAL).
    | Never expose WEBRTC_TURN_SECRET to frontend code; DgtChatCallService
    | generates short-lived expiring credentials per authenticated user.
    |
    */

    'stun_urls' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('WEBRTC_STUN_URLS', implode(',', [
            'stun:stun.l.google.com:19302',
            'stun:stun1.l.google.com:19302',
            'stun:stun2.l.google.com:19302',
            'stun:stun.cloudflare.com:3478',
        ])))
    ))),

    'turn' => [
        // Comma-separated list of TURN/TURNS URLs, e.g.:
        // turn:turn.yourdomain.com:3478?transport=udp,turn:turn.yourdomain.com:3478?transport=tcp,turns:turn.yourdomain.com:5349?transport=tcp
        'urls' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('WEBRTC_TURN_URLS', ''))
        ))),

        // Coturn use-auth-secret HMAC key (recommended for self-hosted Coturn)
        'secret' => env('WEBRTC_TURN_SECRET'),

        // Static TURN credentials (e.g., Cloudflare Calls TURN, Metered, Twilio NTS)
        'username' => env('WEBRTC_TURN_USERNAME'),
        'credential' => env('WEBRTC_TURN_CREDENTIAL'),

        // Time-to-live (seconds) for HMAC-SHA1 ephemeral TURN credentials
        'ttl' => (int) env('WEBRTC_TURN_TTL', 3600),
    ],

    'call' => [
        'ring_timeout_seconds' => (int) env('WEBRTC_RING_TIMEOUT_SECONDS', 45),
        'ice_transport_policy' => env('WEBRTC_ICE_TRANSPORT_POLICY', 'all'),
    ],
];
