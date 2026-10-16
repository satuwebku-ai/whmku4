<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Route serah-terima publik -> client dibatasi 30 permintaan per menit per IP. */
class CartHandoffThrottleTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'Terlalu banyak permintaan. Coba lagi sebentar lagi.';

    private function attempt(): \Illuminate\Testing\TestResponse
    {
        // Input kosong: ditolak validasi, tapi tetap dihitung oleh pembatas.
        return $this->post(route('cart.add-premium-domain'), [], ['Origin' => 'https://'.config('portal_domains.public')]);
    }

    public function test_requests_within_the_limit_are_not_throttled(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->attempt()->assertSessionMissing('error', self::MESSAGE);
        }
    }

    public function test_the_31st_request_in_a_minute_is_redirected_to_the_cart_with_a_clear_message(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->attempt();
        }

        $this->attempt()
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', self::MESSAGE);
    }

    public function test_requests_from_a_wrong_origin_also_count_toward_the_limit(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->post(route('cart.add-product'), [], ['Origin' => 'https://situs-lain.example']);
        }

        $this->attempt()->assertSessionHas('error', self::MESSAGE);
    }

    public function test_the_cart_itself_is_not_throttled_by_the_handoff_limit(): void
    {
        for ($i = 0; $i < 35; $i++) {
            $this->get(route('cart.index'))->assertOk();
        }
    }
}
