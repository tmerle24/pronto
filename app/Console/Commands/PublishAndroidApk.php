<?php

namespace App\Console\Commands;

use App\Support\AndroidApk;
use Illuminate\Console\Command;

/** Puts a signed release APK online at /team/app. */
class PublishAndroidApk extends Command
{
    protected $signature = 'android:publish-apk {file : signed release APK} {--app-version= : versionName, e.g. 1.0.0}';

    protected $description = 'Publish the counter app APK offered at /team/app';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $version = (string) $this->option('app-version');

        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }
        // an APK is a zip; catches an AAB or a wrong path early
        if (file_get_contents($file, false, null, 0, 2) !== 'PK' || ! str_ends_with(strtolower($file), '.apk')) {
            $this->error('Not an APK.');

            return self::FAILURE;
        }
        if ($version === '') {
            $this->error('--app-version is required.');

            return self::FAILURE;
        }

        $meta = AndroidApk::publish($file, $version);

        $this->info(sprintf('Published %s (%.1f MB, sha256 %s)', $meta['version'], $meta['size'] / 1048576, $meta['sha256']));

        return self::SUCCESS;
    }
}
