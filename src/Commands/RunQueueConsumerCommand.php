<?php

declare(strict_types=1);

namespace JeffersonGoncalves\QueueConsumer\Commands;

use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Throwable;

class RunQueueConsumerCommand extends Command
{
    private const CONNECTION_NAME = 'hub';

    protected $signature = 'queue-consumer:run {--payload=} {--last-attempt}';

    protected $description = 'Execute a job payload received from the queue hub';

    public function handle(): int
    {
        $payload = base64_decode((string) $this->option('payload'));

        $container = Container::getInstance();

        $job = new SyncJob($container, $payload, self::CONNECTION_NAME, 'default');

        /** @var Dispatcher $events */
        $events = $container->make('events');

        $events->dispatch(new JobProcessing(self::CONNECTION_NAME, $job));

        try {
            $job->fire();
        } catch (Throwable $exception) {
            $events->dispatch(new JobExceptionOccurred(self::CONNECTION_NAME, $job, $exception));

            if ($this->option('last-attempt')) {
                // Job::fail() already dispatches JobFailed itself, like the real worker does.
                $job->fail($exception);
            }

            throw $exception;
        }

        $events->dispatch(new JobProcessed(self::CONNECTION_NAME, $job));

        return self::SUCCESS;
    }
}
