<?php

namespace Larasell\Larasell\Enums;

enum InventoryReservationStatus: string
{
    case Active = 'active';
    case Consumed = 'consumed';
    case Released = 'released';
}
