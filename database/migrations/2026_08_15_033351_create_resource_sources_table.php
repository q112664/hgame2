<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon_path')->nullable();
            $table->string('host_hint')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        // Built-in storefronts use public assets so deploys work without a media upload.
        $now = now();

        DB::table('resource_sources')->insert([
            [
                'name' => 'DLsite',
                'slug' => 'dlsite',
                'icon_path' => '/images/sources/dlsite.ico',
                'host_hint' => 'dlsite.com',
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Steam',
                'slug' => 'steam',
                'icon_path' => '/images/sources/steam.ico',
                'host_hint' => 'steampowered.com',
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_sources');
    }
};
