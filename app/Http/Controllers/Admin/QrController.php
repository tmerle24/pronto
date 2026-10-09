<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\QrCode;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** QR sign per event: print page (A4/A5, save as PDF in the browser) and plain SVG. */
class QrController extends Controller
{
    public function show(Event $event): View
    {
        $url = route('guest.event', $event);

        return view('admin.qr-sign', [
            'event' => $event,
            'url' => $url,
            'displayUrl' => preg_replace('#^https?://#', '', $url),
            'qr' => QrCode::svg($url),
            'brand' => config('brand'),
        ]);
    }

    public function svg(Event $event): Response
    {
        return response(QrCode::svg(route('guest.event', $event)), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="tiamo-qr-'.$event->slug.'.svg"',
        ]);
    }
}
