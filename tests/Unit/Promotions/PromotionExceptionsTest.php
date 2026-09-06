<?php

use Illuminate\Support\Carbon;
use Larasell\Larasell\Exceptions\Cart\CartException;
use Larasell\Larasell\Exceptions\Promotions\CustomerPromotionRedemptionLimitReachedException;
use Larasell\Larasell\Exceptions\Promotions\InapplicablePromotionCodeException;
use Larasell\Larasell\Exceptions\Promotions\PromotionCodeException;
use Larasell\Larasell\Exceptions\Promotions\PromotionException;
use Larasell\Larasell\Exceptions\Promotions\PromotionRedemptionException;
use Larasell\Larasell\Exceptions\Promotions\PromotionRedemptionIntegrityException;
use Larasell\Larasell\Exceptions\Promotions\PromotionRedemptionLimitReachedException;
use Larasell\Larasell\Exceptions\Promotions\UnavailablePromotionCodeException;
use Larasell\Larasell\Exceptions\Promotions\UnknownPromotionCodeException;

it('groups unknown promotion codes under PromotionCodeException', function () {
    $exception = new UnknownPromotionCodeException('SAVE10');

    expect($exception)->toBeInstanceOf(PromotionCodeException::class)
        ->and($exception)->toBeInstanceOf(PromotionException::class)
        ->and($exception)->not->toBeInstanceOf(PromotionRedemptionException::class)
        ->and($exception)->not->toBeInstanceOf(CartException::class)
        ->and($exception->getMessage())->toBe('Promotion code [SAVE10] is not registered.')
        ->and($exception->promotionCode)->toBe('SAVE10')
        ->and($exception->reason())->toBe('unknown_promotion_code')
        ->and($exception->context())->toBe([
            'reason' => 'unknown_promotion_code',
            'code' => 'SAVE10',
        ]);
});

it('groups inapplicable promotion codes under PromotionCodeException', function () {
    $exception = new InapplicablePromotionCodeException('SAVE10');

    expect($exception)->toBeInstanceOf(PromotionCodeException::class)
        ->and($exception)->toBeInstanceOf(PromotionException::class)
        ->and($exception->getMessage())->toBe('Promotion code [SAVE10] is not applicable to this cart.')
        ->and($exception->promotionCode)->toBe('SAVE10')
        ->and($exception->reason())->toBe('inapplicable_promotion_code')
        ->and($exception->context())->toBe([
            'reason' => 'inapplicable_promotion_code',
            'code' => 'SAVE10',
        ]);
});

it('groups unavailable promotion codes under PromotionCodeException', function () {
    $startsAt = Carbon::parse('2026-09-01 10:00:00');
    $exception = new UnavailablePromotionCodeException('FUTURE', $startsAt);

    expect($exception)->toBeInstanceOf(PromotionCodeException::class)
        ->and($exception)->toBeInstanceOf(PromotionException::class)
        ->and($exception->getMessage())->toBe('Promotion code [FUTURE] is not currently available.')
        ->and($exception->promotionCode)->toBe('FUTURE')
        ->and($exception->startsAt)->toBe($startsAt)
        ->and($exception->endsAt)->toBeNull()
        ->and($exception->reason())->toBe('unavailable_promotion_code')
        ->and($exception->context())->toBe([
            'reason' => 'unavailable_promotion_code',
            'code' => 'FUTURE',
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => null,
        ]);
});

it('groups exhausted global redemption capacity under PromotionRedemptionException', function () {
    $exception = new PromotionRedemptionLimitReachedException('limited-promotion');

    expect($exception)->toBeInstanceOf(PromotionRedemptionException::class)
        ->and($exception)->toBeInstanceOf(PromotionException::class)
        ->and($exception)->not->toBeInstanceOf(PromotionCodeException::class)
        ->and($exception)->not->toBeInstanceOf(CartException::class)
        ->and($exception->getMessage())->toBe('Promotion [limited-promotion] has reached its redemption limit.')
        ->and($exception->identifier)->toBe('limited-promotion')
        ->and($exception->reason())->toBe('redemption_limit_reached')
        ->and($exception->context())->toBe([
            'reason' => 'redemption_limit_reached',
            'identifier' => 'limited-promotion',
        ]);
});

it('groups exhausted customer redemption capacity under PromotionRedemptionException', function () {
    $exception = new CustomerPromotionRedemptionLimitReachedException(
        'limited-promotion',
        'email:redemptions@example.com',
    );

    expect($exception)->toBeInstanceOf(PromotionRedemptionException::class)
        ->and($exception)->toBeInstanceOf(PromotionException::class)
        ->and($exception->getMessage())->toBe('Promotion [limited-promotion] has reached its customer redemption limit.')
        ->and($exception->identifier)->toBe('limited-promotion')
        ->and($exception->customerIdentifier)->toBe('email:redemptions@example.com')
        ->and($exception->reason())->toBe('customer_redemption_limit_reached')
        ->and($exception->context())->toBe([
            'reason' => 'customer_redemption_limit_reached',
            'identifier' => 'limited-promotion',
            'customer_identifier' => 'email:redemptions@example.com',
        ]);
});

it('does not treat corrupted redemption counters as a customer-facing promotion failure', function () {
    $exception = new PromotionRedemptionIntegrityException('limited-promotion');

    expect($exception)->toBeInstanceOf(RuntimeException::class)
        ->and($exception)->not->toBeInstanceOf(PromotionException::class)
        ->and($exception)->not->toBeInstanceOf(CartException::class)
        ->and($exception->getMessage())->toBe('Promotion [limited-promotion] has inconsistent redemption capacity.')
        ->and($exception->identifier)->toBe('limited-promotion');
});
