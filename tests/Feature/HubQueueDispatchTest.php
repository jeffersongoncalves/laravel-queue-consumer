<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\QueueConsumer\Tests\Fixtures\MiddlewareFlaggingJob;
use JeffersonGoncalves\QueueConsumer\Tests\Fixtures\SessionCapturingJob;

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

it('carries the configured session keys in the payload', function (): void {
    config()->set('queue-consumer.session', ['tenant', 'host', 'absent']);
    session()->put(['tenant' => 'acme', 'host' => '10.0.0.1', 'other' => 'ignored']);

    Http::fake([
        '*/api/jobs' => Http::response(['id' => 'job-1'], 202),
    ]);

    dispatch(new SessionCapturingJob);

    Http::assertSent(function (Request $request): bool {
        $payload = json_decode((string) $request['payload'], true);

        expect($payload['queue-consumer:session'])->toBe(['tenant' => 'acme', 'host' => '10.0.0.1']);

        return true;
    });
});

it('refuses to carry session values to a plain http hub', function (): void {
    config()->set('queue-consumer.hub_url', 'http://hub.example.test');
    config()->set('queue-consumer.session', ['tenant']);
    session()->put(['tenant' => 'acme']);

    Http::fake();

    expect(fn () => dispatch(new SessionCapturingJob))
        ->toThrow(LogicException::class, 'requires an https queue-consumer.hub_url');

    Http::assertNothingSent();
});

it('allows a plain http hub on the loopback interface', function (): void {
    config()->set('queue-consumer.hub_url', 'http://localhost:8080');
    config()->set('queue-consumer.session', ['tenant']);
    session()->put(['tenant' => 'acme']);

    Http::fake([
        '*/api/jobs' => Http::response(['id' => 'job-1'], 202),
    ]);

    dispatch(new SessionCapturingJob);

    Http::assertSent(function (Request $request): bool {
        $payload = json_decode((string) $request['payload'], true);

        expect($payload['queue-consumer:session'])->toBe(['tenant' => 'acme']);

        return true;
    });
});

it('still dispatches to a plain http hub when no session value is carried', function (): void {
    config()->set('queue-consumer.hub_url', 'http://hub.example.test');

    Http::fake([
        '*/api/jobs' => Http::response(['id' => 'job-1'], 202),
    ]);

    dispatch(new SessionCapturingJob);

    Http::assertSent(fn (Request $request): bool => true);
});

it('leaves the payload untouched when no session key is configured', function (): void {
    session()->put(['tenant' => 'acme']);

    Http::fake([
        '*/api/jobs' => Http::response(['id' => 'job-1'], 202),
    ]);

    dispatch(new SessionCapturingJob);

    Http::assertSent(function (Request $request): bool {
        $payload = json_decode((string) $request['payload'], true);

        expect($payload)->not->toHaveKey('queue-consumer:session');

        return true;
    });
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
