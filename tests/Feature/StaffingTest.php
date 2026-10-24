<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Availability;
use App\Models\Event;
use App\Models\EventCost;
use App\Models\Invitation;
use App\Models\MarketDay;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StaffingTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private MarketDay $day;

    private User $helper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = Event::factory()->create(['name' => 'Martinsmarkt']);
        $this->day = MarketDay::factory()->for($this->event)->create(['date' => today()->addDays(3)]);
        $this->helper = User::factory()->create(['name' => 'Samuel']);
    }

    public function test_team_page_needs_login(): void
    {
        $this->get('/team/einsaetze')->assertRedirect('/team/login');
    }

    public function test_team_member_enters_and_changes_availability(): void
    {
        $this->actingAs($this->helper)
            ->put(route('team.shifts.availability', $this->day->id), ['from' => '11:00', 'until' => '17:00', 'note' => 'mit Sohn ab 15'])
            ->assertSessionHasNoErrors();
        $this->put(route('team.shifts.availability', $this->day->id), ['from' => '12:00', 'until' => '16:00']);

        $availability = Availability::query()->sole();
        $this->assertSame(['12:00', '16:00', null], [substr($availability->available_from, 0, 5), substr($availability->available_until, 0, 5), $availability->note]);

        $this->get('/team/einsaetze')->assertInertia(fn ($page) => $page
            ->component('Team/Shifts')
            ->where('days.0.event', 'Martinsmarkt')
            ->where('days.0.availability.from', '12:00')
            ->where('days.0.payout', null));

        $this->delete(route('team.shifts.availability.destroy', $this->day->id));
        $this->assertSame(0, Availability::query()->count());
    }

    public function test_end_must_be_after_start_and_past_days_are_closed(): void
    {
        $this->actingAs($this->helper)
            ->put(route('team.shifts.availability', $this->day->id), ['from' => '17:00', 'until' => '11:00'])
            ->assertSessionHasErrors('until');

        $past = MarketDay::factory()->for($this->event)->create(['date' => today()->subDay()]);
        $this->put(route('team.shifts.availability', $past->id), ['from' => '10:00', 'until' => '18:00'])->assertSessionHasErrors('from');
        $this->assertSame(0, Availability::query()->count());
    }

    public function test_team_sees_the_plan_and_only_their_own_payout(): void
    {
        $this->day->update(['date' => today()]);
        $timo = User::factory()->create(['name' => 'Timo']);
        Shift::query()->create(['market_day_id' => $this->day->id, 'user_id' => $timo->id, 'starts_at' => '09:00', 'ends_at' => '19:00']);
        Shift::query()->create(['market_day_id' => $this->day->id, 'user_id' => $this->helper->id, 'starts_at' => '11:00', 'ends_at' => '17:00', 'task' => 'Kasse']);
        EventCost::query()->create(['event_id' => $this->event->id, 'label' => 'Stand', 'amount_cents' => 1000]);

        $this->actingAs($this->helper)->get('/team/einsaetze')->assertInertia(fn ($page) => $page
            ->where('days.0.team.0.name', 'Timo')
            ->where('days.0.team.1.mine', true)
            ->where('days.0.team.1.task', 'Kasse')
            ->where('days.0.payout.hours', 6)
            ->where('days.0.payout.cents', 0)
            ->where('days.0.payout.settled', false)
            ->missing('days.0.profit'));
    }

    public function test_invitation_link_creates_a_team_member_once(): void
    {
        [$invitation, $token] = Invitation::issue('Luca', UserRole::Staff, null);

        $this->get(route('team.invitation', $token))->assertInertia(fn ($page) => $page->component('Team/Invitation')->where('valid', true)->where('name', 'Luca'));
        $this->post(route('team.invitation', $token), [
            'name' => 'Luca Rossi', 'email' => 'luca@example.com', 'password' => 'geheim123', 'password_confirmation' => 'geheim123',
        ])->assertRedirect(route('team.shifts'));

        $user = User::query()->where('email', 'luca@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::Staff, $user->role);
        $this->assertNotNull($invitation->refresh()->accepted_at);

        auth()->logout();
        $this->get(route('team.invitation', $token))->assertInertia(fn ($page) => $page->where('valid', false));
        $this->post(route('team.invitation', $token), [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'geheim123', 'password_confirmation' => 'geheim123',
        ])->assertSessionHasErrors('email');
        $this->assertSame(0, User::query()->where('email', 'x@example.com')->count());
    }

    public function test_expired_invitation_is_refused(): void
    {
        [, $token] = Invitation::issue('Luca', UserRole::Staff, null);
        $this->travel(Invitation::VALID_DAYS + 1)->days();

        $this->get(route('team.invitation', $token))->assertInertia(fn ($page) => $page->where('valid', false));
    }

    public function test_admin_invites_from_the_user_list(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListUsers::class)
            ->callAction('invite', ['name' => 'Luca', 'role' => 'staff'])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertSame('Luca', Invitation::query()->sole()->name);
    }

    public function test_admin_staffing_pages_render(): void
    {
        Availability::query()->create(['market_day_id' => $this->day->id, 'user_id' => $this->helper->id, 'available_from' => '11:00', 'available_until' => '17:00']);
        Shift::query()->create(['market_day_id' => $this->day->id, 'user_id' => $this->helper->id, 'starts_at' => '11:00', 'ends_at' => '17:00']);
        $this->actingAs(User::factory()->admin()->create());

        foreach (['/team/admin/market-days', "/team/admin/market-days/{$this->day->id}/edit", "/team/admin/market-days/{$this->day->id}/abrechnung"] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get("/team/admin/market-days/{$this->day->id}/abrechnung")->assertSee('Samuel');
    }

    public function test_staff_cannot_open_staffing_admin(): void
    {
        $this->actingAs($this->helper)->get("/team/admin/market-days/{$this->day->id}/abrechnung")->assertForbidden();
    }
}
