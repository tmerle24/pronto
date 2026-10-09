<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** German by default; English via ?lang=en or browser language, remembered in the session. */
class SetLocale
{
    public const SUPPORTED = ['de', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->query('lang');
        if (is_string($requested) && in_array($requested, self::SUPPORTED, true)) {
            $request->session()->put('locale', $requested);
        }

        $locale = $request->session()->get('locale')
            ?? $request->getPreferredLanguage(self::SUPPORTED)
            ?? 'de';

        app()->setLocale(in_array($locale, self::SUPPORTED, true) ? $locale : 'de');

        return $next($request);
    }
}
