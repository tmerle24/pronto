<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Reports\DailyReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function csv(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'event' => ['required', 'integer', 'exists:events,id'],
            'day' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $event = Event::query()->whereKey((int) $data['event'])->firstOrFail();
        $day = isset($data['day']) ? CarbonImmutable::parse($data['day']) : null;
        $report = new DailyReport($event, $day);

        $name = 'tiamo-'.$event->slug.($day ? '-'.$day->format('Y-m-d') : '').'.csv';

        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            // BOM + semicolon: opens cleanly in German Excel
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($report->rows() as $row) {
                fputcsv($out, $row, ';', '"', '');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
