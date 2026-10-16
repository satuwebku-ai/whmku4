<?php

namespace Tests\Feature;

use App\Support\ClientMenu;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ClientMenuTest extends TestCase
{
    public function test_every_menu_item_points_to_an_existing_route(): void
    {
        foreach (['fa', 'bi'] as $set) {
            foreach (ClientMenu::items($set) as $item) {
                $this->assertTrue(Route::has($item['route']), "Route {$item['route']} tidak ada ({$item['label']})");
                $this->assertNotEmpty($item['icon'], $item['label']);
            }
        }
    }

    public function test_both_icon_sets_have_the_same_items_in_the_same_order(): void
    {
        $labels = fn (string $set) => array_column(ClientMenu::items($set), 'label');

        $this->assertSame($labels('fa'), $labels('bi'));
    }

    public function test_only_the_store_item_carries_the_cart_badge(): void
    {
        $withBadge = array_filter(ClientMenu::items('bi', 4), fn ($i) => array_key_exists('badge', $i));

        $this->assertCount(1, $withBadge);
        $this->assertSame('store.index', array_values($withBadge)[0]['route']);
        $this->assertSame(4, array_values($withBadge)[0]['badge']);
    }

    public function test_store_entries_are_part_of_the_menu(): void
    {
        $routes = array_column(ClientMenu::items(), 'route');

        $this->assertContains('store.index', $routes);
        $this->assertContains('client.licenses', $routes);

        // Menu jualan tidak lagi berdiri sendiri; semuanya lewat satu menu Store.
        foreach (['catalog.index', 'catalog.vps', 'domain.search', 'license.index', 'cart.index'] as $route) {
            $this->assertNotContains($route, $routes);
        }
    }

    public function test_client_layouts_use_the_shared_menu_instead_of_their_own_copy(): void
    {
        foreach (glob(resource_path('views/themes/client-themes/*/client/layout.blade.php')) as $layout) {
            $source = file_get_contents($layout);

            $this->assertStringContainsString('ClientMenu::items(', $source, $layout);
            $this->assertStringNotContainsString("'route' => 'client.dashboard'", $source, $layout);
        }
    }

    public function test_store_menu_is_active_on_the_store_and_the_pages_under_it(): void
    {
        $store = collect(ClientMenu::items())->firstWhere('route', 'store.index');

        foreach (['store.index', 'cart.index', 'license.index', 'domain.search', 'catalog.vps'] as $route) {
            $this->assertTrue(
                collect((array) $store['match'])->contains(fn ($p) => \Illuminate\Support\Str::is($p, $route)),
                "Menu Store harus aktif di $route"
            );
        }
    }
}
