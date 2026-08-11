<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_releases', function (Blueprint $table) {
            if (! Schema::hasColumn('game_releases', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('game_id')
                    ->constrained()
                    ->nullOnDelete();
            }
        });

        Schema::table('game_download_links', function (Blueprint $table) {
            if (Schema::hasColumn('game_download_links', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_download_links', function (Blueprint $table) {
            if (! Schema::hasColumn('game_download_links', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('game_release_id')
                    ->constrained()
                    ->nullOnDelete();
            }
        });

        Schema::table('game_releases', function (Blueprint $table) {
            if (Schema::hasColumn('game_releases', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};
