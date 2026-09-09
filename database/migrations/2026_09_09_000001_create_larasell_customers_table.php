<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->timestamps();
        });

        Schema::create('larasell_customer_user', function (Blueprint $table) {
            $table->foreignId('customer_id')->constrained('larasell_customers')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->primary(['customer_id', 'user_id']);
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_customer_user');
        Schema::dropIfExists('larasell_customers');
    }
};
