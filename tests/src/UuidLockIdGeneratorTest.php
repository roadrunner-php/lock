<?php

declare(strict_types=1);

namespace RoadRunner\Lock\Tests;

use Mockery as m;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidFactoryInterface;
use RoadRunner\Lock\UuidLockIdGenerator;
use Testo\Assert;
use Testo\Test;

#[Test]
final class UuidLockIdGeneratorTest
{
    public function testGeneratesUuidV4ByDefault(): void
    {
        $generator = new UuidLockIdGenerator();

        $id = $generator->generate();

        Assert::true(Uuid::isValid($id));
        Assert::same(Uuid::fromString($id)->getFields()->getVersion(), 4);
        Assert::notSame($generator->generate(), $id);
    }

    public function testUsesGivenFactory(): void
    {
        $uuid = Uuid::fromString('2d1e4f7a-8b3c-4d5e-9f60-718293a4b5c6');
        $factory = m::mock(UuidFactoryInterface::class);
        $factory->shouldReceive('uuid4')->once()->andReturn($uuid);

        Assert::same((new UuidLockIdGenerator($factory))->generate(), '2d1e4f7a-8b3c-4d5e-9f60-718293a4b5c6');
    }
}
