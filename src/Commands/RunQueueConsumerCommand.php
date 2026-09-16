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
use Illuminate\Support\Facades\Queue;
use JeffersonGoncalves\QueueConsumer\HubJob;
use JeffersonGoncalves\QueueConsumer\HubQueue;
use Throwable;

class RunQueueConsumerCommand extends Command
{
    private const CONNECTION_NAME = 'hub';

    protected $signature = 'queue-consumer:run {--payload=} {--queue=default} {--last-attempt}';

    protected $description = 'Execute a job payload received from the queue hub';

    /**
     * Execute the base64-encoded payload the hub sent back, raising the same
     * queue lifecycle events a regular worker would raise around it.
     */
    public function handle(): int
    {
        $payload = base64_decode((string) $this->option('payload'));

        $container = Container::getInstance();

        $queue = (string) ($this->option('queue') ?: 'default');

        $job = new HubJob($container, $payload, self::CONNECTION_NAME, $queue);

        $this->restoreSession($job);

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

        if ($job->isReleased()) {
            $this->releaseToHub($job, $payload);
        }

        $events->dispatch(new JobProcessed(self::CONNECTION_NAME, $job));

        return self::SUCCESS;
    }

    /**
     * A job released by its middleware (WithoutOverlapping, RateLimited, a plain
     * release()) has nowhere to go in this process — the hub is the only queue
     * around, so the untouched payload is posted back to it with the requested
     * delay. A failure to re-post throws, so the job is never lost quietly.
     */
    private function releaseToHub(HubJob $job, string $payload): void
    {
        Queue::connection(self::CONNECTION_NAME)->pushRaw($payload, $job->getQueue(), [
            'delay' => $job->releaseDelay(),
        ]);
    }

    /**
     * This process has an empty session, so the session values the dispatching
     * process carried in the payload are restored before anything reads them.
     */
    private function restoreSession(SyncJob $job): void
    {
        /** @var array<string, mixed> $session */
        $session = $job->payload()[HubQueue::SESSION_PAYLOAD_KEY] ?? [];

        if ($session !== []) {
            session()->put($session);
        }
    }
}
