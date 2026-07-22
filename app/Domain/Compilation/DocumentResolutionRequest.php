<?php

namespace App\Domain\Compilation;

final readonly class DocumentResolutionRequest
{
    public function __construct(public string $documentDefinitionIdentifier, public CompilationSubject $compilationSubject) {}
}
