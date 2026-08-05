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
        Schema::create('media_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_storage_configuration_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->string('status')->default('pending')->index();
            $table->string('source_disk')->nullable();
            $table->string('target_disk')->nullable();
            $table->string('configuration_fingerprint', 64)->nullable()->index();
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('processed_items')->default(0);
            $table->unsignedInteger('succeeded_items')->default(0);
            $table->unsignedInteger('skipped_items')->default(0);
            $table->unsignedInteger('failed_items')->default(0);
            $table->unsignedBigInteger('total_source_bytes')->default(0);
            $table->unsignedBigInteger('total_target_bytes')->default(0);
            $table->json('metadata')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_operations');
    }
};
