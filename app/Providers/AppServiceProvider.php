<?php

namespace App\Providers;

use App\Models\CalendarEvent;
use App\Models\Task;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Relation::enforceMorphMap([
            'task' => Task::class,
            'event' => CalendarEvent::class,
        ]);
    }
}
