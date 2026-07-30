<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('source_name')->nullable()->after('developer');
            $table->string('source_id')->nullable()->after('source_name');
            $table->string('source_url', 2048)->nullable()->after('source_id');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['source_name', 'source_id', 'source_url']);
        });
    }
};
