<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_comments', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('user_id')
                ->constrained('game_comments')
                ->cascadeOnDelete();

            $table->foreignId('reply_to_user_id')
                ->nullable()
                ->after('parent_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['game_id', 'parent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('game_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reply_to_user_id');
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
