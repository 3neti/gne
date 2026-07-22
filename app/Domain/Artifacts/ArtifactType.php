<?php

namespace App\Domain\Artifacts;

use InvalidArgumentException;

final readonly class ArtifactType
{
    public function __construct(public string $value)
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('An artifact type is required.');
        }
    }
}
