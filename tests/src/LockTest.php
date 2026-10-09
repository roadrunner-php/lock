<?php

declare(strict_types=1);

namespace RoadRunner\Lock\Tests;

use Mockery as m;
use Mockery\MockInterface;
use Ramsey\Uuid\Uuid;
use RoadRunner\Lock\DTO\V1BETA1\Request;
use RoadRunner\Lock\DTO\V1BETA1\Response;
use RoadRunner\Lock\Lock;
use RoadRunner\Lock\LockIdGeneratorInterface;
use Spiral\Goridge\RPC\Codec\ProtobufCodec;
use Spiral\Goridge\RPC\RPCInterface;
use Testo\Assert;
use Testo\Assert\ExpectException;
use Testo\Core\Exception\SkipTest;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Lifecycle\BeforeTest;
use Testo\Skip;
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

    public static function negativeTimeDataProvider(): \Generator
    {
        yield 'lock ttl' => ['lock', ['resource', 'uuid', -300]];
        yield 'lock waitTTL' => ['lock', ['resource', 'uuid', 0, -300]];
        yield 'lockRead ttl' => ['lockRead', ['resource', 'uuid', -300]];
        yield 'lockRead waitTTL' => ['lockRead', ['resource', 'uuid', 0, -300]];
        yield 'updateTTL ttl' => ['updateTTL', ['resource', 'uuid', -300]];
    }

    public static function compoundIntervalDataProvider(): \Generator
    {
        yield 'minutes and seconds' => [new \DateInterval('PT1M30S'), 90_000_000];
        yield 'hours' => [new \DateInterval('PT1H'), 3_600_000_000];
        yield 'days' => [new \DateInterval('P1D'), 86_400_000_000];

        $interval = new \DateInterval('PT1S');
        $interval->f = 0.5;
        yield 'fraction of a second' => [$interval, 1_500_000];
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

    #[DataProvider('negativeTimeDataProvider')]
    public function testNegativeTimeFailsAssertion(string $method, array $args): void
    {
        if (\ini_get('zend.assertions') !== '1') {
            throw new SkipTest('The negative time check is an assert(), inactive unless zend.assertions=1');
        }

        Expect::exception(\AssertionError::class);
        $this->lock->$method(...$args);
    }

    #[Skip('Bug: the docblock promises InvalidArgumentException for a negative ttl, but the check is an assert(); with assertions off the negative value is sent to RoadRunner')]
    #[DataProvider('negativeTimeDataProvider')]
    #[ExpectException(\InvalidArgumentException::class)]
    public function testNegativeTimeThrowsInvalidArgumentException(string $method, array $args): void
    {
        $this->lock->$method(...$args);
    }

    #[Skip('Bug: convertTimeToMicroseconds() reads only the seconds field of a DateInterval (format("%s")), dropping minutes, hours, days and microseconds')]
    #[DataProvider('compoundIntervalDataProvider')]
    public function testCompoundDateIntervalTtl(\DateInterval $ttl, int $expectedTtl): void
    {
        $this->rpc->shouldReceive('call')
            ->once()
            ->withArgs(static fn(string $method, Request $request): bool => $request->getTtl() === $expectedTtl)
            ->andReturn(new Response(['ok' => true]));

        Assert::true($this->lock->updateTTL('resource', 'some-id', $ttl));
    }

    public function testLockGeneratesUuidByDefault(): void
    {
        $rpc = m::mock(RPCInterface::class);
        $rpc->shouldReceive('withCodec')->andReturnSelf();
        $rpc->shouldReceive('call')
            ->once()
            ->withArgs(static fn(string $method, Request $request): bool => Uuid::isValid($request->getId()))
            ->andReturn(new Response(['ok' => true]));

        $id = (new Lock($rpc))->lock('resource');

        Assert::string($id);
        Assert::same(Uuid::fromString($id)->getFields()->getVersion(), 4);
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
