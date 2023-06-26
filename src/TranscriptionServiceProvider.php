<?php

namespace OnrampLab\Transcription;

use Illuminate\Support\ServiceProvider;

class TranscriptionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/transcription.php', 'transcription');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'transcription-migrations');

        $this->publishes([
            __DIR__ . '/../config/transcription.php' => config_path('transcription.php'),
        ], 'transcription-config');
    }
}
