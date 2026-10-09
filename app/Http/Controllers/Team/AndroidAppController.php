<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Support\AndroidApk;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Counter app (Tap to Pay) as APK, team only. */
class AndroidAppController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Team/AndroidApp', ['apk' => AndroidApk::info()]);
    }

    public function download(): StreamedResponse
    {
        abort_if(AndroidApk::info() === null, 404);

        return Storage::disk('local')->download(AndroidApk::PATH, 'tiamo-pronto.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
