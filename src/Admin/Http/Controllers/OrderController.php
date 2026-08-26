<?php

namespace Larasell\Larasell\Admin\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Models\ModelRegistry;

class OrderController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var class-string<Model> $orderModel */
        $orderModel = app(ModelRegistry::class)->order->class();
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));

        $orders = $orderModel::query()
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Model $order): array => [
                'id' => $order->getKey(),
                'number' => $order->getAttribute('number'),
                'customerEmail' => $order->getAttribute('customer_email'),
                'currency' => $order->getAttribute('currency')->value,
                'status' => $order->getAttribute('status')->value,
                'total' => $order->getAttribute('total')->toArray(),
                'createdAt' => $order->getAttribute('created_at')->toIso8601String(),
                'url' => route('larasell.admin.orders.show', $order->getKey()),
            ]);

        return Inertia::render('Orders/Index', [
            'homeUrl' => route('larasell.admin.home'),
            'mediaUrl' => route('larasell.admin.media.index'),
            'ordersUrl' => route('larasell.admin.orders.index'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productOptionsUrl' => route('larasell.admin.product-options.index'),
            'settingsUrl' => route('larasell.admin.settings.index'),
            'logoutUrl' => route('larasell.admin.logout'),
            'user' => [
                'name' => $admin->name,
                'email' => $admin->email,
            ],
            'orders' => $orders->items(),
            'pagination' => [
                'currentPage' => $orders->currentPage(),
                'from' => $orders->firstItem(),
                'lastPage' => $orders->lastPage(),
                'nextUrl' => $orders->nextPageUrl(),
                'previousUrl' => $orders->previousPageUrl(),
                'to' => $orders->lastItem(),
                'total' => $orders->total(),
            ],
        ])->rootView('larasell-admin::admin');
    }

    public function show(Request $request, string $adminOrder): Response
    {
        /** @var class-string<Model> $orderModel */
        $orderModel = app(ModelRegistry::class)->order->class();
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));
        $order = $orderModel::query()->with(['items', 'payments'])->findOrFail($adminOrder);

        return Inertia::render('Orders/Show', [
            'homeUrl' => route('larasell.admin.home'),
            'mediaUrl' => route('larasell.admin.media.index'),
            'ordersUrl' => route('larasell.admin.orders.index'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productOptionsUrl' => route('larasell.admin.product-options.index'),
            'settingsUrl' => route('larasell.admin.settings.index'),
            'logoutUrl' => route('larasell.admin.logout'),
            'user' => [
                'name' => $admin->name,
                'email' => $admin->email,
            ],
            'order' => [
                'id' => $order->getKey(),
                'number' => $order->getAttribute('number'),
                'customerEmail' => $order->getAttribute('customer_email'),
                'customerName' => $order->getAttribute('customer_name'),
                'currency' => $order->getAttribute('currency')->value,
                'status' => $order->getAttribute('status')->value,
                'subtotal' => $order->getAttribute('subtotal')->toArray(),
                'total' => $order->getAttribute('total')->toArray(),
                'billingAddress' => $order->getAttribute('billing_address')?->toArray(),
                'shippingAddress' => $order->getAttribute('shipping_address')?->toArray(),
                'createdAt' => $order->getAttribute('created_at')->toIso8601String(),
                'items' => $order->getRelation('items')->map(fn (Model $item): array => [
                    'id' => $item->getKey(),
                    'name' => $item->getAttribute('product_name'),
                    'slug' => $item->getAttribute('product_slug'),
                    'unitPrice' => $item->getAttribute('unit_price')->toArray(),
                    'quantity' => $item->getAttribute('quantity'),
                    'total' => $item->getAttribute('total')->toArray(),
                ])->all(),
                'payments' => $order->getRelation('payments')->map(fn (Model $payment): array => [
                    'id' => $payment->getKey(),
                    'provider' => $payment->getAttribute('provider'),
                    'reference' => $payment->getAttribute('reference'),
                    'status' => $payment->getAttribute('status')->value,
                    'amount' => $payment->getAttribute('amount')->toArray(),
                    'failureMessage' => $payment->getAttribute('failure_message'),
                    'createdAt' => $payment->getAttribute('created_at')->toIso8601String(),
                ])->all(),
            ],
        ])->rootView('larasell-admin::admin');
    }
}
