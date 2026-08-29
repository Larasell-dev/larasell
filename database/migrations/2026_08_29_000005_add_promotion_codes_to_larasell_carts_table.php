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
            $table->json('promotion_codes')->nullable();
        });

        DB::table('larasell_carts')->update([
            'promotion_codes' => json_encode([], JSON_THROW_ON_ERROR),
        ]);

        Schema::table('larasell_carts', function (Blueprint $table) {
            $table->json('promotion_codes')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('larasell_carts', function (Blueprint $table) {
            $table->dropColumn('promotion_codes');
        });
    }
};
