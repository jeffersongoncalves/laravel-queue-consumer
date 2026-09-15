<?php

declare(strict_types=1);

namespace JeffersonGoncalves\QueueConsumer;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\Queue;
use Illuminate\Support\Facades\Http;

class HubQueue extends Queue implements QueueContract
{
    /**
     * Payload key holding the session values carried to the environment.
     */
    public const SESSION_PAYLOAD_KEY = 'queue-consumer:session';

    private const RETRY_TIMES = 3;

    private const RETRY_SLEEP_MILLISECONDS = 100;

    /**
     * @param  list<string>  $sessionKeys
     */
    public function __construct(
        private readonly string $hubUrl,
        private readonly string $token,
        private readonly string $slug,
        private readonly int $timeout,
        private readonly string $defaultQueue = 'default',
        private readonly array $sessionKeys = [],
    ) {}

    // ponytail: no hub status endpoint is configurable yet, so every queue-depth
    // introspection method is a stub. Add real GETs to a hub status endpoint
    // if that becomes configurable.
    public function size($queue = null): int
    {
        return 0;
    }

    public function pendingSize($queue = null): int
    {
        return 0;
    }

    public function delayedSize($queue = null): int
    {
        return 0;
    }

    public function reservedSize($queue = null): int
    {
        return 0;
    }

    public function creationTimeOfOldestPendingJob($queue = null): ?int
    {
        return null;
    }

    public function push($job, $data = '', $queue = null): mixed
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            null,
            fn (string $payload, ?string $queue): mixed => $this->pushRaw($payload, $queue),
        );
    }

    public function later($delay, $job, $data = '', $queue = null): mixed
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data, $delay),
            $queue,
            $delay,
            fn (string $payload, ?string $queue, $delay): mixed => $this->pushRaw($payload, $queue, [
                'delay' => $this->secondsUntil($delay),
            ]),
        );
    }

    public function pushRaw($payload, $queue = null, array $options = []): mixed
    {
        $response = Http::baseUrl(rtrim($this->hubUrl, '/'))
            ->withHeaders(['X-Laravel-Queue-Token' => $this->token])
            ->timeout($this->timeout)
            ->retry(
                self::RETRY_TIMES,
                self::RETRY_SLEEP_MILLISECONDS,
                fn (\Throwable $exception): bool => $exception instanceof ConnectionException,
            )
            ->post('/api/jobs', [
                'slug' => $this->slug,
                'path' => base_path(),
                'queue' => $this->getQueue($queue),
                'delay' => $options['delay'] ?? 0,
                'payload' => $payload,
            ]);

        $response->throw();

        return $response->json('id');
    }

    public function pop($queue = null): mixed
    {
        return null;
    }

    /**
     * The job runs in a fresh process with an empty session, so the configured
     * session keys travel inside the payload and are restored by the
     * queue-consumer:run command before the job is fired.
     *
     * @param  string|object  $job
     * @param  string  $queue
     * @param  mixed  $data
     * @return array<string, mixed>
     */
    protected function createPayloadArray($job, $queue, $data = ''): array
    {
        $payload = parent::createPayloadArray($job, $queue, $data);

        $session = array_filter(
            session()->only($this->sessionKeys),
            fn (mixed $value): bool => $value !== null,
        );

        return $session === []
            ? $payload
            : [...$payload, self::SESSION_PAYLOAD_KEY => $session];
    }

    private function getQueue(?string $queue): string
    {
        return $queue ?: $this->defaultQueue;
    }
}
