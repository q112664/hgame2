<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    public function getSubheading(): ?string
    {
        return 'Create accounts, manage admin access, and reset passwords. Login IPs update automatically after sign-in.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create user')
                ->modalHeading('Create user')
                ->createAnother(false)
                ->using(function (array $data): Model {
                    $payload = UserResource::mutateUserFormData($data);
                    $payload['registration_ip'] = request()->ip();

                    return User::query()->create($payload);
                }),
        ];
    }
}
