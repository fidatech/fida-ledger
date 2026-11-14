<?php

namespace Tests\Unit;

use App\Support\Dec;
use PHPUnit\Framework\TestCase;

class DecTest extends TestCase
{
    public function test_it_adds_without_float_errors(): void
    {
        $this->assertSame('0.3000', Dec::add('0.1', '0.2'));
        $this->assertSame('0.00', Dec::sub('100.10', '100.10', 2));
    }

    public function test_it_rounds_half_away_from_zero(): void
    {
        $this->assertSame('1.01', Dec::round('1.005', 2));
        $this->assertSame('-1.01', Dec::round('-1.005', 2));
        $this->assertSame('1.00', Dec::round('1.0049', 2));
        $this->assertSame('0.00', Dec::round('-0.001', 2));
    }

    public function test_it_multiplies_an_amount_by_a_rate(): void
    {
        $this->assertSame('7150.0000', Dec::mul('100', '71.5'));
        $this->assertSame('0.3333', Dec::div('1', '3'));
    }

    public function test_it_formats_for_people(): void
    {
        $this->assertSame('1,234,567.50', Dec::format('1234567.5'));
        $this->assertSame('-1,000.00', Dec::format('-1000'));
        $this->assertSame('999.99', Dec::format('999.994'));
    }

    public function test_it_reads_user_input(): void
    {
        $this->assertSame('12500.75', Dec::of('12,500.75'));
        $this->assertSame('0', Dec::of(''));
        $this->assertFalse(Dec::valid('12a'));
        $this->assertTrue(Dec::valid('-3.5'));
    }
}
