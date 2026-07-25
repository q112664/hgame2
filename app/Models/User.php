<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Media;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $avatar
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_admin
 * @property string|null $registration_ip
 * @property string|null $last_login_ip
 * @property Carbon|null $last_login_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'avatar', 'is_admin', 'email_verified_at', 'registration_ip', 'last_login_ip', 'last_login_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, PasskeyUser
{
    use HasApiTokens {
        createToken as createSanctumToken;
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Expose a public URL for the stored avatar path.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value
                ? Media::url($value)
                : null,
            set: fn (?string $value): ?string => $value,
        );
    }

    public function avatarPath(): ?string
    {
        return $this->getRawOriginal('avatar');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_admin;
    }

    /** @return BelongsToMany<Game, $this> */
    public function favoritedGames(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'favorites')
            ->withPivot('downloads_seen_at')
            ->withTimestamps();
    }

    /** @return HasMany<GameRating, $this> */
    public function gameRatings(): HasMany
    {
        return $this->hasMany(GameRating::class);
    }

    /**
     * Create a new personal access token and persist the plaintext for admin reuse.
     *
     * @param  list<string>  $abilities
     */
    public function createToken(string $name, array $abilities = ['*'], ?\DateTimeInterface $expiresAt = null): NewAccessToken
    {
        $accessToken = $this->createSanctumToken($name, $abilities, $expiresAt);

        $accessToken->accessToken->forceFill([
            'plain_text_token' => $accessToken->plainTextToken,
        ])->save();

        return $accessToken;
    }
}
