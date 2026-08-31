<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_carts', function (Blueprint $table) {
            $table->json('metadata')->nullable();
        });

        Schema::table('larasell_orders', function (Blueprint $table) {
            $table->json('metadata')->nullable();
        });

        DB::table('larasell_carts')->update([
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
        ]);
        DB::table('larasell_orders')->update([
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
        ]);

        Schema::table('larasell_carts', function (Blueprint $table) {
            $table->json('metadata')->nullable(false)->change();
        });

        Schema::table('larasell_orders', function (Blueprint $table) {
            $table->json('metadata')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('larasell_carts', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });

        Schema::table('larasell_orders', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
