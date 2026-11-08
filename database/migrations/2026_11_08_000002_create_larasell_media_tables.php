<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_media', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16)->index();
            $table->json('title')->nullable();
            $table->json('description')->nullable();
            $table->json('alt')->nullable();
            $table->timestamps();
        });

        // The files of a media item: its original, and later the renditions made from it.
        Schema::create('larasell_media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('larasell_media')->cascadeOnDelete();
            $table->string('role', 32);
            // Private unless told otherwise, so a file is never public by accident.
            $table->string('visibility', 16)->default('private');
            $table->string('disk');
            $table->string('path');
            $table->string('file_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->char('checksum', 64);
            $table->timestamps();

            $table->unique(['media_id', 'role'], 'larasell_media_assets_role_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_media_assets');
        Schema::dropIfExists('larasell_media');
    }
};
