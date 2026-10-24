@php
    $euro = fn (int $cents) => number_format($cents / 100, 2, ',', '.').' €';
    $hours = fn (float $h) => number_format($h, 2, ',', '').' h';
@endphp
<x-filament-panels::page>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px">
        @foreach ([
            ['Umsatz netto', $euro($s['net_cents'])],
            ['Kosten gesamt', $euro($s['card_fee_cents'] + $s['costs_cents'])],
            ['Gewinn des Tages', $euro($s['profit_cents'])],
            ['Stunden Team', $hours($s['hours'])],
            ['Pro Stunde', $s['hours'] > 0 ? $euro((int) floor(max(0, $s['profit_cents']) / $s['hours'])) : '-'],
        ] as [$label, $value])
            <x-filament::section>
                <div style="font-size:12px;opacity:.7">{{ $label }}</div>
                <div style="font-size:24px;font-weight:700">{{ $value }}</div>
            </x-filament::section>
        @endforeach
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:12px">
        <x-filament::section heading="So entsteht der Gewinn">
            <table style="width:100%">
                <tr><td>Umsatz bezahlt ({{ $s['orders'] }} Bestellungen, ohne Pfand)</td><td style="text-align:right">{{ $euro($s['gross_cents']) }}</td></tr>
                <tr><td>– Umsatzsteuer</td><td style="text-align:right">{{ $euro($s['vat_cents']) }}</td></tr>
                <tr><td>– Kartengebühren ({{ $s['card_payments'] }} Zahlungen, geschätzt)</td><td style="text-align:right">{{ $euro($s['card_fee_cents']) }}</td></tr>
                @foreach ($s['costs'] as $cost)
                    <tr><td>– {{ $cost['label'] }}</td><td style="text-align:right">{{ $euro($cost['cents']) }}</td></tr>
                @endforeach
                <tr style="font-weight:700;border-top:1px solid #ccc"><td>Gewinn</td><td style="text-align:right">{{ $euro($s['profit_cents']) }}</td></tr>
            </table>
            @if ($s['costs'] === [])
                <p style="margin-top:8px;color:#B8202B">Noch keine Kosten eingetragen (Standgebühr, Einkauf Ware ...). Ohne sie ist der Gewinn zu hoch.</p>
            @endif
        </x-filament::section>

        <x-filament::section :heading="$settled ? 'Auszahlung (abgerechnet am '.$settled->format('d.m.Y').')' : 'Auszahlung (vorläufig)'">
            <table style="width:100%">
                <tr style="opacity:.7;font-size:12px"><td>Wer</td><td style="text-align:right">Stunden</td><td style="text-align:right">Anteil</td><td style="text-align:right">Betrag</td></tr>
                @forelse ($s['people'] as $p)
                    <tr>
                        <td>{{ $p['name'] }}</td>
                        <td style="text-align:right">{{ $hours($p['hours']) }}</td>
                        <td style="text-align:right">{{ number_format($p['share'] * 100, 1, ',', '') }} %</td>
                        <td style="text-align:right;font-weight:700">{{ $euro($p['payout_cents']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Noch keine Einsätze eingetragen.</td></tr>
                @endforelse
            </table>
            @if ($s['profit_cents'] < 0)
                <p style="margin-top:8px;color:#B8202B">Verlust: niemand bekommt etwas ausgezahlt.</p>
            @endif
            <p style="margin-top:8px;font-size:12px;opacity:.7">Jeder bekommt den gleichen Betrag pro Stunde. Grundlage sind die Zeiten unter "Einsätze".</p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
