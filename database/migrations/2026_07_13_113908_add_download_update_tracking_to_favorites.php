<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->timestamp('downloads_updated_at')->nullable()->after('downloads_count');
        });

        Schema::table('favorites', function (Blueprint $table) {
            $table->timestamp('downloads_seen_at')->nullable()->after('game_id');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('downloads_updated_at');
        });

        Schema::table('favorites', function (Blueprint $table) {
            $table->dropColumn('downloads_seen_at');
        });
    }
};
