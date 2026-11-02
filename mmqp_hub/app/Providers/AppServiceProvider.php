<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (config('demo.enabled')) {
            config(['mail.default' => 'array', 'queue.default' => 'sync']);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (request()->header('x-forwarded-proto') === 'https' || str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Notifications\Events\NotificationSending::class,
            fn ($event) => config('demo.enabled') && in_array($event->channel, ['mail', \NotificationChannels\WebPush\WebPushChannel::class], true) ? false : null,
        );

        \Carbon\Carbon::setLocale('es');
        setlocale(LC_TIME, 'es_ES.utf8', 'es_ES', 'esp');
    }
}
