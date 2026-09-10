<?php

namespace Larasell\Larasell\Carts;

use Larasell\Larasell\Discounts\PromotionManager;

final readonly class CartMergeAttributePlan
{
    /**
     * @param  list<string>  $promotionCodes
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public array $promotionCodes,
        public ?string $preferredShippingOption,
        public ?string $fallbackShippingOption,
        public array $metadata,
    ) {}

    public static function preferDestination(CartMergeContext $context): self
    {
        return new self(
            promotionCodes: self::uniqueCodes(
                $context->destination->promotionCodes(),
                $context->source->promotionCodes(),
            ),
            preferredShippingOption: $context->destination->shipping_option,
            fallbackShippingOption: $context->source->shipping_option,
            metadata: [
                ...$context->source->metadata->all(),
                ...$context->destination->metadata->all(),
            ],
        );
    }

    public static function preferSource(CartMergeContext $context): self
    {
        return new self(
            promotionCodes: self::uniqueCodes(
                $context->source->promotionCodes(),
                $context->destination->promotionCodes(),
            ),
            preferredShippingOption: $context->source->shipping_option,
            fallbackShippingOption: $context->destination->shipping_option,
            metadata: [
                ...$context->destination->metadata->all(),
                ...$context->source->metadata->all(),
            ],
        );
    }

    /**
     * @param  array<int, string>  ...$groups
     * @return list<string>
     */
    private static function uniqueCodes(array ...$groups): array
    {
        $codes = [];

        foreach ($groups as $group) {
            foreach ($group as $code) {
                $code = PromotionManager::normalizeCode($code);

                if ($code !== '' && ! in_array($code, $codes, true)) {
                    $codes[] = $code;
                }
            }
        }

        return $codes;
    }
}
