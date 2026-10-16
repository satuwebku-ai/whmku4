<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Setting;
use App\Support\ThemeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Layout toko ditentukan DOMAIN: domain client (keranjang, checkout) = layout portal
 * client dalam mode store, untuk klien login maupun tamu; domain publik (etalase)
 * = layout publik.
 */
class StoreLayoutSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_get_the_store_layout_on_the_client_domain(): void
    {
        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('store-topbar', false)
            ->assertSee(route('client.login'), false)
            ->assertSee(route('client.register'), false);
    }

    public function test_logged_in_clients_get_the_store_layout_with_dashboard_and_account(): void
    {
        $client = Client::factory()->create();
        $this->actingAs($client, 'client');

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('store-topbar', false)
            ->assertSee(route('client.dashboard'), false)
            ->assertSee('alt="'.e($client->name).'"', false);
    }

    public function test_the_store_layout_replaces_the_dashboard_chrome(): void
    {
        $this->actingAs(Client::factory()->create(), 'client');

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertDontSee('id="clientTopbar"', false)
            ->assertDontSee('Layanan Saya');
    }

    public function test_store_pages_on_the_public_domain_keep_the_public_layout(): void
    {
        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertDontSee('store-topbar', false);
    }

    public function test_cart_views_come_from_the_client_theme_when_themes_differ(): void
    {
        Setting::put('public_template', 'namahost');
        Setting::put('client_template', 'default');

        foreach ([false, true] as $loggedIn) {
            if ($loggedIn) {
                $this->actingAs(Client::factory()->create(), 'client');
            }

            $this->get(route('cart.index'))->assertOk();

            $finder = app('view')->getFinder();
            $this->assertStringContainsString('public-themes/default/', $finder->find('public.cart.index'));
            $this->assertStringContainsString('public-themes/default/', $finder->find('public.store-layout'));
        }
    }

    public function test_public_browsing_keeps_the_public_theme_when_themes_differ(): void
    {
        Setting::put('public_template', 'namahost');
        Setting::put('client_template', 'default');

        $this->get(route('catalog.index'))->assertOk();

        $this->assertStringContainsString('public-themes/namahost/', app('view')->getFinder()->find('public.catalog.index'));
    }

    public function test_unknown_theme_keys_fall_back_to_default(): void
    {
        Setting::put('public_template', 'tema-yang-sudah-dihapus');

        $this->assertSame('default', ThemeRegistry::active('public'));
    }

    public function test_every_store_view_of_every_public_theme_uses_the_store_layout(): void
    {
        foreach (array_keys(ThemeRegistry::available('public')) as $theme) {
            foreach (['catalog', 'cart', 'licenses'] as $dir) {
                foreach (glob(resource_path("views/themes/public-themes/{$theme}/public/{$dir}/*.blade.php")) as $file) {
                    if (! str_contains((string) file_get_contents($file), '@extends')) {
                        continue; // partial (mis. _product-card)
                    }

                    $this->assertStringContainsString("@extends('public.store-layout')", file_get_contents($file), $file);
                    $this->assertStringNotContainsString("@extends('public.layout')", file_get_contents($file), $file);
                }
            }
        }
    }
}
