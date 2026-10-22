<?php

namespace App\Providers;

use App\Auth\TbmasterUserProvider;
use App\Models\Auth\DatabaseUser;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

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
        Auth::provider('tbmaster', function ($app, array $config): TbmasterUserProvider {
            return new TbmasterUserProvider($app['hash'], $config['model']);
        });

        if (! $this->app->environment('testing')) {
            config([
                'auth.providers.users.driver' => 'tbmaster',
                'auth.providers.users.model' => DatabaseUser::class,
            ]);
        }

        if ($this->app->runningInConsole() && ! $this->app->environment('testing')) {
            Event::listen(CommandStarting::class, function (CommandStarting $event): void {
                $blockedCommands = [
                    'db:seed',
                    'db:wipe',
                    'migrate',
                    'migrate:fresh',
                    'migrate:install',
                    'migrate:refresh',
                    'migrate:reset',
                    'migrate:rollback',
                    'model:prune',
                    'schema:dump',
                    'schema:load',
                ];

                if (in_array($event->command, $blockedCommands, true)) {
                    throw new RuntimeException('Database write and schema commands are disabled for this application.');
                }
            });
        }

        Vite::prefetch(concurrency: 3);
    }
}
