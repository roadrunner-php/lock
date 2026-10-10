<p align="center">
    <a href="https://roadrunner.dev"><picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://github.com/roadrunner-server/.github/assets/8040338/e6bde856-4ec6-4a52-bd5b-bfe78736c1ff">
        <img alt="RoadRunner" src="https://github.com/roadrunner-server/.github/assets/8040338/040fb694-1dd3-4865-9d29-8e0748c2c8b8" style="width: 6in; display: block">
    </picture></a>
</p>

<p align="center">Distributed locks for PHP applications powered by RoadRunner</p>

<div align="center">

[![Documentation](https://img.shields.io/badge/Documentation-blue?style=for-the-badge&logo=gitbook&logoColor=white)](https://docs.roadrunner.dev/docs/plugins/locks)
[![Sponsor](https://img.shields.io/static/v1?style=for-the-badge&label=&message=Sponsor&logo=githubsponsors&logoColor=white&color=%23EA4AAA)](https://github.com/sponsors/roadrunner-server)

[![Psalm Level](https://shepherd.dev/github/roadrunner-php/lock/level.svg)](https://shepherd.dev/github/roadrunner-php/lock)
[![Type Coverage](https://shepherd.dev/github/roadrunner-php/lock/coverage.svg)](https://shepherd.dev/github/roadrunner-php/lock)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Froadrunner-php%2Flock%2F1.x)](https://dashboard.stryker-mutator.io/reports/github.com/roadrunner-php/lock/1.x)

</div>

<br />

This package is a PHP client for the [RoadRunner Lock plugin](https://docs.roadrunner.dev/docs/plugins/locks). It lets
your workers acquire, release and manage exclusive and shared (read) locks on named resources, shared across all
processes connected to the RoadRunner server.

## Get Started

### Requirements

Make sure that your server is configured with following PHP version and extensions:

- PHP 8.2+
- RoadRunner 3.0+ with the `lock` plugin enabled

### Installation

```bash
composer require roadrunner/lock
```

[![PHP](https://img.shields.io/packagist/php-v/roadrunner/lock.svg?style=flat-square&logo=php)](https://packagist.org/packages/roadrunner/lock)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/roadrunner/lock.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/roadrunner/lock)
[![License](https://img.shields.io/packagist/l/roadrunner/lock.svg?style=flat-square)](LICENSE)
[![Total Downloads](https://img.shields.io/packagist/dt/roadrunner/lock.svg?style=flat-square)](https://packagist.org/packages/roadrunner/lock/stats)

### Configuration

The client talks to RoadRunner over RPC, so enable the `rpc` section in `.rr.yaml`. The Lock plugin uses the in-memory
backend by default; see the [plugin documentation](https://docs.roadrunner.dev/docs/plugins/locks) to share locks
between RoadRunner instances through Redis.

```yaml
version: "3"

rpc:
  listen: tcp://127.0.0.1:6001
```

### Your First Lock

Create a `Lock` instance with an RPC connection to the RoadRunner server, then acquire and release a lock:

```php
use RoadRunner\Lock\Lock;
use Spiral\Goridge\RPC\RPC;

require __DIR__ . '/vendor/autoload.php';

$lock = new Lock(RPC::create('tcp://127.0.0.1:6001'));

$id = $lock->lock('pdf:create', ttl: 10);
if ($id === false) {
    // The resource is locked by another process
    return;
}

try {
    // Do the work
} finally {
    $lock->release('pdf:create', $id);
}
```

## Usage

### Acquire lock

Locks a resource so that it can be accessed by one process at a time.

By default the call is **non-blocking**: if the resource is already locked, it returns `false` almost immediately
(the RoadRunner server caps the default `waitTTL` window at `1ms`). Pass a positive `waitTTL` to block until the lock
is released — the call then returns the lock id as soon as the lock becomes free, or `false` when the `waitTTL`
timeout elapses.

```php
$id = $lock->lock('pdf:create');

// Acquire lock with ttl - 10 seconds
$id = $lock->lock('pdf:create', ttl: 10);
// or
$id = $lock->lock('pdf:create', ttl: new \DateInterval('PT10S'));

// Acquire lock and wait 5 seconds until lock will be released
$id = $lock->lock('pdf:create', waitTTL: 5);
// or
$id = $lock->lock('pdf:create', waitTTL: new \DateInterval('PT5S'));

// Acquire lock with id - 14e1b600-9e97-11d8-9f32-f2801f1b9fd1
$id = $lock->lock('pdf:create', id: '14e1b600-9e97-11d8-9f32-f2801f1b9fd1');
```

### Acquire read lock

Locks a resource for shared access, allowing multiple processes to access the resource simultaneously. When a resource
is locked for shared access, other processes that attempt to lock the resource for exclusive access will fail to do so
while any shared lock is held.

As with `lock()`, the `waitTTL` parameter is non-blocking by default (`false` is returned almost immediately, within
the server's `1ms` window); pass a positive `waitTTL` to block for up to that duration for the lock to become available.

```php
$id = $lock->lockRead('pdf:create', ttl: 10);
// or
$id = $lock->lockRead('pdf:create', ttl: new \DateInterval('PT10S'));

// Acquire lock and wait 5 seconds until lock will be released
$id = $lock->lockRead('pdf:create', waitTTL: 5);
// or
$id = $lock->lockRead('pdf:create', waitTTL: new \DateInterval('PT5S'));

// Acquire lock with id - 14e1b600-9e97-11d8-9f32-f2801f1b9fd1
$id = $lock->lockRead('pdf:create', id: '14e1b600-9e97-11d8-9f32-f2801f1b9fd1');
```

### Lock parameters

Both `lock()` and `lockRead()` accept the same arguments:

| Parameter  | Type                            | Default       | Description |
|------------|---------------------------------|---------------|-------------|
| `resource` | `non-empty-string`              | —             | Name of the resource to lock. |
| `id`       | `non-empty-string`\|`null`      | `null`        | Lock owner id. When omitted a random UUID is generated. Keep it — the same `id` must be passed to `release()`. |
| `ttl`      | `int`\|`float`\|`DateInterval`  | `0` (forever) | Lock lifetime, in seconds. When it elapses the lock is released automatically; `0` means it never expires on its own. |
| `waitTTL`  | `int`\|`float`\|`DateInterval`  | `0` (~1ms)    | How long to wait for the lock to become free, in seconds. `0` is effectively non-blocking — the server caps it at `1ms`, so `false` is returned almost immediately when the resource is already locked. A positive value blocks for up to that duration, then returns `false` on timeout. |

Both methods return the lock **id** (`non-empty-string`) when the lock is acquired, or `false` when it is not (the resource stayed busy until the `waitTTL` window elapsed).

> `ttl` and `waitTTL` are expressed in **seconds** (`int` or `float`), or as a `DateInterval`.

### Release lock

Releases an exclusive lock or read lock on a resource that was previously acquired by a call to `lock()`
or `lockRead()`.

```php
// Release lock after task is done.
$lock->release('pdf:create', $id);

// Force release lock
$lock->forceRelease('pdf:create');
```

### Check lock

Checks if a resource is currently locked. Pass a lock id to check whether that particular lock is held.

```php
if ($lock->exists('pdf:create')) {
    // The resource is locked
}

if ($lock->exists('pdf:create', $id)) {
    // The lock with this id is held
}
```

### Update TTL

Updates the time-to-live (TTL) for the locked resource.

```php
// Set the lock ttl to 10 seconds
$lock->updateTTL('pdf:create', $id, 10);
// or
$lock->updateTTL('pdf:create', $id, new \DateInterval('PT10S'));
```

## Testing

```bash
composer test
```

## Credits

- [butschster](https://github.com/butschster)
