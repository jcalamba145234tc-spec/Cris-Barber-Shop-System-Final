<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        // Refuse RefreshDatabase before it can reset a cached or external database.
        if (! $app->environment('testing') || $app['config']->get('database.default') !== 'sqlite'
            || $app['config']->get('database.connections.sqlite.database') !== ':memory:'
            || filled($app['config']->get('database.connections.sqlite.url'))) {
            throw new \RuntimeException('Tests require isolated in-memory SQLite. Clear cached configuration in your local test copy.');
        }

        return $app;
    }
}
