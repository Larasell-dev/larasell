<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('larasell_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('product_name');
            $table->string('product_slug')->nullable();
            $table->json('unit_price');
            $table->unsignedInteger('quantity');
            $table->json('total');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_order_items');
    }
};
