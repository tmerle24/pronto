<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="{{ config('brand.colors.rosso') }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex">
        <style>
            :root {
            @foreach (config('brand.colors') as $token => $hex)
                --{{ $token }}: {{ $hex }};
            @endforeach
            }
        </style>
        <title inertia>{{ config('brand.product') }}</title>
        @routes
        @vite('resources/js/app.js')
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
