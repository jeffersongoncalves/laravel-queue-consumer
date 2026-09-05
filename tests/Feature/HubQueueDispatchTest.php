<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\QueueConsumer\Tests\Fixtures\MiddlewareFlaggingJob;

it('dispatches a job through the hub connection with the intact payload', function (): void {
    Http::fake([
        '*/api/jobs' => Http::response(['id' => 'job-1'], 202),
    ]);

    dispatch(new MiddlewareFlaggingJob);

    Http::assertSent(function (Request $request): bool {
        expect($request->url())->toBe('https://hub.example.test/api/jobs');
        expect($request->hasHeader('X-Laravel-Queue-Token', 'test-token'))->toBeTrue();
        expect($request['slug'])->toBe('app-feature-1234');
        expect($request['path'])->toBe(base_path());
        expect($request['queue'])->toBe('default');
        expect($request['delay'])->toBe(0);

        $payload = json_decode((string) $request['payload'], true);
        expect($payload['displayName'])->toBe(MiddlewareFlaggingJob::class);
        expect($payload['data']['commandName'])->toBe(MiddlewareFlaggingJob::class);

        return true;
    });
});

it('does not dispatch before the surrounding transaction commits, only after', function (): void {
    Http::fake([
        '*/api/jobs' => Http::response(['id' => 'job-1'], 202),
    ]);

    DB::transaction(function (): void {
        dispatch((new MiddlewareFlaggingJob)->afterCommit());

        Http::assertNothingSent();
    });

    Http::assertSent(fn (Request $request): bool => true);
});

it('propagates hub http errors to the dispatching code instead of swallowing them', function (): void {
    Http::fake([
        '*/api/jobs' => Http::response(['message' => 'invalid token'], 401),
    ]);

    expect(fn () => dispatch(new MiddlewareFlaggingJob))
        ->toThrow(RequestException::class);
});

it('sends the delay in seconds for delayed dispatch', function (): void {
    Http::fake([
        '*/api/jobs' => Http::response(['id' => 'job-1'], 202),
    ]);

    dispatch((new MiddlewareFlaggingJob)->delay(30));

    Http::assertSent(function (Request $request): bool {
        expect($request['delay'])->toBe(30);

        return true;
    });
});
