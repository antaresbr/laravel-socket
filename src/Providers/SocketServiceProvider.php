<?php
namespace Antares\Socket\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SocketServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFile('socket');
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadTranslationsFrom(ai_socket_path('lang'), 'socket');

        $this->loadRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishResources();
        }
    }

    protected function mergeConfigFile($name)
    {
        $targetFile = ai_socket_path("config/{$name}.php");

        if (is_file($targetFile) and !Config::has($name)) {
            $this->mergeConfigFrom($targetFile, $name);
        }
    }

    protected function loadRoutes()
    {
        $attributes = [
            'prefix' => config('socket.route.prefix.api'),
            'namespace' => 'Antares\Socket\Http\Controllers',
        ];
        Route::group($attributes, function () {
            $this->loadRoutesFrom(ai_socket_path('routes/api.php'));
        });
    }

    protected function publishResources()
    {
        $this->publishes([
            ai_socket_path('config/socket.php') => config_path('socket.php'),
        ], 'socket-config');
    }
}
