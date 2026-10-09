<?php

// Branding per stand. Kept out of templates so a second stand only needs config.
return [
    'name' => env('BRAND_NAME', 'TIAMO Pizza'),
    'product' => env('BRAND_PRODUCT', 'TIAMO Pronto'),
    // wordmark split around the heart
    'wordmark' => ['TI', 'AMO'],
    'claim' => 'Pizza con amore - dal forno di Timo',
    'instagram' => env('BRAND_INSTAGRAM', '@TiamoDalForno'),
    'website' => env('BRAND_WEBSITE', 'https://www.tiamo-pizza.de'),
    'colors' => [
        'rosso' => '#B8202B',
        'rosso-scuro' => '#8E1620',
        'basilico' => '#2F6B3B',
        'oro' => '#D69A2D',
        'crema' => '#FBF4E6',
        'crema-scuro' => '#F1E4C9',
        'raised' => '#FFFFFF',
        'carbone' => '#2A1E19',
        'grigio' => '#6E625A',
        'notte' => '#1F1713',
    ],
    'legal' => [
        // same operator as the website, so its imprint applies
        'imprint_url' => env('BRAND_IMPRINT_URL', 'https://www.tiamo-pizza.de/impressum'),
        'privacy_url' => '/datenschutz',
    ],
];
