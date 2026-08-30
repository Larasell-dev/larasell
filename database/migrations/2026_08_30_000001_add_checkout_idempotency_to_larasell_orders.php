<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_orders', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('idempotency_fingerprint', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('larasell_orders', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['idempotency_key', 'idempotency_fingerprint']);
        });
    }
};
