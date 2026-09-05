<?php

declare(strict_types=1);

namespace JeffersonGoncalves\QueueConsumer\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use RuntimeException;
use Throwable;

class FailingJob implements ShouldQueue
{
    use Queueable;

    public static int $failedCount = 0;

    public function handle(): void
    {
        throw new RuntimeException('job failed intentionally');
    }

    public function failed(Throwable $exception): void
    {
        self::$failedCount++;
    }
}
