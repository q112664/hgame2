<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('media_storage_configurations')->where('is_active', true)->count() > 1) {
            throw new RuntimeException('Multiple active media storage configurations require manual reconciliation before this migration.');
        }

        if (DB::table('media_operations')->whereIn('status', ['pending', 'running'])->count() > 1) {
            throw new RuntimeException('Multiple running media operations require recovery before this migration.');
        }

        Schema::table('media_storage_configurations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('active_slot')->nullable()->after('is_active');
            $table->unique('active_slot', 'media_storage_active_slot_unique');
        });

        Schema::table('media_operations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('running_slot')->nullable()->after('status');
            $table->unique('running_slot', 'media_operations_running_slot_unique');
        });

        DB::table('media_storage_configurations')
            ->where('is_active', true)
            ->orderBy('id')
            ->limit(1)
            ->update(['active_slot' => 1]);

        DB::table('media_operations')
            ->whereIn('status', ['pending', 'running'])
            ->orderBy('id')
            ->limit(1)
            ->update(['running_slot' => 1]);
    }

    public function down(): void
    {
        Schema::table('media_operations', function (Blueprint $table): void {
            $table->dropUnique('media_operations_running_slot_unique');
            $table->dropColumn('running_slot');
        });

        Schema::table('media_storage_configurations', function (Blueprint $table): void {
            $table->dropUnique('media_storage_active_slot_unique');
            $table->dropColumn('active_slot');
        });
    }
};
