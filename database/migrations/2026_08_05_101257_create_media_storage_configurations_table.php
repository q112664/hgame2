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
        Schema::create('media_storage_configurations', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('cloudflare_r2');
            $table->text('account_id');
            $table->text('access_key_id');
            $table->text('secret_access_key');
            $table->string('bucket');
            $table->string('public_url');
            $table->string('region')->default('auto');
            $table->string('configuration_fingerprint', 64);
            $table->string('tested_fingerprint', 64)->nullable();
            $table->timestamp('connection_tested_at')->nullable();
            $table->text('connection_test_error')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_storage_configurations');
    }
};
