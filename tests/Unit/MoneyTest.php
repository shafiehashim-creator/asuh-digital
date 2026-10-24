<?php

use App\Support\Money;

it('formats sen as ringgit', function () {
    expect(Money::format(7900))->toBe('RM79.00')
        ->and(Money::format(12345))->toBe('RM123.45')
        ->and(Money::format(990000))->toBe('RM9,900.00');
});

it('drops empty sen in the short format', function () {
    expect(Money::short(7900))->toBe('RM79')
        ->and(Money::short(7950))->toBe('RM79.50');
});
