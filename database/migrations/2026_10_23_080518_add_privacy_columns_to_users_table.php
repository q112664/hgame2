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
        Schema::table('users', function (Blueprint $table) {
            // Opt-in privacy: a hidden tab stays visible to the owner only.
            $table->boolean('hide_favorites')->default(false)->after('avatar');
            $table->boolean('hide_resources')->default(false)->after('hide_favorites');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['hide_favorites', 'hide_resources']);
        });
    }
};
