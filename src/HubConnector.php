<?php

declare(strict_types=1);

namespace JeffersonGoncalves\QueueConsumer;

use Illuminate\Queue\Connectors\ConnectorInterface;

class HubConnector implements ConnectorInterface
{
    public function connect(array $config): HubQueue
    {
        return new HubQueue(
            hubUrl: (string) config('queue-consumer.hub_url'),
            token: (string) config('queue-consumer.token'),
            slug: (string) config('queue-consumer.slug'),
            timeout: (int) config('queue-consumer.timeout', 5),
            defaultQueue: $config['queue'] ?? 'default',
        );
    }
}
