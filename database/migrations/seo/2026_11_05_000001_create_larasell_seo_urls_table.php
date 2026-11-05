<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_seo_urls', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 64);
            $table->string('locale', 35);
            $table->string('path', 512);
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id');
            $table->string('status', 16);
            $table->boolean('is_custom')->default(false);
            $table->timestamp('went_public_at')->nullable();
            $table->json('gone_snapshot')->nullable();
            $table->nullableMorphs('replacement');
            $table->timestamps();

            $table->unique(['channel', 'locale', 'path']);
            $table->index(['resource_type', 'resource_id', 'channel', 'locale', 'status'], 'larasell_seo_urls_resource_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_seo_urls');
    }
};
