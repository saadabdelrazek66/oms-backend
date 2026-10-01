<?php

namespace App\Modules\Notifications\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class NotificationServiceProvider extends ServiceProvider
{

    public function boot(): void
    {
        $this->registerRoutes();
    }


    protected function registerRoutes(): void
    {
        Route::prefix('api/notifications')
            ->middleware('auth:sanctum')
            ->group(base_path('app/Modules/Notifications/Routes/api.php'));
    }
}