<x-mail::message>
# Your order has been cancelled

Hello {{ $order['customerName'] }},

Order {{ $order['number'] }} has been cancelled.

@if ($order['cancellationMessage'])
{{ $order['cancellationMessage'] }}
@endif

@include('mail.orders.summary')

<x-mail::button :url="$order['url']">
View order
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
