<?php

namespace App\Providers;

use App\Models\PersonalityReport;
use App\Observers\PersonalityReportObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        PersonalityReport::observe(PersonalityReportObserver::class);
    }
}
