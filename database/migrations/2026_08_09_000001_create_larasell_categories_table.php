<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Larasell\Larasell\Enums\Visibility;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('larasell_categories')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('status')->default(Visibility::Visible->value);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_categories');
    }
};
