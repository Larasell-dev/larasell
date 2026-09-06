<x-mail::message>
# Payment received

Hello {{ $order['customerName'] }},

We've received your payment for order {{ $order['number'] }}. We'll email you again when your order has been fulfilled.

@include('mail.orders.summary')

<x-mail::button :url="$order['url']">
View order
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
