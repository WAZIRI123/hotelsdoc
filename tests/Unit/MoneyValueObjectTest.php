<?php

declare(strict_types=1);

use App\Support\Money;

it('builds from minor units', function (): void {
    $m = Money::fromMinor(150_000);

    expect($m->amount)->toBe(150_000)
        ->and($m->currency)->toBe('TZS')
        ->and($m->toMajor())->toBe(1500.0);
});

it('builds from major units', function (): void {
    expect(Money::fromMajor(150_000)->amount)->toBe(15_000_000);
});

it('adds and subtracts within same currency', function (): void {
    $a = Money::fromMajor(100);
    $b = Money::fromMajor(40);

    expect($a->add($b)->toMajor())->toBe(140.0)
        ->and($a->subtract($b)->toMajor())->toBe(60.0);
});

it('refuses to mix currencies', function (): void {
    Money::fromMajor(100, 'TZS')->add(new Money(50_00, 'USD'));
})->throws(InvalidArgumentException::class);

it('formats with currency prefix', function (): void {
    expect(Money::fromMajor(150_000)->format())->toBe('TZS 150,000');
});

it('reports sign correctly', function (): void {
    expect(Money::zero()->isZero())->toBeTrue()
        ->and(Money::fromMinor(1)->isPositive())->toBeTrue()
        ->and(Money::fromMinor(-1)->isNegative())->toBeTrue();
});
