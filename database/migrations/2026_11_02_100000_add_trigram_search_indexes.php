<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Trigram support for catalog search.
 *
 * Search tokenises the query and matches substrings, so these GIN indexes keep
 * `ILIKE '%token%'` and the `<%` similarity fallback fast as the catalog grows.
 * Only PostgreSQL gets them; the SQLite test database keeps plain scans.
 *
 * Written as raw statements because a GIN index needs an operator class
 * (`gin_trgm_ops`) and Laravel's index builder cannot express that portably.
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

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        foreach ($this->indexedColumns as $column) {
            DB::statement(sprintf(
                'CREATE INDEX IF NOT EXISTS %s ON games USING gin (%s gin_trgm_ops)',
                "games_{$column}_trgm_index",
                $column,
            ));
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->indexedColumns as $column) {
            DB::statement("DROP INDEX IF EXISTS games_{$column}_trgm_index");
        }
    }
};
