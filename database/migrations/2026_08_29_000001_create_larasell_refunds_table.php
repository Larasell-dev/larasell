<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('larasell_payments')->cascadeOnDelete();
            $table->string('provider');
            $table->string('reference')->nullable();
            $table->string('status')->index();
            $table->json('amount');
            $table->text('failure_message')->nullable();
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_refunds');
    }
};
