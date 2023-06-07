<?php

namespace OnrampLab\LaravelPackageTemplate;

use Illuminate\Support\ServiceProvider;

class LaravelPackageTemplateServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // $this->mergeConfigFrom(__DIR__ . '/../config/package_template.php', 'package_template');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'package-template-migrations');

        $this->publishes([
            __DIR__ . '/../config/package_template.php' => config_path('package_template.php'),
        ], 'package-template-config');
    }
}
