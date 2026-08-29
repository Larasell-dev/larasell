---
title: Inventory
description: Reserve, consume, release, and expire product inventory.
---

# Inventory

Larasell deducts tracked stock during checkout and records an inventory
reservation for each affected order item. A successful payment consumes the
reservation. Cancelling the order releases the reservation and restores stock
unless cancellation explicitly disables restocking.

## Expired reservations

Reservation expiration is processed by this command:

```bash
php artisan larasell:release-expired-inventory
```

The command is not scheduled by the package. Add it to the application
scheduler so the application controls how often cleanup runs:

```php [routes/console.php]
use Illuminate\Support\Facades\Schedule;

Schedule::command('larasell:release-expired-inventory')
    ->everyMinute()
    ->withoutOverlapping();
```

The application must also run Laravel's scheduler in production. The command
accepts a configurable query batch size when larger stores need it:

```bash
php artisan larasell:release-expired-inventory --batch-size=250
```

When an active reservation expires, the command safely cancels its unpaid
order, cancels pending payments, releases all active reservations belonging to
the order, and restores their stock. Paid orders are not released.

## Events

Reservation lifecycle events are dispatched after their database transaction
commits:

- `Larasell\Larasell\Events\InventoryReserved`
- `Larasell\Larasell\Events\InventoryReservationConsumed`
- `Larasell\Larasell\Events\InventoryReservationReleased`
- `Larasell\Larasell\Events\InventoryReservationExpired`

Each event exposes the affected reservation through its `$reservation`
property. Expiration dispatches both `InventoryReservationReleased` and
`InventoryReservationExpired`. Stock quantity changes continue to dispatch
`InventoryDecremented` and `InventoryRestocked`.
