<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// URLs of products and categories now live in larasell_seo_urls. Existing slugs are not carried over.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['larasell_products', 'larasell_categories'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('slug');
            });
        }
    }

    public function down(): void
    {
        foreach (['larasell_products', 'larasell_categories'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->json('slug')->nullable();
            });
        }
    }
};
