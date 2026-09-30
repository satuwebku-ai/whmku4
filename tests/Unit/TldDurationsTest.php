<?php

namespace Tests\Unit;

use App\Support\TldDurations;
use PHPUnit\Framework\TestCase;

class TldDurationsTest extends TestCase
{
    /** Meniru Tld::priceForYears: harga khusus > 0 dipakai, selain itu linier. */
    private function priceFor(float $base, array $overrides = []): \Closure
    {
        return fn (int $years) => (isset($overrides[$years]) && $overrides[$years] > 0)
            ? (float) $overrides[$years]
            : $base * max($years, 1);
    }

    public function test_linear_pricing_offers_1_2_3_5_years_without_saving(): void
    {
        $options = TldDurations::build($this->priceFor(20000), 1, 10);

        $this->assertSame([1, 2, 3, 5], array_keys($options));
        $this->assertSame(60000.0, $options[3]['price']);
        $this->assertSame(0.0, array_sum(array_column($options, 'saving')));
    }

    public function test_multi_year_override_is_reported_as_saving(): void
    {
        $options = TldDurations::build($this->priceFor(20000, [2 => 38000, 5 => 90000]), 1, 10);

        $this->assertSame(2000.0, $options[2]['saving']);
        $this->assertSame(5, $options[2]['percent']);
        $this->assertSame(40000.0, $options[2]['linear']);
        $this->assertSame(10, $options[5]['percent']);
        $this->assertSame(0.0, $options[3]['saving']);
    }

    public function test_override_more_expensive_than_linear_never_shows_as_saving(): void
    {
        $options = TldDurations::build($this->priceFor(20000, [2 => 45000]), 1, 10);

        $this->assertSame(0.0, $options[2]['saving']);
        $this->assertSame(0, $options[2]['percent']);
    }

    public function test_min_and_max_years_limit_the_offered_durations(): void
    {
        $this->assertSame([1, 2, 3], array_keys(TldDurations::build($this->priceFor(54000), 1, 3)));
        $this->assertSame([2, 3, 5], array_keys(TldDurations::build($this->priceFor(54000), 2, 10)));
    }

    public function test_zero_price_yields_no_options(): void
    {
        $this->assertSame([], TldDurations::build(fn (int $years) => 0.0, 1, 10));
    }
}
