<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('larasell_product_product_image');
        Schema::dropIfExists('larasell_product_images');
    }
};
