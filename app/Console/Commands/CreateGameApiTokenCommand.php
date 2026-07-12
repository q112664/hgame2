<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('game:token {user : The admin user ID or email} {--name=game-publish : Token name}')]
#[Description('Create a Sanctum API token for an administrator to publish games')]
class CreateGameApiTokenCommand extends Command
{
    public function handle(): int
    {
        $identifier = (string) $this->argument('user');

        $user = User::query()
            ->where('email', $identifier)
            ->when(
                ctype_digit($identifier),
                fn ($query) => $query->orWhere('id', (int) $identifier),
            )
            ->first();

        if ($user === null) {
            $this->error("User [{$identifier}] was not found.");

            return self::FAILURE;
        }

        if (! $user->is_admin) {
            $this->error("User [{$user->email}] is not an administrator.");

            return self::FAILURE;
        }

        $token = $user->createToken((string) $this->option('name'))->plainTextToken;

        $this->info('API token created. Store it securely; it will not be shown again.');
        $this->line($token);

        return self::SUCCESS;
    }
}
