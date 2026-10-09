<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'brand' => fn () => [
                'name' => config('brand.name'),
                'wordmark' => config('brand.wordmark'),
                'instagram' => config('brand.instagram'),
                'legal' => config('brand.legal'),
            ],
            'locale' => fn () => app()->getLocale(),
            // German strings are the keys; only other locales need a dictionary
            'translations' => fn () => $this->translations(app()->getLocale()),
            'auth' => fn () => [
                'user' => $request->user()?->only(['id', 'name', 'role']),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function translations(string $locale): array
    {
        $path = lang_path($locale.'.json');
        if ($locale === 'de' || ! is_file($path)) {
            return [];
        }

        /** @var array<string, string> */
        return json_decode((string) file_get_contents($path), true) ?: [];
    }
}
