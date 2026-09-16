<?php

declare(strict_types=1);

namespace JeffersonGoncalves\QueueConsumer\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ReleasingJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public static int $releaseDelay = 60;

    public static bool $handled = false;

    /**
     * Release itself back to the queue, the way WithoutOverlapping and
     * RateLimited do when they cannot let the job run yet.
     */
    public function handle(): void
    {
        self::$handled = true;

        $this->release(self::$releaseDelay);
    }
}
