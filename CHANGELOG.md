# Changelog

All notable changes to `laravel-queue-consumer` will be documented in this file.

## 1.1.0 - 2026-09-15

### Added

- Session values can now be carried into the process that runs the job (#7). The job runs in a fresh process with an empty session, so applications that keep tenant data there had to pass it through every job class by hand. List the keys in `queue-consumer.session` and they travel in the payload and are restored before the job and its middleware run:
  
  ```php
  // config/queue-consumer.php
  'session' => ['tenant', 'db_host', 'db_port'],
  
  ```
  The default empty list changes nothing for existing installs. Laravel's own Context needs no configuration — it is already carried in the payload and rehydrated on `JobProcessing`.
  

### Security

- Carrying session values requires an `https` `hub_url`. With the list filled and a plain `http://` hub, the dispatch throws instead of putting application data on the wire in cleartext (CWE-319). A hub on `localhost` / `127.0.0.1` / `::1` never leaves the machine and is still allowed without TLS, and an `http` hub with an empty session list keeps working as before.

**Full Changelog**: https://github.com/jeffersongoncalves/laravel-queue-consumer/compare/1.0.1...1.1.0

## 1.0.1 - 2026-09-15

### Fixed

- `queue-consumer:run` now raises the queue lifecycle events around job execution (#5). `JobProcessing`, `JobProcessed` and `JobExceptionOccurred` are dispatched with the `hub` connection name, so `Queue::before()` / `Queue::after()` callbacks — context restore, metrics, log correlation ids, per-tenant setup — run inside the ephemeral environment like they do under a regular worker. `JobFailed` keeps being dispatched by `Job::fail()` itself on `--last-attempt`, matching `Illuminate\Queue\Worker`.

**Full Changelog**: https://github.com/jeffersongoncalves/laravel-queue-consumer/compare/1.0.0...1.0.1

## 1.0.0 - 2026-09-05

Initial release.

Registers a `hub` queue connection so an ephemeral review environment can dispatch jobs over HTTP to a central hub instead of running its own local queue worker, and ships the `queue-consumer:run` command that executes a returned payload through Laravel's own `SyncJob`/`CallQueuedHandler`, so job middleware still runs correctly (including `after_commit` semantics).

Companion package: [`laravel-queue-worker`](https://github.com/jeffersongoncalves/laravel-queue-worker), which receives and executes these jobs on the hub side.
