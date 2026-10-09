<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Beleg {{ $r['number'] }}</title>
    <style>
        @page { margin: 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #2A1E19; }
        h1 { font-family: 'DejaVu Serif', serif; font-size: 20pt; margin: 0; }
        .heart { color: #D69A2D; }
        .muted { color: #6E625A; }
        .band { height: 5px; background: #B8202B; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td, th { padding: 4px 0; text-align: left; vertical-align: top; }
        th { font-size: 8pt; text-transform: uppercase; letter-spacing: 1px; color: #6E625A; border-bottom: 1px solid #F1E4C9; }
        .r { text-align: right; }
        .total td { border-top: 2px solid #2A1E19; font-weight: bold; font-size: 12pt; }
        .small { font-size: 8pt; word-break: break-all; }
        .stamp { color: #B8202B; font-weight: bold; font-size: 14pt; }
    </style>
</head>
<body>
    <div class="band"></div>
    <h1>TI<span class="heart">&hearts;</span>AMO</h1>
    <p class="muted">{{ $r['business']['name'] }} · {{ $r['business']['address'] }}<br>
        @if ($r['business']['tax_number']) Steuernr. {{ $r['business']['tax_number'] }} @endif
        @if ($r['business']['vat_id']) · USt-IdNr. {{ $r['business']['vat_id'] }} @endif
    </p>

    <p><strong>Beleg {{ $r['number'] }}</strong> · {{ $r['date'] }} · Abholnummer {{ $r['pickup_number'] }}<br>
        Zahlung: {{ $r['payment'] }}</p>
    @if ($r['refunded'])
        <p class="stamp">STORNIERT / ERSTATTET</p>
    @endif

    <table>
        <tr><th>Menge</th><th>Artikel</th><th class="r">MwSt.</th><th class="r">Betrag</th></tr>
        @foreach ($r['lines'] as $line)
            <tr>
                <td>{{ $line['quantity'] }}&#8239;×</td>
                <td>{{ $line['name'] }}@if ($line['deposit_cents']) <span class="muted">(inkl. {{ number_format($line['deposit_cents'] / 100, 2, ',', '.') }} € Pfand)</span>@endif</td>
                <td class="r">{{ rtrim(rtrim(number_format($line['vat_rate'], 2, ',', ''), '0'), ',') }} %</td>
                <td class="r">{{ number_format($line['total_cents'] / 100, 2, ',', '.') }} €</td>
            </tr>
        @endforeach
        <tr class="total"><td></td><td>Summe</td><td></td><td class="r">{{ number_format($r['total_cents'] / 100, 2, ',', '.') }} €</td></tr>
    </table>

    <table>
        <tr><th>MwSt.-Satz</th><th class="r">Netto</th><th class="r">MwSt.</th><th class="r">Brutto</th></tr>
        @foreach ($r['vat'] as $g)
            <tr>
                <td>{{ rtrim(rtrim(number_format($g['rate'], 2, ',', ''), '0'), ',') }} %</td>
                <td class="r">{{ number_format($g['net_cents'] / 100, 2, ',', '.') }} €</td>
                <td class="r">{{ number_format($g['vat_cents'] / 100, 2, ',', '.') }} €</td>
                <td class="r">{{ number_format($g['gross_cents'] / 100, 2, ',', '.') }} €</td>
            </tr>
        @endforeach
    </table>

    @if ($r['fiscal'])
        <p class="small muted">TSE-Seriennr. {{ $r['fiscal']['tss_serial'] }} · Transaktion {{ $r['fiscal']['number'] }} · Signaturzähler {{ $r['fiscal']['signature_counter'] }}<br>
            Start {{ $r['fiscal']['time_start'] }} · Ende {{ $r['fiscal']['time_end'] }}<br>
            Signatur {{ $r['fiscal']['signature'] }}</p>
    @endif

    <p class="muted">Grazie! Pizza con amore - dal forno di Timo.</p>
</body>
</html>
