<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_product_images', function (Blueprint $table) {
            $table->json('placeholder')->nullable()->after('alt');
        });
    }

    public function down(): void
    {
        Schema::table('larasell_product_images', function (Blueprint $table) {
            $table->dropColumn('placeholder');
        });
    }
};
