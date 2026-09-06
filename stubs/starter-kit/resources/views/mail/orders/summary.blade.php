**Order number:** {{ $order['number'] }}

@foreach ($order['items'] as $item)
- {{ $item['line'] }}
@endforeach

**Subtotal:** {{ $order['subtotal'] }}
@foreach ($order['discounts'] as $discount)
**{{ $discount['label'] }}:** −{{ $discount['total'] }}
@endforeach
@if ($order['shipping'])
**Shipping:** {{ $order['shipping'] }}
@endif
@if ($order['tax'])
**Tax:** {{ $order['tax'] }}
@endif
**Total:** {{ $order['total'] }}

@if ($order['address'])
**Address**

@foreach ($order['address'] as $line)
{{ $line }}

@endforeach
@else
@if ($order['shippingAddress'])
**Shipping address**

@foreach ($order['shippingAddress'] as $line)
{{ $line }}

@endforeach
@endif
@if ($order['billingAddress'])
**Billing address**

@foreach ($order['billingAddress'] as $line)
{{ $line }}

@endforeach
@endif
@endif
