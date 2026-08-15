<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_comments', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable()->after('body');
            $table->index(['game_id', 'rating']);
        });

        Schema::table('games', function (Blueprint $table) {
            $table->unsignedInteger('ratings_count')->default(0)->after('likes_count');
            $table->decimal('ratings_avg', 3, 2)->default(0)->after('ratings_count');
        });
    }

    public function down(): void
    {
        Schema::table('game_comments', function (Blueprint $table) {
            $table->dropIndex(['game_id', 'rating']);
            $table->dropColumn('rating');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['ratings_count', 'ratings_avg']);
        });
    }
};
