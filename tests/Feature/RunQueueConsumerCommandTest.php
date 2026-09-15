<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\QueueConsumer\Tests\Fixtures\FailingJob;
use JeffersonGoncalves\QueueConsumer\Tests\Fixtures\MiddlewareFlaggingJob;

function capturePayloadFor(object $job): string
{
    Http::fake([
        '*/api/jobs' => Http::response(['id' => 'job-1'], 202),
    ]);

    dispatch($job);

    $payload = null;

    Http::assertSent(function (Request $request) use (&$payload): bool {
        $payload = (string) $request['payload'];

        return true;
    });

    return $payload;
}

beforeEach(function (): void {
    MiddlewareFlaggingJob::$middlewareRan = false;
    MiddlewareFlaggingJob::$handled = false;
    FailingJob::$failedCount = 0;
});

it('executes the job through its middleware', function (): void {
    $payload = capturePayloadFor(new MiddlewareFlaggingJob);

    $this->artisan('queue-consumer:run', ['--payload' => base64_encode($payload)])
        ->assertSuccessful();

    expect(MiddlewareFlaggingJob::$middlewareRan)->toBeTrue();
    expect(MiddlewareFlaggingJob::$handled)->toBeTrue();
});

it('calls failed() exactly once when --last-attempt is passed', function (): void {
    $payload = capturePayloadFor(new FailingJob);

    expect(fn () => $this->artisan('queue-consumer:run', [
        '--payload' => base64_encode($payload),
        '--last-attempt' => true,
    ])->run())->toThrow(RuntimeException::class);

    expect(FailingJob::$failedCount)->toBe(1);
});

it('never calls failed() without --last-attempt', function (): void {
    $payload = capturePayloadFor(new FailingJob);

    expect(fn () => $this->artisan('queue-consumer:run', [
        '--payload' => base64_encode($payload),
    ])->run())->toThrow(RuntimeException::class);

    expect(FailingJob::$failedCount)->toBe(0);
});

it('raises the queue lifecycle events around a successful job', function (): void {
    $events = [];

    Event::listen(JobProcessing::class, function (JobProcessing $event) use (&$events): void {
        $events[] = ['processing', $event->connectionName];
    });
    Event::listen(JobProcessed::class, function (JobProcessed $event) use (&$events): void {
        $events[] = ['processed', $event->connectionName];
    });

    $payload = capturePayloadFor(new MiddlewareFlaggingJob);

    $this->artisan('queue-consumer:run', ['--payload' => base64_encode($payload)])
        ->assertSuccessful();

    expect($events)->toBe([['processing', 'hub'], ['processed', 'hub']]);
});

it('raises JobExceptionOccurred but not JobFailed without --last-attempt', function (): void {
    $exceptionOccurred = 0;
    $failed = 0;

    Event::listen(JobExceptionOccurred::class, function () use (&$exceptionOccurred): void {
        $exceptionOccurred++;
    });
    Event::listen(JobFailed::class, function () use (&$failed): void {
        $failed++;
    });

    $payload = capturePayloadFor(new FailingJob);

    expect(fn () => $this->artisan('queue-consumer:run', [
        '--payload' => base64_encode($payload),
    ])->run())->toThrow(RuntimeException::class);

    expect($exceptionOccurred)->toBe(1);
    expect($failed)->toBe(0);
});

it('raises JobFailed on --last-attempt', function (): void {
    $failed = [];

    Event::listen(JobFailed::class, function (JobFailed $event) use (&$failed): void {
        $failed[] = [$event->connectionName, $event->exception->getMessage()];
    });

    $payload = capturePayloadFor(new FailingJob);

    expect(fn () => $this->artisan('queue-consumer:run', [
        '--payload' => base64_encode($payload),
        '--last-attempt' => true,
    ])->run())->toThrow(RuntimeException::class);

    expect($failed)->toHaveCount(1);
    expect($failed[0][0])->toBe('hub');
});
