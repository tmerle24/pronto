<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR-Aufsteller {{ $event->name }}</title>
    <style>
        :root {
        @foreach ($brand['colors'] as $token => $hex)
            --{{ $token }}: {{ $hex }};
        @endforeach
        }
        @page { size: A4 portrait; margin: 0; }
        body { margin: 0; background: #ddd; }
        .sheet { width: 210mm; height: 297mm; margin: 10mm auto; background: var(--crema); position: relative; overflow: hidden; box-sizing: border-box; padding: 22mm 18mm; text-align: center; }
        .band { position: absolute; left: 0; right: 0; height: 9mm; background: linear-gradient(90deg, #2f6b3b 0 33.33%, #fff 33.33% 66.66%, #b8202b 66.66%); }
        .band.top { top: 0; } .band.bottom { bottom: 0; }
        .qr { width: 120mm; height: 120mm; margin: 8mm auto; background: #fff; border-radius: 8mm; padding: 6mm; box-sizing: border-box; }
        .qr svg { width: 100%; height: 100%; }
        .no-print { text-align: center; margin: 6mm; font-family: sans-serif; }
        @media print { body { background: none; } .sheet { margin: 0; } .no-print { display: none; } }
    </style>
    @vite('resources/css/app.css')
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="font-size:16px;padding:10px 18px">Drucken / als PDF speichern</button>
        <a href="{{ route('admin.events.qr.svg', $event) }}" style="margin-left:12px">Nur QR-Code als SVG</a>
    </div>
    <div class="sheet">
        <div class="band top"></div>
        <p class="font-display text-7xl font-black tracking-tight text-carbone">{{ $brand['wordmark'][0] }}<span class="text-oro">♥</span>{{ $brand['wordmark'][1] }}</p>
        <p class="mt-2 font-script text-5xl text-rosso">Pronto!</p>
        <p class="mt-4 font-display text-5xl font-bold text-carbone">Scan &amp; bestell</p>
        <div class="qr">{!! $qr !!}</div>
        <p class="label-caps text-lg text-carbone">Bestellen · Bezahlen · Wir sagen Bescheid</p>
        <p class="mt-3 text-xl text-grigio">Ohne App, ohne Konto. Apple Pay, Google Pay, Karte, PayPal oder bar.</p>
        <p class="mt-6 font-semibold text-carbone">{{ $displayUrl }}</p>
        <p class="mt-1 text-grigio">{{ $event->name }} · {{ $brand['instagram'] }}</p>
        <div class="band bottom"></div>
    </div>
</body>
</html>
