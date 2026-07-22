<?php

namespace App\Domain\Repository;

use InvalidArgumentException;

final readonly class RepositoryPath
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = str_replace('\\', '/', trim($value));

        if ($normalized === '' || str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized) || in_array('..', explode('/', $normalized), true)) {
            throw new InvalidArgumentException("Unsafe repository-relative path: {$value}");
        }

        $this->value = trim($normalized, '/');
    }

    public function isWithin(self $parent): bool
    {
        return $this->value === $parent->value || str_starts_with($this->value, $parent->value.'/');
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
