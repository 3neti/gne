<?php

namespace App\Domain\Compilation;

final readonly class ResolvedDocument
{
    /** @param array<string, mixed> $content @param list<string> $evidencePaths */
    public function __construct(public string $identifier, public string $definition, public array $content, public array $evidencePaths) {}
}
