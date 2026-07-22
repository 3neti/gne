<?php

namespace App\Domain\Compilation;

final readonly class DocumentCompilationRequest
{
    public function __construct(public ResolvedDocument $document, public string $driver) {}
}
