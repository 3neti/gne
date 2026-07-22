<?php

namespace App\Domain\Compilation;

interface DocumentProjection
{
    /** @return array<string, mixed> */
    public function toArray(): array;
}
