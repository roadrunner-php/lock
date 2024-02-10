<a href="https://roadrunner.dev" target="_blank">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://github.com/roadrunner-server/.github/assets/8040338/e6bde856-4ec6-4a52-bd5b-bfe78736c1ff">
    <img align="center" src="https://github.com/roadrunner-server/.github/assets/8040338/040fb694-1dd3-4865-9d29-8e0748c2c8b8">
  </picture>
</a>

# RoadRunner locks

[![PHP Version Require](https://poser.pugx.org/roadrunner-php/lock/require/php)](https://packagist.org/packages/roadrunner-php/lock)
[![Latest Stable Version](https://poser.pugx.org/roadrunner-php/lock/v/stable)](https://packagist.org/packages/roadrunner-php/lock)
[![phpunit](https://github.com/roadrunner-php/lock/actions/workflows/phpunit.yml/badge.svg)](https://github.com/roadrunner-php/lock/actions)
[![psalm](https://github.com/roadrunner-php/lock/actions/workflows/psalm.yml/badge.svg)](https://github.com/roadrunner-php/lock/actions)
[![Codecov](https://codecov.io/gh/roadrunner-php/lock/branch/1.x/graph/badge.svg)](https://codecov.io/gh/roadrunner-php/lock/)
[![Total Downloads](https://poser.pugx.org/roadrunner-php/lock/downloads)](https://packagist.org/packages/roadrunner-php/lock)
<a href="https://discord.gg/spiralphp"><img src="https://img.shields.io/badge/discord-chat-magenta.svg"></a>

This package provides a PHP integration package for the RoadRunner Lock plugin, which allows for easy management of
distributed locks in PHP applications. The plugin provides a fast, lightweight, and reliable way to acquire, release,
and manage locks in a distributed environment, making it ideal for use in high-traffic web applications and
microservices.

## Requirements

Make sure that your server is configured with following PHP version and extensions:

- PHP 8.1+

## Installation

You can install the package via composer:

```bash
composer require roadrunner-php/lock
```

## Usage

```php
use RoadRunner\Lock\Lock;
use Spiral\Goridge\RPC\RPC;

require __DIR__ . '/vendor/autoload.php';

$lock = new Lock(RPC::create('tcp://127.0.0.1:6001'));
```

### Acquire lock

Locks a resource so that it can be accessed by one process at a time. When a resource is locked, other processes that
attempt to lock the same resource will be blocked until the lock is released.

```php
$id = $lock->lock('pdf:create');

// Acquire lock with ttl - 10 seconds
$id = $lock->lock('pdf:create', ttl: 10);
// or
$id = $lock->lock('pdf:create', ttl: new \DateInterval('PT10S'));

// Acquire lock and wait 5 seconds until lock will be released
$id = $lock->lock('pdf:create', wait: 5);
// or
$id = $lock->lock('pdf:create', wait: new \DateInterval('PT5S'));

// Acquire lock with id - 14e1b600-9e97-11d8-9f32-f2801f1b9fd1
$id = $lock->lock('pdf:create', id: '14e1b600-9e97-11d8-9f32-f2801f1b9fd1');
```

### Acquire read lock

Locks a resource for shared access, allowing multiple processes to access the resource simultaneously. When a resource
is locked for shared access, other processes that attempt to lock the resource for exclusive access will be blocked
until all shared locks are released.

```php
$id = $lock->lockRead('pdf:create', ttl: 10);
// or
$id = $lock->lockRead('pdf:create', ttl: new \DateInterval('PT10S'));

// Acquire lock and wait 5 seconds until lock will be released
$id = $lock->lockRead('pdf:create', wait: 5);
// or
$id = $lock->lockRead('pdf:create', wait: new \DateInterval('PT5S'));

// Acquire lock with id - 14e1b600-9e97-11d8-9f32-f2801f1b9fd1
$id = $lock->lockRead('pdf:create', id: '14e1b600-9e97-11d8-9f32-f2801f1b9fd1');
```

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

Checks if a resource is currently locked and returns information about the lock.

```php
$status = $lock->exists('pdf:create');
if($status) {
    // Lock exists
} else {
    // Lock not exists
}
```

### Update TTL

Updates the time-to-live (TTL) for the locked resource.

```php
// Add 10 seconds to lock ttl
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

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
