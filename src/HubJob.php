<?php

declare(strict_types=1);

namespace JeffersonGoncalves\QueueConsumer;

use Illuminate\Queue\Jobs\SyncJob;

/**
 * A job released by its middleware has to go back to the hub, which is the only
 * thing holding a queue here. The base job discards the requested delay, so it
 * is captured on the way through.
 */
class HubJob extends SyncJob
{
    private int $releaseDelay = 0;

    /**
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     */
    public function release($delay = 0): void
    {
        $this->releaseDelay = (int) $this->secondsUntil($delay);

        parent::release($delay);
    }

    /**
     * Seconds the job asked to wait before running again.
     */
    public function releaseDelay(): int
    {
        return $this->releaseDelay;
    }

    /**
     * The queue the job came from, instead of the `sync` the parent reports.
     */
    public function getQueue(): string
    {
        return $this->queue;
    }
}
