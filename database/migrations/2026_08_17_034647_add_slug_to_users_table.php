<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug', 16)->nullable()->after('id');
        });

        User::query()->eachById(function (User $user): void {
            $user->forceFill([
                'slug' => User::newUniqueSlug(),
            ])->saveQuietly();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
