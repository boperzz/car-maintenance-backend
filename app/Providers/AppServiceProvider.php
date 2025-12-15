<?php

namespace App\Providers;

use Illuminate\Console\Scheduling\Schedule;
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
        $this->app->booted(function () {
            $schedule = app(Schedule::class);
            
            // Send appointment reminders daily at 9 AM
            $schedule->command('appointments:send-reminders')
                ->dailyAt('09:00')
                ->timezone('America/New_York') // Adjust to your timezone
                ->withoutOverlapping();
        });
    }
}
