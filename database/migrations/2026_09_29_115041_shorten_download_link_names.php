<?php

use App\Actions\Games\RelabelAutoDerivedDownloadLinks;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Shorten download link names that were stored as a bare host, so download
     * buttons read `Nekobox` instead of `dl.nekobox.club`.
     *
     * Names a person chose are untouched.
     */
    public function up(): void
    {
        app(RelabelAutoDerivedDownloadLinks::class)();
    }

    public function down(): void
    {
        // The old names were only ever derived from the URL, so there is nothing
        // worth restoring.
    }
};
