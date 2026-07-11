<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('game_release_platform', function (Blueprint $table) {
            $table->foreignId('game_release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->primary(['game_release_id', 'platform_id']);
        });

        Schema::create('game_release_language', function (Blueprint $table) {
            $table->foreignId('game_release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->primary(['game_release_id', 'language_id']);
        });

        DB::table('game_releases')
            ->whereNotNull('platform_id')
            ->get(['id', 'platform_id'])
            ->each(fn (object $release) => DB::table('game_release_platform')->insert([
                'game_release_id' => $release->id,
                'platform_id' => $release->platform_id,
            ]));

        DB::table('game_releases')
            ->whereNotNull('language_id')
            ->get(['id', 'language_id'])
            ->each(fn (object $release) => DB::table('game_release_language')->insert([
                'game_release_id' => $release->id,
                'language_id' => $release->language_id,
            ]));

        Schema::table('game_releases', function (Blueprint $table) {
            $table->foreignId('platform_id')->nullable()->change();
            $table->foreignId('language_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_release_language');
        Schema::dropIfExists('game_release_platform');
    }
};
