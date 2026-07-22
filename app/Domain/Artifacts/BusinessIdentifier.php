<?php

namespace App\Domain\Artifacts;

use InvalidArgumentException;

final readonly class BusinessIdentifier
{
    public function __construct(public string $value)
    {
        if (trim($value) === '' || mb_strlen($value) > 160 || preg_match('/\s/', $value)) {
            throw new InvalidArgumentException('A business identifier must be a non-empty, whitespace-free string of at most 160 characters.');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
