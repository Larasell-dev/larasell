<?php

namespace App\Http\Controllers;

use App\Inertia\OrderDetails;
use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Models\Order;

class OrderController extends Controller
{
    public function show(string $publicId, OrderDetails $orderDetails): Response
    {
        $order = Order::query()
            ->where('public_id', $publicId)
            ->firstOrFail();

        return Inertia::render('OrderConfirmation', [
            'order' => $orderDetails->toArray($order),
        ]);
    }
}
