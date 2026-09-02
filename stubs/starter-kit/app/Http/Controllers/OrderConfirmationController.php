<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\OrderItem;

class OrderConfirmationController extends Controller
{
    public function __invoke(string $publicId): Response
    {
        $order = Order::query()
            ->with('items')
            ->where('public_id', $publicId)
            ->firstOrFail();

        return Inertia::render('OrderConfirmation', [
            'order' => [
                'number' => $order->number,
                'customerEmail' => $order->customer_email,
                'customerName' => $order->customer_name,
                'currency' => $order->currency->value,
                'status' => $order->status->value,
                'subtotal' => $order->subtotal->toArray(),
                'total' => $order->total->toArray(),
                'items' => $order->items->map(fn (OrderItem $item): array => [
                    'id' => $item->getKey(),
                    'name' => $item->product_name->get(),
                    'quantity' => $item->quantity,
                    'unitPrice' => $item->unit_price->toArray(),
                    'total' => $item->total->toArray(),
                ])->all(),
            ],
        ]);
    }
}
