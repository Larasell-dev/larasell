<x-mail::message>
# We've received your order

Hello {{ $order['customerName'] }},

{{ $order['intro'] }}

@include('mail.orders.summary')

<x-mail::button :url="$order['url']">
View order
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
