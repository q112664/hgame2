<?php

namespace App\Providers;

use App\Filesystem\R2FilesystemAdapter;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SendPasswordResetLinkRequest;
use App\Listeners\RecordUserLogin;
use App\Models\Game;
use App\Models\PersonalAccessToken;
use App\Models\Setting;
use App\Support\MediaStorageManager;
use Aws\S3\S3Client;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;
use Laravel\Fortify\Http\Requests\SendPasswordResetLinkRequest as FortifySendPasswordResetLinkRequest;
use Laravel\Sanctum\Sanctum;
use League\Flysystem\Filesystem;
use SocialiteProviders\Discord\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FortifyLoginRequest::class, LoginRequest::class);
        $this->app->bind(FortifySendPasswordResetLinkRequest::class, SendPasswordResetLinkRequest::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerR2FilesystemDriver();

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $this->configureDefaults();
        $this->configureSiteUrl();
        $this->configureMediaStorage();

        Event::listen(Login::class, RecordUserLogin::class);

        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('discord', Provider::class);
        });

        Route::bind('resource', static fn (string $value): Game => Game::query()
            ->published()
            ->where('slug', $value)
            ->firstOrFail());
    }

    /**
     * Apply the admin-configured site URL to runtime config.
     */
    protected function configureSiteUrl(): void
    {
        Setting::applySiteUrlToConfig();
    }

    protected function configureMediaStorage(): void
    {
        app(MediaStorageManager::class)->applyRuntimeConfiguration();
    }

    protected function registerR2FilesystemDriver(): void
    {
        Storage::extend('r2', function ($app, array $config): FilesystemAdapter {
            $s3Config = $config + ['version' => 'latest'];

            if (filled($config['key'] ?? null) && filled($config['secret'] ?? null)) {
                $s3Config['credentials'] = Arr::only($config, ['key', 'secret']);
            }

            $s3Config = Arr::except($s3Config, [
                'driver',
                'throw',
                'report',
                'url',
                'configuration_fingerprint',
            ]);
            $client = new S3Client($s3Config);
            $adapter = new R2FilesystemAdapter(
                $client,
                (string) $s3Config['bucket'],
                (string) ($s3Config['root'] ?? ''),
                options: $config['options'] ?? [],
                streamReads: (bool) ($s3Config['stream_reads'] ?? true),
            );
            $filesystem = new Filesystem($adapter, Arr::only($config, [
                'disable_asserts',
                'temporary_url',
                'url',
            ]));

            return new AwsS3V3Adapter($filesystem, $adapter, $s3Config + [
                'url' => $config['url'] ?? null,
            ], $client);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Keep registration simple for Western-facing signups: letters + numbers.
        Password::defaults(fn (): Password => Password::min(8)
            ->letters()
            ->numbers(),
        );
    }
}
