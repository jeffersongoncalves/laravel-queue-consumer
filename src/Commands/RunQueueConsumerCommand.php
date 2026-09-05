<?php

declare(strict_types=1);

namespace JeffersonGoncalves\QueueConsumer\Commands;

use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Queue\Jobs\SyncJob;
use Throwable;

class RunQueueConsumerCommand extends Command
{
    protected $signature = 'queue-consumer:run {--payload=} {--last-attempt}';

    protected $description = 'Execute a job payload received from the queue hub';

    public function handle(): int
    {
        $payload = base64_decode((string) $this->option('payload'));

        $job = new SyncJob(Container::getInstance(), $payload, 'hub', 'default');

        try {
            $job->fire();
        } catch (Throwable $exception) {
            if ($this->option('last-attempt')) {
                $job->fail($exception);
            }

            throw $exception;
        }

        return self::SUCCESS;
    }
}
