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
        Schema::create('media_operation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_operation_id')->constrained()->cascadeOnDelete();
            $table->string('path', 2048);
            $table->string('path_hash', 64);
            $table->string('target_path', 2048)->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedBigInteger('source_size')->nullable();
            $table->unsignedBigInteger('target_size')->nullable();
            $table->string('source_checksum', 64)->nullable();
            $table->string('target_checksum', 64)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['media_operation_id', 'path_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_operation_items');
    }
};
