<?php

namespace App\Providers;

use App\Database\StrictMySqlGrammar;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        $configure = static function (Connection $connection): void {
            if ($connection->getDriverName() === 'mysql') {
                $connection->setQueryGrammar(new StrictMySqlGrammar($connection));
            }
        };

        Event::listen(ConnectionEstablished::class, static function (ConnectionEstablished $event) use ($configure): void {
            $configure($event->connection);
        });

        // Also cover connections already resolved by another provider. Merely
        // configuring a grammar does not connect PDO or run any database query.
        foreach (DB::getConnections() as $connection) {
            $configure($connection);
        }
    }
}
