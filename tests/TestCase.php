<?php

declare(strict_types=1);

namespace JeffersonGoncalves\QueueConsumer\Tests;

use JeffersonGoncalves\QueueConsumer\QueueConsumerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            QueueConsumerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('queue-consumer.hub_url', 'https://hub.example.test');
        $app['config']->set('queue-consumer.token', 'test-token');
        $app['config']->set('queue-consumer.slug', 'app-feature-1234');
        $app['config']->set('queue.default', 'hub');
    }
}
