<?php

namespace Tests\Feature;

use App\Enums\MenuEdition;
use App\Models\Event;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class GuestMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_shows_items_of_the_active_events_editions_plus_drinks(): void
    {
        Event::factory()->create(['editions' => [MenuEdition::Pizza]]);
        MenuItem::factory()->create(['name' => 'Margherita']);
        MenuItem::factory()->drink()->create();
        MenuItem::factory()->create(['name' => 'Flammkuchen', 'edition' => MenuEdition::Flammkuchen]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Guest/Menu')
                ->has('items', 2)
                ->where('items.0.name', 'Margherita')
                ->where('items.1.name', 'Cola'));
    }

    public function test_menu_without_active_event(): void
    {
        Event::factory()->inactive()->create();

        $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('event', null));
    }
}
