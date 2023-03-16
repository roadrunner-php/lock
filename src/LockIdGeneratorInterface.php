<?php

declare(strict_types=1);

namespace RoadRunner\Lock;

/**
 * Unique identity generator for locks.
 */
interface LockIdGeneratorInterface
{
    /**
     * Generate a new identity string
     *
     * @return non-empty-string
     */
    public function generate(): string;
}