<?php

return [
    // where browsers reach Reverb (production: same host behind nginx, port 443)
    'realtime' => [
        'host' => env('REVERB_PUBLIC_HOST', env('REVERB_HOST', 'localhost')),
        'port' => (int) env('REVERB_PUBLIC_PORT', env('REVERB_PORT', 8080)),
        'scheme' => env('REVERB_PUBLIC_SCHEME', env('REVERB_SCHEME', 'http')),
    ],
];
