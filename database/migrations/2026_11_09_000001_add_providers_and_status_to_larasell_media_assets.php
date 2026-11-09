<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larasell_media_assets', function (Blueprint $table) {
            // Every file so far is on the local provider's disks, and ready.
            $table->string('provider', 64)->default('local')->after('visibility');
            $table->string('status', 16)->default('ready')->after('provider');
            $table->text('failure')->nullable()->after('status');
            // The provider's own, e.g. a Mux playback ID.
            $table->json('metadata')->nullable()->after('checksum');
            // Only providers built on Laravel disks have one.
            $table->string('disk')->nullable()->change();

            // Webhooks find an asset by its provider's reference.
            $table->index(['provider', 'path'], 'larasell_media_assets_reference_index');
        });
    }

    public function down(): void
    {
        Schema::table('larasell_media_assets', function (Blueprint $table) {
            $table->dropIndex('larasell_media_assets_reference_index');
            $table->dropColumn(['provider', 'status', 'failure', 'metadata']);
        });
    }
};
