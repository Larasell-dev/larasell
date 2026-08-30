<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->string('variant_name')->nullable()->after('product_barcode');
            $table->json('variant_options')->nullable()->after('variant_name');
        });

        DB::table('larasell_order_items')->update([
            'variant_name' => DB::raw('product_name'),
            'variant_options' => json_encode([], JSON_THROW_ON_ERROR),
        ]);
    }

    public function down(): void
    {
        Schema::table('larasell_order_items', function (Blueprint $table) {
            $table->dropColumn(['variant_name', 'variant_options']);
        });
    }
};
