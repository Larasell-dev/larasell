<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('larasell_order_number_sequences');

        Schema::create('larasell_order_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('number');
            $table->timestamp('created_at');
            $table->unique(['name', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_order_number_sequences');

        Schema::create('larasell_order_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at');
        });
    }
};
