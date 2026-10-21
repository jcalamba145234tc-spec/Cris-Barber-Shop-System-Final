<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        // Refuse RefreshDatabase before it can reset a cached or external database.
        $config = $app['config'];
        $driver = $config->get('database.default');
        $isolatedSqlite = $driver === 'sqlite' && $config->get('database.connections.sqlite.database') === ':memory:'
            && blank($config->get('database.connections.sqlite.url'));
        $isolatedMysql = $driver === 'mysql' && $config->get('database.connections.mysql.database') === 'cris_barber_shop_testing'
            && $config->get('database.connections.mysql.host') === '127.0.0.1'
            && blank($config->get('database.connections.mysql.url'));
        if (! $app->environment('testing') || (! $isolatedSqlite && ! $isolatedMysql)) {
            throw new \RuntimeException('Tests require in-memory SQLite or local cris_barber_shop_testing. Clear cached configuration.');
        }

        return $app;
    }
}
