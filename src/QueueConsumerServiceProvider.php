<?php

declare(strict_types=1);

namespace JeffersonGoncalves\QueueConsumer;

use Illuminate\Support\Facades\Queue;
use JeffersonGoncalves\QueueConsumer\Commands\RunQueueConsumerCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class QueueConsumerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('queue-consumer')
            ->hasConfigFile()
            ->hasCommand(RunQueueConsumerCommand::class);
    }

    public function packageBooted(): void
    {
        $config = $this->app->make('config');

        if (! $config->has('queue.connections.hub')) {
            $config->set('queue.connections.hub', ['driver' => 'hub']);
        }

        Queue::extend('hub', fn (): HubConnector => new HubConnector);
    }
}
