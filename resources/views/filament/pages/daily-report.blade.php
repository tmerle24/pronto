@php
    $euro = fn (int $cents) => number_format($cents / 100, 2, ',', '.').' €';
    $min = fn (?float $m) => $m === null ? '-' : str_replace('.', ',', (string) $m).' Min';
@endphp
<x-filament-panels::page>
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">
        <label>
            <span style="display:block;font-size:12px;opacity:.7">Event</span>
            <select wire:model.live="eventId" style="min-width:260px;padding:8px;border-radius:8px;border:1px solid #ccc">
                @foreach ($events as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span style="display:block;font-size:12px;opacity:.7">Tag (leer = ganzes Event)</span>
            <input type="date" wire:model.live="day" style="padding:8px;border-radius:8px;border:1px solid #ccc">
        </label>
        @if ($csvUrl)
            <x-filament::button tag="a" :href="$csvUrl" icon="heroicon-o-arrow-down-tray">CSV-Export</x-filament::button>
        @endif
    </div>

    @if ($report)
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px">
            @foreach ([
                ['Bestellungen', $report['orders']],
                ['Umsatz (bezahlt)', $euro($report['revenue_cents'])],
                ['davon Pfand', $euro($report['deposit_cents'])],
                ['Erstattet', $euro($report['refunded_cents'])],
                ['Storniert', $report['cancelled']],
                ['Bar noch offen', $report['open_cash']],
                ['Ø Wartezeit', $min($report['avg_wait'])],
                ['Ø im Ofen', $min($report['avg_oven'])],
                ['Ø bis Abholung', $min($report['avg_pickup'])],
            ] as [$label, $value])
                <x-filament::section>
                    <div style="font-size:12px;opacity:.7">{{ $label }}</div>
                    <div style="font-size:24px;font-weight:700">{{ $value }}</div>
                </x-filament::section>
            @endforeach
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:12px">
            <x-filament::section heading="Umsatz nach Zahlungsart">
                <table style="width:100%">
                    @foreach ($report['by_method'] as $row)
                        <tr><td>{{ $row['method'] }}</td><td style="text-align:right">{{ $row['count'] }}×</td><td style="text-align:right">{{ $euro($row['cents']) }}</td></tr>
                    @endforeach
                </table>
            </x-filament::section>
            <x-filament::section heading="Renner">
                <table style="width:100%">
                    @forelse ($report['top_items'] as $item)
                        <tr><td>{{ $item['name'] }}</td><td style="text-align:right">{{ $item['quantity'] }}×</td><td style="text-align:right">{{ $euro($item['cents']) }}</td></tr>
                    @empty
                        <tr><td>Noch nichts verkauft.</td></tr>
                    @endforelse
                </table>
            </x-filament::section>
            <x-filament::section heading="Bestellungen pro Stunde">
                <table style="width:100%">
                    @forelse ($report['per_hour'] as $hour => $count)
                        <tr><td>{{ $hour }}</td><td style="width:70%"><div style="background:#B8202B;height:12px;border-radius:6px;width:{{ min(100, $count * 100 / max(1, max($report['per_hour']))) }}%"></div></td><td style="text-align:right">{{ $count }}</td></tr>
                    @empty
                        <tr><td>-</td></tr>
                    @endforelse
                </table>
            </x-filament::section>
        </div>
    @else
        <p>Noch kein Event angelegt.</p>
    @endif
</x-filament-panels::page>
