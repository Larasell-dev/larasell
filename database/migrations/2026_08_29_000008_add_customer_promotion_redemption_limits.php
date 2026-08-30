<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_promotion_redemptions', function (Blueprint $table) {
            $table->unsignedInteger('global_limit')->nullable();
            $table->unsignedInteger('customer_limit')->nullable();
        });

        Schema::create('larasell_promotion_customer_redemption_counters', function (Blueprint $table) {
            $table->string('promotion_identifier');
            $table->string('customer_identifier');
            $table->unsignedInteger('reserved_count')->default(0);
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->timestamps();

            $table->primary(['promotion_identifier', 'customer_identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_promotion_customer_redemption_counters');

        Schema::table('larasell_promotion_redemptions', function (Blueprint $table) {
            $table->dropColumn(['global_limit', 'customer_limit']);
        });
    }
};
