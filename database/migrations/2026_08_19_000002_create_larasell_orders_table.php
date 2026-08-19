<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Larasell\Larasell\Enums\OrderStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('customer_email');
            $table->string('customer_name');
            $table->json('billing_address');
            $table->json('shipping_address');
            $table->string('status')->default(OrderStatus::PendingPayment->value)->index();
            $table->json('subtotal');
            $table->json('total');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_orders');
    }
};
