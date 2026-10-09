<?php

declare(strict_types=1);

namespace RoadRunner\Lock\Tests;

use Mockery as m;
use Mockery\MockInterface;
use RoadRunner\Lock\DTO\V1BETA1\Request;
use RoadRunner\Lock\DTO\V1BETA1\Response;
use RoadRunner\Lock\Lock;
use RoadRunner\Lock\LockIdGeneratorInterface;
use Spiral\Goridge\RPC\Codec\ProtobufCodec;
use Spiral\Goridge\RPC\RPCInterface;
use Testo\Assert;
use Testo\Assert\ExpectException;
use Testo\Data\DataProvider;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
final class LockTest
{
    private RPCInterface|MockInterface $rpc;
    private LockIdGeneratorInterface|MockInterface $idGenerator;
    private Lock $lock;

    public static function lockTypeDataProvider(): \Generator
    {
        foreach (self::lockDataProvider() as $name => $data) {
            foreach ([true, false] as $result) {
                foreach (['id1', null] as $id) {
                    yield 'lock: ' . $name . ' | ' . $id . ' | ' . \var_export($result, true) => [
                        'lock',
                        'lock.Lock',
                        ...$data,
                        $result,
                        $id,
                    ];

                    yield 'read-lock: ' . $name . ' | ' . $id . ' | ' . \var_export($result, true) => [
                        'lockRead',
                        'lock.LockRead',
                        ...$data,
                        $result,
                        $id,
                    ];
                }
            }
        }
    }

    public static function updateTTLDataProvider(): \Traversable
    {
        foreach (self::lockDataProvider() as $name => $data) {
            foreach ([true, false] as $result) {
                yield $name . ' | ' . \var_export($result, true) => [$data[0], $data[1], $result];
            }
        }
    }

    public static function lockDataProvider(): \Generator
    {
        yield 'int' => [10, 10_000_000, 8, 8_000_000,];

        yield 'float' => [0.000_01, 10, 0.000_004, 4,];

        yield 'date-interval' => [
            new \DateInterval('PT10S'),
            10_000_000,
            new \DateInterval('PT9S'),
            9_000_000,
        ];
    }

    public static function resultDataProvider(): \Traversable
    {
        yield [true];
        yield [false];
    }

    #[DataProvider('lockTypeDataProvider')]
    public function testLock(
        string $method,
        string $callMethod,
        int|float|\DateInterval|\DateTimeInterface $ttl,
        int $expectedTtlMicroseconds,
        int|float|\DateInterval|\DateTimeInterface $wait,
        int $expectedWaitSec,
        bool $expectedResult = true,
        ?string $id = null,
    ): void {
        if ($id === null) {
            $this->idGenerator->shouldReceive('generate')->once()->andReturn('some-id');
        }

        $this->rpc->shouldReceive('call')
            ->withArgs(function (string $method, Request $request, string $response) use (
                $expectedTtlMicroseconds,
                $expectedWaitSec,
                $callMethod,
                $id
            ): bool {
                return $method === $callMethod
                    && $request->getResource() === 'resource'
                    && $request->getId() === ($id === null ? 'some-id' : $id)
                    && $request->getTtl() === $expectedTtlMicroseconds
                    && $request->getWait() === $expectedWaitSec
                    && $response === Response::class;
            })
            ->andReturn(new Response(['ok' => $expectedResult]));

        $result = $this->lock->$method(resource: 'resource', id: $id, ttl: $ttl, waitTTL: $wait);
        if ($expectedResult) {
            Assert::same($result, ($id === null ? 'some-id' : $id));
        } else {
            Assert::false($result);
        }
    }

    #[DataProvider('resultDataProvider')]
    public function testRelease(bool $result): void
    {
        $this->rpc->shouldReceive('call')
            ->once()
            ->withArgs(function (string $method, Request $request, string $response): bool {
                return $method === 'lock.Release'
                    && $request->getResource() === 'resource'
                    && $request->getId() === 'some-id'
                    && $response === Response::class;
            })
            ->andReturn(new Response(['ok' => $result]));

        Assert::same($this->lock->release('resource', 'some-id'), $result);
    }

    #[DataProvider('resultDataProvider')]
    public function testForceRelease(bool $result): void
    {
        $this->rpc->shouldReceive('call')
            ->once()
            ->withArgs(function (string $method, Request $request, string $response): bool {
                return $method === 'lock.ForceRelease'
                    && $request->getResource() === 'resource'
                    && $request->getId() === ''
                    && $response === Response::class;
            })
            ->andReturn(new Response(['ok' => $result]));

        Assert::same($this->lock->forceRelease('resource'), $result);
    }

    #[DataProvider('resultDataProvider')]
    public function testExists(bool $result): void
    {
        $this->rpc->shouldReceive('call')
            ->once()
            ->withArgs(function (string $method, Request $request, string $response): bool {
                return $method === 'lock.Exists'
                    && $request->getResource() === 'resource'
                    && $request->getId() === '*'
                    && $response === Response::class;
            })
            ->andReturn(new Response(['ok' => $result]));

        Assert::same($this->lock->exists('resource'), $result);
    }

    #[DataProvider('updateTTLDataProvider')]
    public function testUpdateTTL($ttl, int $expectedTtl, bool $result): void
    {
        $this->rpc->shouldReceive('call')
            ->once()
            ->withArgs(function (string $method, Request $request, string $response) use ($expectedTtl): bool {
                return $method === 'lock.UpdateTTL'
                    && $request->getResource() === 'resource'
                    && $request->getId() === 'some-id'
                    && $request->getTtl() === $expectedTtl
                    && $response === Response::class;
            })
            ->andReturn(new Response(['ok' => $result]));

        Assert::same($this->lock->updateTTL('resource', 'some-id', $ttl), $result);
    }

    #[DataProvider('resultDataProvider')]
    public function testExistsWithId(bool $result): void
    {
        $this->rpc->shouldReceive('call')
            ->once()
            ->withArgs(function (string $method, Request $request, string $response): bool {
                return $method === 'lock.Exists'
                    && $request->getResource() === 'resource'
                    && $request->getId() === 'some-id'
                    && $response === Response::class;
            })
            ->andReturn(new Response(['ok' => $result]));

        Assert::same($this->lock->exists('resource', 'some-id'), $result);
    }

    #[ExpectException(\LogicException::class)]
    public function testLockNegativeTtl(): void
    {
        $this->lock->lock('resource', 'uuid', -300);
    }

    #[ExpectException(\LogicException::class)]
    public function testLockNegativeWaitTtl(): void
    {
        $this->lock->lock('resource', 'uuid', 0, -300);
    }

    #[ExpectException(\LogicException::class)]
    public function testLockReadNegativeTtl(): void
    {
        $this->lock->lock('resource', 'uuid', -300);
    }

    #[ExpectException(\LogicException::class)]
    public function testLockReadNegativeWaitTtl(): void
    {
        $this->lock->lockRead('resource', 'uuid', 0, -300);
    }

    #[ExpectException(\LogicException::class)]
    public function testUpdateNegativeWaitTtl(): void
    {
        $this->lock->updateTTL('resource', 'uuid', -300);
    }

    #[BeforeTest]
    protected function setUp(): void
    {
        $this->rpc = m::mock(RPCInterface::class);

        $this->rpc->shouldReceive('withCodec')
            ->with(m::type(ProtobufCodec::class))
            ->once()
            ->andReturnSelf();

        $this->idGenerator = m::mock(LockIdGeneratorInterface::class);
        $this->lock = new Lock($this->rpc, $this->idGenerator);
    }
}
