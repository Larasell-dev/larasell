<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('larasell_orders')->cascadeOnDelete();
            $table->string('provider');
            $table->string('reference')->nullable()->index();
            $table->string('status')->index();
            $table->json('amount');
            $table->text('failure_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_payments');
    }
};
