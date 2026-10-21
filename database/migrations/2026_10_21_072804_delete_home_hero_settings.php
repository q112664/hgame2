<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        // The homepage used to take its browser title from the hero heading. Carry
        // that copy into the new homepage title before the hero settings go, so the
        // page keeps the title it was indexed and shared with.
        $heroTitle = DB::table('settings')
            ->where('key', 'hero_title')
            ->value('value');

        if (is_string($heroTitle) && trim($heroTitle) !== '') {
            DB::table('settings')->updateOrInsert(
                ['key' => 'home_title'],
                ['value' => trim($heroTitle), 'updated_at' => $now, 'created_at' => $now],
            );
        }

        // The homepage hero is gone from the app and from the admin tab, so the rest
        // of its settings would linger as rows nothing reads.
        DB::table('settings')->where('key', 'like', 'hero_%')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
