<?php

declare(strict_types=1);

namespace tests\Unit\Payment;

use core\payment\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use tests\TestCase;

final class MoneyTest extends TestCase
{
    /** @return array<string, array{string|int, int}> */
    public static function validAmounts(): array
    {
        return [
            'zero string'            => ['0', 0],
            'one cent'               => ['0.01', 1],
            'ten cents one decimal'  => ['0.1', 10],
            'integer string'         => ['12', 1200],
            'one decimal'            => ['1.2', 120],
            'two decimals'           => ['12.30', 1230],
            'recharge upper bound'   => ['10000', 1000000],
            'recharge upper decimal' => ['10000.00', 1000000],
            'leading zero'           => ['05', 500],
            'leading zero decimal'   => ['007.50', 750],
            'fifteen digits'         => ['999999999999999.99', 99999999999999999],
            'int zero'               => [0, 0],
            'int value'              => [12, 1200],
            'int large'              => [intdiv(PHP_INT_MAX, 100), intdiv(PHP_INT_MAX, 100) * 100],
        ];
    }

    #[DataProvider('validAmounts')]
    public function test_to_cents_accepts_valid_amounts(string|int $yuan, int $expected): void
    {
        $this->assertSame($expected, Money::toCents($yuan));
    }

    /** @return array<string, array{string|int}> */
    public static function invalidAmounts(): array
    {
        return [
            'three decimals'    => ['1.234'],
            'negative string'   => ['-1'],
            'negative decimal'  => ['-0.01'],
            'plus sign'         => ['+1'],
            'letters'           => ['abc'],
            'empty'             => [''],
            'leading space'     => [' 1'],
            'trailing space'    => ['1 '],
            'trailing newline'  => ["1\n"],
            'trailing dot'      => ['1.'],
            'leading dot'       => ['.5'],
            'exponent'          => ['1e2'],
            'thousands comma'   => ['1,000'],
            'double dot'        => ['1.2.3'],
            'hex'               => ['0x10'],
            'sixteen digits'    => ['1000000000000000'],
            'negative int'      => [-1],
            'int overflow'      => [intdiv(PHP_INT_MAX, 100) + 1],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_to_cents_rejects_invalid_amounts(string|int $yuan): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::toCents($yuan);
    }

    /** @return array<string, array{int, string}> */
    public static function centsToYuan(): array
    {
        return [
            'zero'        => [0, '0.00'],
            'one cent'    => [1, '0.01'],
            'ten cents'   => [10, '0.10'],
            'whole'       => [1200, '12.00'],
            'mixed'       => [1230, '12.30'],
            'no grouping' => [100000000, '1000000.00'],
            'large'       => [99999999999999999, '999999999999999.99'],
            'int max'     => [PHP_INT_MAX, intdiv(PHP_INT_MAX, 100) . '.' . str_pad((string) (PHP_INT_MAX % 100), 2, '0', STR_PAD_LEFT)],
        ];
    }

    #[DataProvider('centsToYuan')]
    public function test_to_yuan_formats_two_decimals(int $cents, string $expected): void
    {
        $this->assertSame($expected, Money::toYuan($cents));
    }

    public function test_to_yuan_rejects_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::toYuan(-1);
    }

    public function test_round_trip_is_exact(): void
    {
        foreach (['0.01', '0.10', '1.00', '12.34', '9999.99', '10000.00'] as $yuan) {
            $this->assertSame($yuan, Money::toYuan(Money::toCents($yuan)));
        }
    }
}
