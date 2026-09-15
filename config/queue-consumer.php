<?php

declare(strict_types=1);

return [
    'hub_url' => env('QUEUE_CONSUMER_HUB_URL'),

    'token' => env('QUEUE_CONSUMER_TOKEN'),

    'slug' => env('QUEUE_CONSUMER_SLUG', basename(base_path())),

    'timeout' => env('QUEUE_CONSUMER_TIMEOUT', 5),

    /*
     * Session keys carried with the job and restored into the session of the
     * process that runs it. The job runs in a fresh process with an empty
     * session, so anything the job reads from the session must be listed here.
     *
     * These values are sent to the hub inside the payload — list only what the
     * job actually needs, never credentials or the whole session.
     *
     * Application data that does not live in the session needs nothing here:
     * Laravel's own Context is already carried in the payload and rehydrated.
     */
    'session' => [],
];
