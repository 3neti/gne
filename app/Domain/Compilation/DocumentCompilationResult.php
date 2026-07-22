<?php

namespace App\Domain\Compilation;

final readonly class DocumentCompilationResult
{
    public function __construct(public bool $successful, public ?string $location = null, public ?string $message = null) {}
}
