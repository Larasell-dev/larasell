<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_payments', function (Blueprint $table) {
            $table->string('method')->default('legacy')->after('order_id')->index();
            $table->timestamp('paid_at')->nullable()->after('failure_message');
        });
    }

    public function down(): void
    {
        Schema::table('larasell_payments', function (Blueprint $table) {
            $table->dropIndex(['method']);
            $table->dropColumn(['method', 'paid_at']);
        });
    }
};
