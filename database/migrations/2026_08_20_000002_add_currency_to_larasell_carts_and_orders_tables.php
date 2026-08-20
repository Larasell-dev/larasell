<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Larasell\Larasell\Enums\Currency;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_carts', function (Blueprint $table) {
            $table->string('currency', 3)->nullable();
        });

        Schema::table('larasell_orders', function (Blueprint $table) {
            $table->string('currency', 3)->nullable();
        });

        DB::table('larasell_carts')->whereNull('currency')->update(['currency' => Currency::USD->value]);

        DB::table('larasell_orders')
            ->select(['id', 'total'])
            ->orderBy('id')
            ->each(function (object $order): void {
                $total = is_string($order->total) ? json_decode($order->total, true) : $order->total;
                $currency = is_array($total) && is_string($total['currency'] ?? null)
                    ? $total['currency']
                    : Currency::USD->value;

                DB::table('larasell_orders')->where('id', $order->id)->update(['currency' => $currency]);
            });

        Schema::table('larasell_carts', function (Blueprint $table) {
            $table->string('currency', 3)->nullable(false)->change();
        });

        Schema::table('larasell_orders', function (Blueprint $table) {
            $table->string('currency', 3)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('larasell_orders', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::table('larasell_carts', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
