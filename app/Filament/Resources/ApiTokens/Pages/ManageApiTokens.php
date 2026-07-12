<?php

namespace App\Filament\Resources\ApiTokens\Pages;

use App\Filament\Resources\ApiTokens\ApiTokenResource;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ManageApiTokens extends ManageRecords
{
    protected static string $resource = ApiTokenResource::class;

    public ?string $plainTextToken = null;

    public function getSubheading(): ?string
    {
        return 'Game Publish API base URL: '.url('/api/v1').' — Authorization: Bearer {token}';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create token')
                ->using(function (array $data): PersonalAccessToken {
                    $user = User::query()
                        ->where('is_admin', true)
                        ->find($data['user_id'] ?? null);

                    if ($user === null) {
                        throw ValidationException::withMessages([
                            'user_id' => 'Select a valid administrator.',
                        ]);
                    }

                    $expiresAt = filled($data['expires_at'] ?? null)
                        ? Carbon::parse($data['expires_at'])
                        : null;

                    $newAccessToken = $user->createToken(
                        (string) $data['name'],
                        ['*'],
                        $expiresAt,
                    );

                    $this->plainTextToken = $newAccessToken->plainTextToken;

                    return $newAccessToken->accessToken;
                })
                ->successNotification(function (): Notification {
                    $token = $this->plainTextToken;
                    $this->plainTextToken = null;

                    return Notification::make()
                        ->success()
                        ->persistent()
                        ->title('API token created')
                        ->body($token !== null
                            ? "Token is also listed in the table and can be copied anytime.\n\n{$token}"
                            : 'Token created.');
                }),
        ];
    }
}
