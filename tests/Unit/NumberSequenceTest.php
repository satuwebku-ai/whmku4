<?php

namespace Tests\Unit;

use App\Models\NumberSequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sequence_is_monotonic_and_unique(): void
    {
        $this->assertSame(1001, NumberSequence::next('orders', 1001));
        $this->assertSame(1002, NumberSequence::next('orders', 1001));
        $this->assertSame(1003, NumberSequence::next('orders', 1001));
    }

    public function test_invoice_sequence_is_independent_per_year(): void
    {
        $this->assertSame(1, NumberSequence::next('invoices:2030', 1));
        $this->assertSame(2, NumberSequence::next('invoices:2030', 1));
        $this->assertSame(1, NumberSequence::next('invoices:2031', 1));
    }
}
