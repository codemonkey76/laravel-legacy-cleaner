<?php

namespace Codemonkey76\LegacyCleaner;

use Codemonkey76\LegacyCleaner\Commands\AnalyzeControllersCommand;
use Codemonkey76\LegacyCleaner\Commands\AnalyzeModelsCommand;
use Codemonkey76\LegacyCleaner\Commands\AnalyzeRoutesCommand;
use Codemonkey76\LegacyCleaner\Commands\AnalyzeViewsCommand;
use Codemonkey76\LegacyCleaner\Commands\GenerateReportCommand;
use Illuminate\Support\ServiceProvider;

class LegacyCleanerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/legacy-cleaner.php',
            'legacy-cleaner'
        );
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AnalyzeRoutesCommand::class,
                AnalyzeControllersCommand::class,
                AnalyzeModelsCommand::class,
                AnalyzeViewsCommand::class,
                GenerateReportCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/legacy-cleaner.php' => config_path('legacy-cleaner.php'),
            ], 'legacy-cleaner-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'legacy-cleaner-migrations');
        }
    }
}
