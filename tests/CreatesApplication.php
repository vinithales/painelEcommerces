<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Docker exports variables in $_SERVER, which can override PHPUnit's
        // <env> values. Set the test connection before RefreshDatabase runs.
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.sqlite.url', null);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('app.env', 'testing');
        $app['db']->purge('sqlite');
        if ($app['db']->connection()->getDriverName() !== 'sqlite' || $app['db']->connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Tests require an isolated in-memory SQLite database.');
        }

        return $app;
    }
}
