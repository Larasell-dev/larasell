<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Larasell\Larasell\Enums\Visibility;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_products', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('price');
            $table->integer('stock')->default(0);
            $table->boolean('allow_backorders')->default(true);
            $table->string('status')->default(Visibility::Visible->value);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_products');
    }
};
