<?php

declare(strict_types=1);

namespace RoadRunner\Lock;

interface LockInterface
{
    /**
     * Lock a resource for exclusive access.
     *
     * Locks a resource so that it can be accessed by one process at a time. By default the call is non-blocking:
     * if the resource is already locked, it returns false immediately. Pass a positive $waitTTL to wait for the
     * lock to be released instead.
     *
     * @param non-empty-string $resource The name of the resource to be locked.
     * @param non-empty-string|null $id The lock ID. If not specified, a random UUID will be generated.
     * @param int|float|\DateInterval $ttl The time-to-live of the lock, in seconds. Defaults to 0 (forever).
     * @param int|float|\DateInterval $waitTTL How long to wait for the lock to become free before giving up, in seconds.
     *                                          Defaults to 0. With 0 the call is effectively non-blocking: the RoadRunner
     *                                          server caps the acquire window at defaultImmediateTimeout (1ms), so false
     *                                          is returned almost immediately when the resource is already locked. A
     *                                          positive value blocks for up to that duration, returning the lock id as
     *                                          soon as the lock is released, or false on timeout.
     * @return false|non-empty-string Returns lock ID if the lock was acquired successfully, false otherwise.
     */
    public function lock(
        string $resource,
        ?string $id = null,
        int|float|\DateInterval $ttl = 0,
        int|float|\DateInterval $waitTTL = 0,
    ): false|string;

    /**
     * Lock a resource for shared access.
     *
     * Locks a resource for shared access, allowing multiple processes to access the resource simultaneously.
     * When a resource is locked for shared access, other processes that attempt to lock the resource for exclusive access
     * will be blocked until all shared locks are released.
     *
     * @param non-empty-string $resource The name of the resource to be locked.
     * @param non-empty-string|null $id The lock ID. If not specified, a random UUID will be generated.
     * @param int|float|\DateInterval $ttl The time-to-live of the lock, in seconds. Defaults to 0 (forever).
     * @param int|float|\DateInterval $waitTTL How long to wait for the lock to become free before giving up, in seconds.
     *                                          Defaults to 0. With 0 the call is effectively non-blocking: the RoadRunner
     *                                          server caps the acquire window at defaultImmediateTimeout (1ms), so false
     *                                          is returned almost immediately when the resource is already locked. A
     *                                          positive value blocks for up to that duration, returning the lock id as
     *                                          soon as the lock is released, or false on timeout.
     * @return false|non-empty-string Returns lock ID if the lock was acquired successfully, false otherwise.
     */
    public function lockRead(
        string $resource,
        ?string $id = null,
        int|float|\DateInterval $ttl = 0,
        int|float|\DateInterval $waitTTL = 0,
    ): false|string;

    /**
     * Release an exclusive lock on a resource.
     *
     * Releases an exclusive lock or read lock on a resource that was previously acquired by a call to
     * lock() or lockRead(). The lock can only be released by the process that acquired it.
     *
     * @param non-empty-string $resource The name of the resource to be unlocked.
     * @param non-empty-string $id An identifier for the process that is releasing the lock.
     * @return bool Returns true if the lock was released successfully, false otherwise.
     */
    public function release(string $resource, string $id): bool;

    /**
     * Forcefully release all locks on a resource.
     *
     * Releases all locks on a resource, regardless of who acquired the locks. This should only be used
     * as a last resort, for example when a process that acquired a lock crashes and is no longer able to release the
     * lock.
     *
     * @param non-empty-string $resource The name of the resource to be unlocked
     * @return bool Returns true if all locks were released successfully, false otherwise
     */
    public function forceRelease(string $resource): bool;

    /**
     * Check if a resource is locked.
     *
     * Checks if a resource is currently locked and returns information about the lock.
     *
     * @param string $resource The name of the resource to check.
     * @param string|null $id An identifier for the process that is releasing the lock. If not specified, the lock
     *                       information will be returned regardless of who acquired the lock.
     * @return bool Returns true if the resource is locked, false otherwise.
     */
    public function exists(string $resource, ?string $id = null): bool;

    /**
     * Updates the time-to-live (TTL) for the locked resource.
     *
     * @param string $resource The name of the resource to update the TTL for.
     * @param string $id An identifier for the process that is releasing the lock.
     * @param int|float|\DateInterval $ttl The new TTL in seconds.
     * @return bool Returns true on success and false on failure.
     */
    public function updateTTL(string $resource, string $id, int|float|\DateInterval $ttl): bool;
}
