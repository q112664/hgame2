<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameRelease;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Bulk-assign release contributors without bumping downloads_updated_at.
 *
 * Uses a query-builder UPDATE (no Eloquent model events).
 */
#[Signature('releases:assign-contributor
    {email : Contributor user email (case-insensitive)}
    {--game=* : Limit to game slug(s)}
    {--release=* : Limit to release id(s)}
    {--only-missing : Only assign where user_id is currently null}
    {--dry-run : Show how many rows would change without writing}
')]
#[Description('Assign a site user as release contributor without updating download timestamps')]
class AssignReleaseContributorCommand extends Command
{
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        $user = User::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->first();

        if ($user === null) {
            $this->error("No user found for email [{$email}].");

            return self::FAILURE;
        }

        $query = GameRelease::query();

        $gameSlugs = array_values(array_filter(
            array_map(strval(...), (array) $this->option('game')),
        ));

        if ($gameSlugs !== []) {
            $gameIds = Game::query()
                ->whereIn('slug', $gameSlugs)
                ->pluck('id');

            if ($gameIds->count() !== count($gameSlugs)) {
                $this->error('One or more --game slugs were not found.');

                return self::FAILURE;
            }

            $query->whereIn('game_id', $gameIds);
        }

        $releaseIds = array_values(array_filter(
            array_map(
                static fn (mixed $id): int => (int) $id,
                (array) $this->option('release'),
            ),
            static fn (int $id): bool => $id > 0,
        ));

        if ($releaseIds !== []) {
            $query->whereIn('id', $releaseIds);
        }

        if ($this->option('only-missing')) {
            $query->whereNull('user_id');
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->warn('No matching releases to update.');

            return self::SUCCESS;
        }

        $this->info("User #{$user->id} ({$user->email}) → {$count} release(s).");

        if ($this->option('dry-run')) {
            $this->comment('Dry run — no changes written.');

            return self::SUCCESS;
        }

        // Query builder update: no model events, no downloads_updated_at touch.
        $updated = $query->update([
            'user_id' => $user->id,
            'updated_at' => now(),
        ]);

        $this->info("Assigned contributor on {$updated} release(s).");
        $this->comment('games.downloads_updated_at was not modified.');

        return self::SUCCESS;
    }
}
