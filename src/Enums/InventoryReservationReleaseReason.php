<?php

namespace Larasell\Larasell\Enums;

enum InventoryReservationReleaseReason: string
{
    case OrderCancelled = 'order_cancelled';
    case ReservationExpired = 'reservation_expired';
}
