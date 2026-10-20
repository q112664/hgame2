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
        Schema::table('game_releases', function (Blueprint $table) {
            $table->unsignedBigInteger('downloads_count')->default(0)->after('downloads_updated_at');
        });

        // Downloads were only ever counted on the game. A game with a single
        // package can attribute all of them exactly: every download went through
        // that package's links. Games with several packages have no per-package
        // history to recover, so they start from zero.
        DB::table('game_releases')
            ->whereIn('game_id', function ($query): void {
                $query->select('game_id')
                    ->from('game_releases')
                    ->groupBy('game_id')
                    ->havingRaw('count(*) = 1');
            })
            ->update(['downloads_count' => DB::raw('(select downloads_count from games where games.id = game_releases.game_id)')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_releases', function (Blueprint $table) {
            $table->dropColumn('downloads_count');
        });
    }
};
