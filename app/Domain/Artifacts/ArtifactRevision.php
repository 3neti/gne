<?php

namespace App\Domain\Artifacts;

use InvalidArgumentException;

final readonly class ArtifactRevision
{
    public function __construct(public int|string $value)
    {
        if ((is_int($value) && $value < 1) || (is_string($value) && trim($value) === '')) {
            throw new InvalidArgumentException('An artifact revision must be a positive number or a non-empty immutable revision identity.');
        }
    }
}
