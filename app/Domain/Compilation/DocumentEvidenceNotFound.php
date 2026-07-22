<?php

namespace App\Domain\Compilation;

final class DocumentEvidenceNotFound extends DocumentResolutionException
{
    public function __construct(public readonly string $artifactType, string $message)
    {
        parent::__construct($message);
    }
}
