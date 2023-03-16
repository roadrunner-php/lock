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

final class LockTest extends TestCase
{
    private RPCInterface|MockInterface $rpc;
    private LockIdGeneratorInterface|MockInterface $idGenerator;
    private Lock $lock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rpc = m::mock(RPCInterface::class);

        $this->rpc->shouldReceive('withCodec')
            ->with(m::type(ProtobufCodec::class))
            ->once()
            ->andReturnSelf();

        $this->idGenerator = m::mock(LockIdGeneratorInterface::class);
        $this->lock = new Lock($this->rpc, $this->idGenerator);
    }

    /**
     * @dataProvider lockTypeDataProvider
     */
    public function testLock(
        string $method,
        string $callMethod,
        int|\DateInterval|\DateTimeInterface $ttl,
        int $expectedTtlSec,
        int|\DateInterval|\DateTimeInterface $wait,
        int $expectedWaitSec,
        bool $expectedResult = true,
    ): void {
        $this->idGenerator->shouldReceive('generate')->once()->andReturn('some-id');

        $this->rpc->shouldReceive('call')
            ->withArgs(function (string $method, Request $request, string $response) use (
                $expectedTtlSec,
                $expectedWaitSec,
                $callMethod
            ): bool {
                return $method === $callMethod
                    && $request->getResource() === 'resource'
                    && $request->getId() === 'some-id'
                    && $request->getTtl() === $expectedTtlSec
                    && $request->getWait() === $expectedWaitSec
                    && $response === Response::class;
            })
            ->andReturn(new Response(['ok' => $expectedResult]));

        if ($expectedResult) {
            $this->assertSame('some-id', $this->lock->$method('resource', $ttl, $wait));
        } else {
            $this->assertFalse($this->lock->$method('resource', $ttl, $wait));
        }
    }

    /**
     * @dataProvider resultDataProvider
     */
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

        $this->assertSame($result, $this->lock->release('resource', 'some-id'));
    }

    /**
     * @dataProvider resultDataProvider
     */
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

        $this->assertSame($result, $this->lock->forceRelease('resource'));
    }

    /**
     * @dataProvider resultDataProvider
     */
    public function testExists(bool $result): void
    {
        $this->rpc->shouldReceive('call')
            ->once()
            ->withArgs(function (string $method, Request $request, string $response): bool {
                return $method === 'lock.Exists'
                    && $request->getResource() === 'resource'
                    && $request->getId() === ''
                    && $response === Response::class;
            })
            ->andReturn(new Response(['ok' => $result]));

        $this->assertSame($result, $this->lock->exists('resource'));
    }

    /**
     * @dataProvider updateTTLDataProvider
     */
    public function testUpdateTTL($ttl, int $expectedTtl, bool $result): void
    {
        $this->rpc->shouldReceive('call')
            ->once()
            ->withArgs(function (string $method, Request $request, string $response) use($expectedTtl): bool {
                return $method === 'lock.UpdateTTL'
                    && $request->getResource() === 'resource'
                    && $request->getId() === 'some-id'
                    && $request->getTtl() === $expectedTtl
                    && $response === Response::class;
            })
            ->andReturn(new Response(['ok' => $result]));

        $this->assertSame($result, $this->lock->updateTTL('resource', 'some-id', $ttl));
    }

    /**
     * @dataProvider resultDataProvider
     */
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

        $this->assertSame($result, $this->lock->exists('resource', 'some-id'));
    }

    public function lockTypeDataProvider(): \Generator
    {
        foreach ($this->lockDataProvider() as $name => $data) {
            foreach ([true, false] as $result) {
                yield 'lock: ' . $name . ' | ' . \var_export($result, true) => ['lock', 'lock.Lock', ...$data, $result];
                yield 'read-lock: ' . $name . ' | ' . \var_export($result, true) => [
                    'lockRead',
                    'lock.LockRead',
                    ...$data,
                    $result,
                ];
            }
        }
    }

    public function updateTTLDataProvider()
    {
        foreach ($this->lockDataProvider() as $name => $data) {
            foreach ([true, false] as $result) {
                yield $name . ' | ' . \var_export($result, true) => [$data[0], $data[1], $result];
            }
        }
    }

    public function lockDataProvider(): \Generator
    {
        yield 'int' => [10, 10, 8, 8,];

        yield 'date-interval' => [
            new \DateInterval('PT10S'),
            10,
            new \DateInterval('PT9S'),
            9,
        ];
    }

    public function resultDataProvider()
    {
        return [
            [true],
            [false],
        ];
    }
}