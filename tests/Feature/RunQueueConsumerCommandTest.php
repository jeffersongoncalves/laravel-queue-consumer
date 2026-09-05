<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
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
