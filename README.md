<div class="filament-hidden">

![Laravel Queue Consumer](https://raw.githubusercontent.com/jeffersongoncalves/laravel-queue-consumer/main/art/jeffersongoncalves-laravel-queue-consumer.png)

</div>

# Laravel Queue Consumer

[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-support-FFDD00?style=flat-square&logo=buy-me-a-coffee&logoColor=black)](https://buymeacoffee.com/jeffersongoncalves)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jeffersongoncalves/laravel-queue-consumer.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-queue-consumer)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-queue-consumer/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/jeffersongoncalves/laravel-queue-consumer/actions/workflows/tests.yml)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/laravel-queue-consumer/pint.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/jeffersongoncalves/laravel-queue-consumer/actions/workflows/pint.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/jeffersongoncalves/laravel-queue-consumer.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-queue-consumer)
[![License](https://img.shields.io/packagist/l/jeffersongoncalves/laravel-queue-consumer.svg?style=flat-square)](LICENSE.md)

Dispatch Laravel queue jobs to a central "hub" application over HTTP, instead of running a dedicated queue worker in every environment.

This package is built for setups that spin up an ephemeral Laravel application per git branch (review apps) for CI. Running a full queue worker in each of these short-lived environments wastes memory across dozens of environments that are mostly idle. `laravel-queue-consumer` lets each environment forward its queued jobs, over HTTP, to a shared hub application that actually executes them.

## How it works

This package is the **producer** side of the protocol only. It registers a `hub` queue connection: once selected via `QUEUE_CONNECTION=hub`, every `dispatch()` call in your application is serialized exactly like Laravel would for any other driver, then POSTed to your hub's `/api/jobs` endpoint. Nothing is executed locally.

A separate package, `laravel-queue-worker` (not yet published), is the hub-side consumer: it receives these HTTP requests and executes the job payloads using the Artisan command this package ships (`queue-consumer:run`). Without a hub application running that package, jobs dispatched through this connection are never executed — this package alone does not run your jobs.

## Installation

You can install the package via composer:

```bash
composer require jeffersongoncalves/laravel-queue-consumer
```

The package uses Laravel's auto-discovery, so the service provider is registered automatically.

### Publish Configuration

```bash
php artisan vendor:publish --tag=queue-consumer-config
```

This publishes `config/queue-consumer.php`:

```php
return [
    'hub_url' => env('QUEUE_CONSUMER_HUB_URL'),

    'token' => env('QUEUE_CONSUMER_TOKEN'),

    'slug' => env('QUEUE_CONSUMER_SLUG', basename(base_path())),

    'timeout' => env('QUEUE_CONSUMER_TIMEOUT', 5),
];
```

### Environment Setup

Add the following to your `.env` and switch your application's queue connection to `hub`:

```env
QUEUE_CONNECTION=hub

QUEUE_CONSUMER_HUB_URL=https://hub.internal.example
QUEUE_CONSUMER_TOKEN=some-shared-secret-token
QUEUE_CONSUMER_TIMEOUT=5
```

`QUEUE_CONSUMER_SLUG` is optional and defaults to `basename(base_path())` — the folder name of the current environment (e.g. `app-feature-1234`). No changes are required at any `dispatch()` call site; every job your application already dispatches will be forwarded to the hub as soon as `QUEUE_CONNECTION=hub` is set.

## Protocol

Every job is forwarded as a `POST` request to `{hub_url}/api/jobs`:

```json
{
    "slug": "app-feature-1234",
    "path": "/srv/environments/app-feature-1234",
    "queue": "default",
    "delay": 0,
    "payload": "<Laravel's own serialized job payload, untouched>"
}
```

The request carries an `X-Laravel-Queue-Token` header with the configured token. A successful hub response is `202` with `{"id": "<hub job id>"}`. Delivery is at-least-once — dedup is expected to happen hub-side.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security

This package must only be used to talk to a **trusted internal hub application**. Never point `hub_url` at an untrusted or public host — the token grants whoever holds it the ability to have arbitrary serialized job payloads executed on the hub. Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Limitations

- No local execution fallback: if the hub is unreachable, the job dispatch fails loudly (except for a small number of automatic retries on connection failure) — there is no local queue to fall back to.
- No supervisor process, no cron scheduling, and no retry policy beyond the thin connection-retry in the HTTP push are included.
- Designed for ephemeral review environments, not for production traffic.

## Credits

- [Jefferson Gonçalves](https://github.com/jeffersongoncalves)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
