<?php

declare(strict_types=1);

namespace JeffersonGoncalves\QueueConsumer\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class MiddlewareFlaggingJob implements ShouldQueue
{
    use Queueable;

    public static bool $middlewareRan = false;

    public static bool $handled = false;

    /**
     * @return array<int, FlaggingMiddleware>
     */
    public function middleware(): array
    {
        return [new FlaggingMiddleware];
    }

    public function handle(): void
    {
        self::$handled = true;
    }
}
