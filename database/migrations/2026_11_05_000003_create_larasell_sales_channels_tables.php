<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_sales_channels', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->string('type', 32);
            $table->string('default_locale', 35);
            $table->string('default_currency', 3);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('larasell_sales_channel_locales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_channel_id')->constrained('larasell_sales_channels')->cascadeOnDelete();
            $table->string('locale', 35);
            $table->unique(['sales_channel_id', 'locale'], 'larasell_channel_locales_unique');
        });

        Schema::create('larasell_sales_channel_currencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_channel_id')->constrained('larasell_sales_channels')->cascadeOnDelete();
            $table->string('currency', 3);
            $table->unique(['sales_channel_id', 'currency'], 'larasell_channel_currencies_unique');
        });

        Schema::create('larasell_sales_channel_entry_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_channel_id')->constrained('larasell_sales_channels')->cascadeOnDelete();
            $table->string('scheme', 8);
            $table->string('host');
            $table->unsignedInteger('port')->nullable();
            $table->string('prefix')->default('');
            $table->string('locale', 35);
            $table->string('currency', 3);
            // true on the primary entry point, NULL on all others: the unique index then allows one primary
            // per channel, locale and currency, and any number of others, because NULLs never collide.
            $table->boolean('is_primary')->nullable();
            $table->timestamps();

            $table->unique(['host', 'prefix'], 'larasell_entry_points_url_unique');
            $table->unique(['sales_channel_id', 'locale', 'currency', 'is_primary'], 'larasell_entry_points_one_primary_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_sales_channel_entry_points');
        Schema::dropIfExists('larasell_sales_channel_currencies');
        Schema::dropIfExists('larasell_sales_channel_locales');
        Schema::dropIfExists('larasell_sales_channels');
    }
};
