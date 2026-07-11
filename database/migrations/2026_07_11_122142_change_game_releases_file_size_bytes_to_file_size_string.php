<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Number;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('game_releases', function (Blueprint $table) {
            $table->string('file_size')->nullable()->after('version');
        });

        DB::table('game_releases')
            ->whereNotNull('file_size_bytes')
            ->orderBy('id')
            ->each(function (object $release): void {
                DB::table('game_releases')
                    ->where('id', $release->id)
                    ->update([
                        'file_size' => Number::fileSize((int) $release->file_size_bytes),
                    ]);
            });

        Schema::table('game_releases', function (Blueprint $table) {
            $table->dropColumn('file_size_bytes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_releases', function (Blueprint $table) {
            $table->unsignedBigInteger('file_size_bytes')->nullable()->after('version');
        });

        Schema::table('game_releases', function (Blueprint $table) {
            $table->dropColumn('file_size');
        });
    }
};
