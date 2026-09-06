<x-mail::message>
# Your order has been fulfilled

Hello {{ $order['customerName'] }},

Order {{ $order['number'] }} has been fulfilled.

@include('mail.orders.summary')

<x-mail::button :url="$order['url']">
View order
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
