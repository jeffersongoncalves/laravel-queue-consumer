<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;

it('never pops jobs locally', function (): void {
    expect(Queue::connection('hub')->pop())->toBeNull();
});

it('returns zero size when no status endpoint is configured', function (): void {
    expect(Queue::connection('hub')->size())->toBe(0);
});
