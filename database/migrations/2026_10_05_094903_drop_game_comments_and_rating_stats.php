<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('game_comments');

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['ratings_count', 'ratings_avg']);
        });
    }

    public function down(): void
    {
        Schema::create('game_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable();
            $table->foreignId('reply_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamps();

            $table->index(['game_id', 'created_at']);
            $table->index(['game_id', 'parent_id', 'created_at']);
            $table->index(['game_id', 'rating']);
        });

        Schema::table('game_comments', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('game_comments')->cascadeOnDelete();
        });

        Schema::table('games', function (Blueprint $table) {
            $table->unsignedInteger('ratings_count')->default(0)->after('likes_count');
            $table->decimal('ratings_avg', 3, 2)->default(0)->after('ratings_count');
        });
    }
};
