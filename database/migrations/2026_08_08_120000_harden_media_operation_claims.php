<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_operation_items', function (Blueprint $table): void {
            $table->string('dispatch_token', 64)->nullable()->after('attempts');
            $table->timestamp('dispatched_at')->nullable()->after('dispatch_token');
            $table->string('lease_token', 64)->nullable()->after('dispatched_at');
            $table->timestamp('lease_expires_at')->nullable()->after('lease_token');
            $table->timestamp('heartbeat_at')->nullable()->after('lease_expires_at');
            $table->string('target_path_hash', 64)->nullable()->after('target_path');
            $table->string('remote_etag', 255)->nullable()->after('target_checksum');
            $table->string('remote_version_id', 255)->nullable()->after('remote_etag');

            $table->index(['status', 'lease_expires_at'], 'media_items_status_lease_index');
            $table->index(['dispatch_token', 'dispatched_at'], 'media_items_dispatch_index');
            $table->unique(['media_operation_id', 'target_path_hash'], 'media_items_target_unique');
        });
    }

    public function down(): void
    {
        Schema::table('media_operation_items', function (Blueprint $table): void {
            $table->dropIndex('media_items_status_lease_index');
            $table->dropIndex('media_items_dispatch_index');
            $table->dropUnique('media_items_target_unique');
            $table->dropColumn([
                'dispatch_token',
                'dispatched_at',
                'lease_token',
                'lease_expires_at',
                'heartbeat_at',
                'target_path_hash',
                'remote_etag',
                'remote_version_id',
            ]);
        });
    }
};
