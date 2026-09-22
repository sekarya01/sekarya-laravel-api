<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Push\FcmClient;
use App\Support\Push\FcmPushNotifier;
use App\Support\Push\PushNotifier;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Log\LogManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Push: satu client FCM (menyimpan access token OAuth2 di cache) dan
        // satu pengirim untuk seluruh proses. Binding ke ANTARMUKA, bukan
        // kelas konkret, supaya Action/job tidak pernah tahu penyedianya dan
        // test bisa menukar implementasinya.
        $this->app->singleton(FcmClient::class, fn ($app): FcmClient => new FcmClient(
            http: $app->make(HttpFactory::class),
            config: $app->make(Config::class),
            log: $app->make(LogManager::class),
            cache: $app->make(CacheRepository::class),
        ));

        $this->app->singleton(PushNotifier::class, fn ($app): PushNotifier => new FcmPushNotifier(
            client: $app->make(FcmClient::class),
            log: $app->make(LogManager::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
