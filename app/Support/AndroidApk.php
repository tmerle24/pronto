<?php

namespace App\Support;

use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

/**
 * Counter app as a direct download behind the team login, outside Google Play.
 * One file on the local disk, replaced by `android:publish-apk`; the json
 * beside it carries version, size and checksum for the page.
 */
class AndroidApk
{
    public const PATH = 'android/tiamo-pronto.apk';

    public const META = 'android/apk.json';

    /** @return array{version: string, size: int, sha256: string, published_at: string}|null */
    public static function info(): ?array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::PATH) || ! $disk->exists(self::META)) {
            return null;
        }

        /** @var array{version: string, size: int, sha256: string, published_at: string}|null */
        return json_decode((string) $disk->get(self::META), true) ?: null;
    }

    /** @return array{version: string, size: int, sha256: string, published_at: string} */
    public static function publish(string $file, string $version): array
    {
        $meta = [
            'version' => $version,
            'size' => (int) filesize($file),
            'sha256' => (string) hash_file('sha256', $file),
            'published_at' => now()->toIso8601String(),
        ];

        $disk = Storage::disk('local');
        $disk->putFileAs(dirname(self::PATH), new File($file), basename(self::PATH));
        $disk->put(self::META, (string) json_encode($meta, JSON_PRETTY_PRINT));

        return $meta;
    }
}
