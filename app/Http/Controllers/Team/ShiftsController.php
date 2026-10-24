<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Availability;
use App\Models\MarketDay;
use App\Models\Shift;
use App\Models\User;
use App\Services\Staffing\DaySettlement;
use App\Support\TimeOfDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** "Einsätze": every team member enters when they can work and sees their shifts and payouts. */
class ShiftsController extends Controller
{
    // past days stay visible this long, for the payout
    private const PAST_DAYS = 30;

    public function show(Request $request): Response
    {
        $user = $this->user($request);
        $days = MarketDay::query()
            ->where('date', '>=', today()->subDays(self::PAST_DAYS))
            ->with(['event', 'shifts.user', 'availabilities' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderBy('date')
            ->get();

        return Inertia::render('Team/Shifts', [
            'days' => $days->map(fn (MarketDay $day) => $this->day($day, $user))->values(),
        ]);
    }

    public function update(Request $request, MarketDay $marketDay): RedirectResponse
    {
        $this->guardOpen($marketDay);
        $data = $request->validate([
            'from' => ['required', 'date_format:H:i'],
            'until' => ['required', 'date_format:H:i', 'after:from'],
            'note' => ['nullable', 'string', 'max:200'],
        ], ['until.after' => 'Das Ende muss nach dem Anfang liegen.']);

        Availability::query()->updateOrCreate(
            ['market_day_id' => $marketDay->id, 'user_id' => $this->user($request)->id],
            ['available_from' => $data['from'], 'available_until' => $data['until'], 'note' => $data['note'] ?? null],
        );

        return back();
    }

    public function destroy(Request $request, MarketDay $marketDay): RedirectResponse
    {
        $this->guardOpen($marketDay);
        Availability::query()->where('market_day_id', $marketDay->id)->where('user_id', $this->user($request)->id)->delete();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function day(MarketDay $day, User $user): array
    {
        $availability = $day->availabilities->first();
        $past = $day->date->isBefore(today());

        return [
            'id' => $day->id,
            'date' => $day->date->toDateString(),
            'weekday' => $day->dateLabel('dddd'),
            'event' => $day->event->name,
            'location' => $day->event->location,
            'opens' => TimeOfDay::format($day->opens_at),
            'closes' => TimeOfDay::format($day->closes_at),
            'work_from' => $day->workFrom(),
            'work_until' => $day->workUntil(),
            'past' => $past,
            'availability' => $availability ? [
                'from' => TimeOfDay::format($availability->available_from),
                'until' => TimeOfDay::format($availability->available_until),
                'note' => $availability->note,
            ] : null,
            // the plan: who works when, without money
            'team' => $day->shifts->sortBy('starts_at')->map(fn (Shift $s) => [
                'name' => $s->user->name,
                'from' => TimeOfDay::format($s->starts_at),
                'until' => TimeOfDay::format($s->ends_at),
                'task' => $s->task,
                'mine' => $s->user_id === $user->id,
            ])->values(),
            // own payout once the day has begun; preliminary until the admin settles it
            'payout' => $day->date->isAfter(today()) ? null : $this->payout($day, $user),
        ];
    }

    /**
     * @return ?array{hours: float, cents: int, settled: bool}
     */
    private function payout(MarketDay $day, User $user): ?array
    {
        $mine = (new DaySettlement($day))->payoutFor($user);

        return $mine === null ? null : ['hours' => $mine['hours'], 'cents' => $mine['payout_cents'], 'settled' => $day->settled_at !== null];
    }

    private function guardOpen(MarketDay $day): void
    {
        if ($day->date->isBefore(today())) {
            throw ValidationException::withMessages(['from' => 'Der Tag ist vorbei.']);
        }
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
