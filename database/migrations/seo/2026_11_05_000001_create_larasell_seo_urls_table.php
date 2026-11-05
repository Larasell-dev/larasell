<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larasell_seo_urls', function (Blueprint $table) {
            // Paths compare byte for byte, as in PHP, SQLite, and PostgreSQL. MySQL's default collations
            // treat "café" and "cafe" as the same path. Other databases ignore these two settings.
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_bin');

            $table->id();
            $table->string('channel', 64);
            $table->string('locale', 35);
            // Paths have no length limit, so lookups and the unique index use their SHA-256 (SeoUrl::hash()).
            $table->text('path');
            $table->char('path_hash', 64);
            // The hash of the path without a trailing "-N": finds every numbered variant of a path in one query.
            $table->char('base_path_hash', 64);
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id');
            $table->string('status', 16);
            // 1 on the canonical row, NULL on all others: the unique index below then allows one canonical
            // row per page and context, and any number of old ones, because NULLs never collide.
            $table->tinyInteger('is_canonical')->nullable()->storedAs("case when status = 'canonical' then 1 end");
            $table->boolean('is_custom')->default(false);
            $table->timestamp('went_public_at')->nullable();
            $table->json('gone_snapshot')->nullable();
            $table->nullableMorphs('replacement');
            $table->timestamps();

            $table->unique(['channel', 'locale', 'path_hash']);
            $table->index(['channel', 'locale', 'base_path_hash']);
            $table->unique(['resource_type', 'resource_id', 'channel', 'locale', 'is_canonical'], 'larasell_seo_urls_one_canonical_unique');
            $table->index(['resource_type', 'resource_id', 'channel', 'locale', 'status'], 'larasell_seo_urls_resource_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larasell_seo_urls');
    }
};
