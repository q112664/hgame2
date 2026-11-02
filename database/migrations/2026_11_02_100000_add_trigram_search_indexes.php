<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Tpetry\PostgresqlEnhanced\Schema\Blueprint;
use Tpetry\PostgresqlEnhanced\Support\Facades\Schema;

/**
 * Trigram support for catalog search.
 *
 * Search tokenises the query and matches substrings, so the indexes exist to keep
 * `ILIKE '%token%'` and the similarity fallback fast as the catalog grows. Only
 * PostgreSQL gets them; the SQLite test database keeps the plain column scans.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $indexedColumns = ['title', 'subtitle', 'developer', 'source_name'];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::createExtensionIfNotExists('pg_trgm');

        Schema::table('games', function (Blueprint $table): void {
            foreach ($this->indexedColumns as $column) {
                $table->index("{$column} gin_trgm_ops")
                    ->algorithm('gin')
                    ->name("games_{$column}_trgm_index");
            }
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('games', function (Blueprint $table): void {
            foreach ($this->indexedColumns as $column) {
                $table->dropIndex("games_{$column}_trgm_index");
            }
        });
    }
};
