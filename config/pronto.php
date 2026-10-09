<?php

return [
    // where browsers reach Reverb (production: same host behind nginx, port 443)
    'realtime' => [
        'host' => env('REVERB_PUBLIC_HOST', env('REVERB_HOST', 'localhost')),
        'port' => (int) env('REVERB_PUBLIC_PORT', env('REVERB_PORT', 8080)),
        'scheme' => env('REVERB_PUBLIC_SCHEME', env('REVERB_SCHEME', 'http')),
    ],

    'notifications' => [
        // grace period so an undo on the tablet stops the message
        'delay_seconds' => (int) env('NOTIFY_DELAY_SECONDS', 6),
        // personal contact data is deleted after this
        'retention_hours' => 24,
    ],

    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:team@tiamo-pizza.de'),
    ],

    // driver "log" until credentials exist: nothing is sent, nothing costs
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'log'), // log | meta | 360dialog
        // our number in international format without +, for wa.me links
        'number' => env('WHATSAPP_NUMBER'),
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        // hard cap per day, protects against loops and abuse
        'daily_limit' => (int) env('WHATSAPP_DAILY_LIMIT', 300),
    ],

    // technical security device, off until the tax advisor says otherwise
    'fiscal' => [
        'driver' => env('FISCAL_DRIVER', 'null'), // null | fiskaly
        'fiskaly' => [
            'api_key' => env('FISKALY_API_KEY'),
            'api_secret' => env('FISKALY_API_SECRET'),
            'tss_id' => env('FISKALY_TSS_ID'),
            'client_id' => env('FISKALY_CLIENT_ID'),
        ],
    ],

    // printed on receipts
    'business' => [
        'name' => env('BUSINESS_NAME', 'TIAMO Pizza'),
        'address' => env('BUSINESS_ADDRESS', 'Adresse folgt'),
        'tax_number' => env('BUSINESS_TAX_NUMBER'),
        'vat_id' => env('BUSINESS_VAT_ID'),
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'), // log | seven
        'api_key' => env('SMS_API_KEY'),
        'sender' => env('SMS_SENDER', 'TIAMO'),
        // hard cap per day, every SMS costs money
        'daily_limit' => (int) env('SMS_DAILY_LIMIT', 50),
    ],
];
