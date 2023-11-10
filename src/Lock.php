<?php

declare(strict_types=1);

namespace RoadRunner\Lock;

use DateInterval;
use RoadRunner\Lock\DTO\V1BETA1\{
    Request, Response
};
use Spiral\Goridge\RPC\Codec\ProtobufCodec;
use Spiral\Goridge\RPC\RPCInterface;

final class Lock implements LockInterface
{
    private RPCInterface $rpc;

    public function __construct(
        RPCInterface $rpc,
        private readonly LockIdGeneratorInterface $identityGenerator = new UuidLockIdGenerator(),
    ) {
        $this->rpc = $rpc->withCodec(new ProtobufCodec());
    }

    /**
     * Lock a resource for exclusive access
     *
     * Locks a resource so that it can be accessed by one process at a time. When a resource is locked,
     * other processes that attempt to lock the same resource will be blocked until the lock is released.
     *
     * @param non-empty-string $resource The name of the resource to be locked.
     * @param non-empty-string|null $id The lock ID. If not specified, a random UUID will be generated.
     * @param int|float|DateInterval $ttl The time-to-live of the lock, in seconds. Defaults to 0 (forever).
     * @param int|float|DateInterval $waitTTL How long to wait to acquire lock until returning false.
     * @return false|non-empty-string Returns lock ID if the lock was acquired successfully, false otherwise.
     *
     * @throws \InvalidArgumentException If ttl is negative.
     */
    public function lock(
        string $resource,
        ?string $id = null,
        int|float|DateInterval $ttl = 0,
        int|float|DateInterval $waitTTL = 0,
    ): false|string {
        $request = new Request();
        $request->setResource($resource);
        $request->setId($id ??= $this->identityGenerator->generate());
        $request->setTtl($this->convertTimeToMicroseconds($ttl));
        $request->setWait($this->convertTimeToMicroseconds($waitTTL));

        $response = $this->call('lock.Lock', $request);

        return $response->getOk() ? $id : false;
    }

    /**
     * Lock a resource for shared access
     *
     * Locks a resource for shared access, allowing multiple processes to access the resource simultaneously.
     * When a resource is locked for shared access, other processes that attempt to lock the resource for exclusive access
     * will be blocked until all shared locks are released.
     *
     * @param non-empty-string $resource The name of the resource to be locked.
     * @param non-empty-string|null $id The lock ID. If not specified, a random UUID will be generated.
     * @param int|float|DateInterval $ttl The time-to-live of the lock, in seconds. Defaults to 0 (forever).
     * @param int|float|DateInterval $waitTTL How long to wait to acquire lock until returning false.
     * @return false|non-empty-string Returns lock ID if the lock was acquired successfully, false otherwise.
     *
     * @throws \InvalidArgumentException If ttl is negative.
     */
    public function lockRead(
        string $resource,
        ?string $id = null,
        int|float|DateInterval $ttl = 0,
        int|float|DateInterval $waitTTL = 0,
    ): false|string {
        $request = new Request();
        $request->setResource($resource);
        $request->setId($id ??= $this->identityGenerator->generate());
        $request->setTtl($this->convertTimeToMicroseconds($ttl));
        $request->setWait($this->convertTimeToMicroseconds($waitTTL));

        $response = $this->call('lock.LockRead', $request);

        return $response->getOk() ? $id : false;
    }

    /**
     * Release an exclusive lock on a resource
     *
     * Releases an exclusive lock or read lock on a resource that was previously acquired by a call to
     * lock() or lockRead(). The lock can only be released by the process that acquired it.
     *
     * @param non-empty-string $resource The name of the resource to be unlocked.
     * @param non-empty-string $id An identifier for the process that is releasing the lock.
     * @return bool Returns true if the lock was released successfully, false otherwise.
     */
    public function release(string $resource, string $id): bool
    {
        $request = new Request();
        $request->setResource($resource);
        $request->setId($id);

        $response = $this->call('lock.Release', $request);

        return $response->getOk();
    }

    /**
     * Forcefully release all locks on a resource
     *
     * Releases all locks on a resource, regardless of who acquired the locks. This should only be used
     * as a last resort, for example when a process that acquired a lock crashes and is no longer able to release the
     * lock.
     *
     * @param non-empty-string $resource The name of the resource to be unlocked
     * @return bool Returns true if all locks were released successfully, false otherwise
     */
    public function forceRelease(string $resource): bool
    {
        $request = new Request();
        $request->setResource($resource);

        $response = $this->call('lock.ForceRelease', $request);

        return $response->getOk();
    }

    /**
     * Check if a resource is locked
     *
     * Checks if a resource is currently locked and returns information about the lock.
     *
     * @param string $resource The name of the resource to check.
     * @param string|null $id An identifier for the process that is releasing the lock. If not specified, the lock
     *                       information will be returned regardless of who acquired the lock.
     * @return bool Returns true if the resource is locked, false otherwise.
     */
    public function exists(string $resource, ?string $id = null): bool
    {
        $request = new Request();
        $request->setResource($resource);
        $request->setId($id ?? '*');

        $response = $this->call('lock.Exists', $request);

        return $response->getOk();
    }

    /**
     * Updates the time-to-live (TTL) for the locked resource.
     *
     * @param string $resource The name of the resource to update the TTL for.
     * @param string $id An identifier for the process that is releasing the lock.
     * @param int|float|DateInterval $ttl The new TTL in seconds.
     * @return bool Returns true on success and false on failure.
     *
     * @throws \InvalidArgumentException If ttl is negative.
     */
    public function updateTTL(string $resource, string $id, int|float|DateInterval $ttl): bool
    {
        $request = new Request();
        $request->setResource($resource);
        $request->setId($id);
        $request->setTtl($this->convertTimeToMicroseconds($ttl));

        $response = $this->call('lock.UpdateTTL', $request);

        return $response->getOk();
    }

    private function convertTimeToMicroseconds(int|float|DateInterval $ttl): int
    {
        if ($ttl instanceof DateInterval) {
            return (int) \round((int)$ttl->format('%s') * 1_000_000);
        }

            \assert($ttl >= 0, 'TTL must be positive');

        return (int) \round($ttl * 1_000_000);
    }

    /**
     * Make an RPC call to the RoadRunner server.
     *
     * @param non-empty-string $method
     * @param Request $request
     * @return Response
     */
    private function call(string $method, Request $request): Response
    {
        $response = $this->rpc->call($method, $request, Response::class);
        \assert($response instanceof Response);

        return $response;
    }
}
