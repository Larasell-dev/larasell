<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_promotion_redemption_counters', function (Blueprint $table) {
            $table->string('promotion_identifier')->primary();
            $table->unsignedInteger('reserved_count')->default(0);
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_promotion_redemption_counters');
    }
};
