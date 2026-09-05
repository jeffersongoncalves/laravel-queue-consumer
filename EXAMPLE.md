# End-to-end example

This walks through the two packages talking to each other: a **hub** app (running [`laravel-queue-worker`](https://github.com/jeffersongoncalves/laravel-queue-worker)) and one **ephemeral environment** app (running this package, `laravel-queue-consumer`). It assumes both repos are checked out as siblings on disk:

```
your-projects/
├── laravel-queue-worker/
└── laravel-queue-consumer/   (you are here)
```

Everything runs inside one throwaway container — in real deployments the hub and every environment already share the same host filesystem, so a single container is the closest thing to that without provisioning real servers.

## 0. Start the container

```bash
docker compose -f example/docker-compose.yml up -d
docker compose -f example/docker-compose.yml exec demo bash
```

Inside the container, install Composer and the extensions Laravel needs (the base image only ships the bare minimum):

```bash
apt-get update && apt-get install -y unzip libzip-dev git
docker-php-ext-install mbstring zip
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
```

## 1. Scaffold the two apps

```bash
cd /workspace/apps
composer create-project laravel/laravel hub --prefer-dist --no-interaction
composer create-project laravel/laravel app-feature-1234 --prefer-dist --no-interaction
```

`app-feature-1234` stands in for one ephemeral review environment — its directory name is also its `slug`.

## 2. Wire the hub

```bash
cd /workspace/apps/hub
composer config repositories.queue-worker path /workspace/packages/laravel-queue-worker
composer require jeffersongoncalves/laravel-queue-worker:@dev --no-interaction
php artisan vendor:publish --tag=queue-worker-config
```

`.env`:

```
QUEUE_CONNECTION=database
QUEUE_WORKER_TOKEN=demo-token
QUEUE_WORKER_ALLOWED_ROOT=/workspace/apps
```

Map this container's own PHP binary in `config/queue-worker.php` (`php -v` to confirm the version):

```php
'php_binary_map' => [
    '8.3' => '/usr/local/bin/php',
],
```

```bash
php artisan migrate
php artisan serve --host=0.0.0.0 --port=8000 &
php artisan queue:work &
```

## 3. Wire the environment

```bash
cd /workspace/apps/app-feature-1234
composer config repositories.queue-consumer path /workspace/packages/laravel-queue-consumer
composer require jeffersongoncalves/laravel-queue-consumer:@dev --no-interaction
php artisan vendor:publish --tag=queue-consumer-config
```

`.env`:

```
QUEUE_CONNECTION=hub
QUEUE_CONSUMER_HUB_URL=http://127.0.0.1:8000
QUEUE_CONSUMER_TOKEN=demo-token
QUEUE_CONSUMER_SLUG=app-feature-1234
```

## 4. Dispatch a job from the environment

```bash
php artisan tinker --execute="dispatch(fn () => file_put_contents('/tmp/ran-from-app-feature-1234', now()));"
```

## 5. Confirm

```bash
cat /tmp/ran-from-app-feature-1234
```

A timestamp there proves the job travelled: `app-feature-1234` POSTed its own serialized job to the hub over HTTP; the hub queued it and, once `queue:work` picked it up, spawned `php artisan queue-consumer:run` **inside `/workspace/apps/app-feature-1234`** — the closure ran with the environment's own process and autoloader, not the hub's. The hub itself never touched the closure's contents.

## Cleanup

```bash
exit
docker compose -f example/docker-compose.yml down -v
```

## Notes

- `demo-token` and `QUEUE_WORKER_ALLOWED_ROOT=/workspace/apps` are demo-only values. See the **Security** section in each package's README before configuring a real hub.
- If a step fails with a missing PHP extension, install it with `docker-php-ext-install <name>` and retry — the base image is intentionally bare.
