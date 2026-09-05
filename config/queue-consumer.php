<?php

declare(strict_types=1);

return [
    'hub_url' => env('QUEUE_CONSUMER_HUB_URL'),

    'token' => env('QUEUE_CONSUMER_TOKEN'),

    'slug' => env('QUEUE_CONSUMER_SLUG', basename(base_path())),

    'timeout' => env('QUEUE_CONSUMER_TIMEOUT', 5),
];
