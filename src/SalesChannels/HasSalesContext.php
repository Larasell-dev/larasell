<?php

namespace Larasell\Larasell\SalesChannels;

use Larasell\Larasell\SalesChannels\Casts\SalesContextCast;
use LogicException;

/**
 * For records made in a sales context, such as carts and orders: they store it in sales_channel_id,
 * locale and currency, readable as $model->sales_context. A new record without them
 * takes the current context (a storefront request or a job it queued); without one it cannot be saved.
 *
 * @property SalesContext $sales_context
 * @property int $sales_channel_id
 * @property string $locale
 */
trait HasSalesContext
{
    public static function bootHasSalesContext(): void
    {
        static::creating(function (self $model): void {
            if ($model->getAttribute('sales_channel_id') !== null && $model->getAttribute('locale') !== null) {
                return;
            }

            $context = SalesContext::current()
                ?? throw new LogicException('A new ['.$model::class.'] needs a sales context: create it in a storefront request, or set sales_context, e.g. SalesContext::for(\'default\', \'en\').');

            // A currency given explicitly stays; the rest comes from the context.
            $currency = $model->getAttribute('currency');
            $model->sales_context = $context;

            if ($currency !== null) {
                $model->setAttribute('currency', $currency);
            }
        });
    }

    public function initializeHasSalesContext(): void
    {
        $this->mergeCasts(['sales_context' => SalesContextCast::class]);
    }
}
