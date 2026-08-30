<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_promotion_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('larasell_orders')->cascadeOnDelete();
            $table->string('promotion_identifier')->index();
            $table->string('customer_identifier')->nullable()->index();
            $table->string('status')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->unique(['promotion_identifier', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_promotion_redemptions');
    }
};
