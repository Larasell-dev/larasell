<?php

namespace Larasell\Larasell\SalesChannels;

use Larasell\Larasell\SalesChannels\Casts\SalesContextCast;
use LogicException;

/**
 * For records made in a sales context, such as carts and orders: they store it in sales_channel_id,
 * locale and currency, readable as $model->sales_context. Create them with createIn(), which takes
 * the context explicitly; a record never picks one up from whatever context happens to be current.
 *
 * @property SalesContext $sales_context
 * @property int $sales_channel_id
 * @property string $locale
 */
trait HasSalesContext
{
    /**
     * Creates a record in the context. A currency given in the attributes wins over the context's,
     * e.g. for a cart in another currency the channel also takes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createIn(SalesContext $context, array $attributes = []): static
    {
        return static::query()->create(['sales_context' => $context, ...$attributes]);
    }

    public static function bootHasSalesContext(): void
    {
        static::creating(function (self $model): void {
            if ($model->getAttribute('sales_channel_id') === null || $model->getAttribute('locale') === null) {
                throw new LogicException('A new ['.$model::class.'] needs a sales context: create it with '.class_basename($model).'::createIn($context, [...]).');
            }
        });
    }

    public function initializeHasSalesContext(): void
    {
        $this->mergeCasts(['sales_context' => SalesContextCast::class]);
    }
}
