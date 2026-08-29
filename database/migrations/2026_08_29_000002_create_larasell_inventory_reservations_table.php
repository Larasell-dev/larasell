<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('larasell_orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->unique()->constrained('larasell_order_items')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedInteger('quantity');
            $table->string('status')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_inventory_reservations');
    }
};
