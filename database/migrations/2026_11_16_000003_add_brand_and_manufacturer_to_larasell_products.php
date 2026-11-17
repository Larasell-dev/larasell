<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A product's brand and manufacturer, which can't be deleted while products use them. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_products', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->constrained('larasell_brands')->restrictOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained('larasell_manufacturers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('larasell_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
            $table->dropConstrainedForeignId('manufacturer_id');
        });
    }
};
