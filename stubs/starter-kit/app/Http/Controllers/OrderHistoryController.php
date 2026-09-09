<?php

namespace App\Http\Controllers;

use App\Inertia\OrderDetails;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Contracts\StorefrontUser;
use Larasell\Larasell\Models\Order;

class OrderHistoryController extends Controller
{
    public function index(Request $request, OrderDetails $orderDetails): Response
    {
        $user = $request->user();
        $customer = $user instanceof StorefrontUser ? $user->customer : null;

        $orders = $customer === null
            ? collect()
            : $customer->orders()->latest('id')->get();

        return Inertia::render('Orders/Index', [
            'orders' => $orders->map(fn (Order $order): array => $orderDetails->summary($order))->all(),
        ]);
    }

    public function show(Request $request, string $publicId, OrderDetails $orderDetails): Response
    {
        $user = $request->user();
        $customer = $user instanceof StorefrontUser ? $user->customer : null;

        if ($customer === null) {
            abort(404);
        }

        $order = $customer->orders()
            ->where('public_id', $publicId)
            ->firstOrFail();

        return Inertia::render('Orders/Show', [
            'order' => $orderDetails->toArray($order),
        ]);
    }
}
