<?php

declare(strict_types=1);

namespace JeffersonGoncalves\QueueConsumer\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class SessionCapturingJob implements ShouldQueue
{
    use Queueable;

    /**
     * @var array<string, mixed>
     */
    public static array $seen = [];

    /**
     * Record what the session held in the process that ran the job.
     */
    public function handle(): void
    {
        self::$seen = [
            'tenant' => session('tenant'),
            'host' => session('host'),
        ];
    }
}
