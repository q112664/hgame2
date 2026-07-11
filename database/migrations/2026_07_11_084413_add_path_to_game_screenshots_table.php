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
        Schema::table('game_screenshots', function (Blueprint $table) {
            $table->string('url')->nullable()->change();
            $table->string('path')->nullable()->after('url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('game_screenshots')->whereNull('url')->update(['url' => '']);

        Schema::table('game_screenshots', function (Blueprint $table) {
            $table->dropColumn('path');
            $table->string('url')->nullable(false)->change();
        });
    }
};
