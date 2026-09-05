<?php

declare(strict_types=1);

namespace JeffersonGoncalves\QueueConsumer\Tests\Fixtures;

use Closure;

class FlaggingMiddleware
{
    public function handle(mixed $job, Closure $next): mixed
    {
        MiddlewareFlaggingJob::$middlewareRan = true;

        return $next($job);
    }
}
