<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->date('date')->index();
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('downloads')->default(0);
            $table->timestamps();

            // One row per game per day: the counter increments in place, so the
            // pair has to be unique for a race to be caught instead of doubled.
            $table->unique(['game_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_daily_stats');
    }
};
