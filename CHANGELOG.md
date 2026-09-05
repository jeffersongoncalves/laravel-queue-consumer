# Changelog

All notable changes to `laravel-queue-consumer` will be documented in this file.

## 1.0.0 - 2026-09-05

Initial release.

Registers a `hub` queue connection so an ephemeral review environment can dispatch jobs over HTTP to a central hub instead of running its own local queue worker, and ships the `queue-consumer:run` command that executes a returned payload through Laravel's own `SyncJob`/`CallQueuedHandler`, so job middleware still runs correctly (including `after_commit` semantics).

Companion package: [`laravel-queue-worker`](https://github.com/jeffersongoncalves/laravel-queue-worker), which receives and executes these jobs on the hub side.
