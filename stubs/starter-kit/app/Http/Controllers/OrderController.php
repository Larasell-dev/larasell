<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\OrderItem;
use Larasell\Larasell\Price;

class OrderController extends Controller
{
    public function show(string $publicId): Response
    {
        $order = Order::query()
            ->with('items')
            ->where('public_id', $publicId)
            ->firstOrFail();

        $locale = App::currentLocale();

        return Inertia::render('OrderConfirmation', [
            'order' => [
                'number' => $order->number,
                'customerEmail' => $order->customer_email,
                'customerName' => $order->customer_name,
                'status' => $order->status->value,
                'subtotal' => Price::format($order->subtotal, $order->currency, $locale),
                'total' => Price::format($order->total, $order->currency, $locale),
                'items' => $order->items->map(fn (OrderItem $item): array => [
                    'id' => $item->getKey(),
                    'name' => $item->product_name->get(),
                    'quantity' => $item->quantity,
                    'unitPrice' => Price::format($item->unit_price, $order->currency, $locale),
                    'total' => Price::format($item->total, $order->currency, $locale),
                ])->all(),
            ],
        ]);
    }
}
