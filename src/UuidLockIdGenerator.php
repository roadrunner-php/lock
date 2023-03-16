<?php

declare(strict_types=1);

namespace RoadRunner\Lock;

use Ramsey\Uuid\UuidFactory;
use Ramsey\Uuid\UuidFactoryInterface;

final class UuidLockIdGenerator implements LockIdGeneratorInterface
{
    public function __construct(
        private readonly UuidFactoryInterface $factory = new UuidFactory(),
    ) {
    }

    public function generate(): string
    {
        return $this->factory->uuid4()->toString();
    }
}