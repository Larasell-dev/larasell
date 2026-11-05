<?php

namespace Larasell\Larasell\Seo\Fixtures;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Events\OrderPaid;

final class WildcardListener
{
    public function register(Dispatcher $events): void
    {
        Event::listen('eloquent.saved: *', fn () => null);

        foreach (['saved', 'deleting'] as $event) {
            $events->listen("eloquent.{$event}: *", fn () => null);
        }

        // Fine: one model's event, a domain event, and a dynamic name the rule cannot prove.
        Event::listen('eloquent.saved: '.Product::class, fn () => null);
        Event::listen(OrderPaid::class, fn () => null);
        $events->listen($this->name(), fn () => null);
    }

    private function name(): string
    {
        return 'eloquent.saved: *';
    }
}
