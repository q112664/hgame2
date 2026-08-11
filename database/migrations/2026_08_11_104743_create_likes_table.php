<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'game_id']);
        });

        Schema::table('games', function (Blueprint $table) {
            $table->unsignedBigInteger('likes_count')->default(0)->after('downloads_count');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('likes_count');
        });

        Schema::dropIfExists('likes');
    }
};
