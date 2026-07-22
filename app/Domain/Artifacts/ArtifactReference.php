<?php

namespace App\Domain\Artifacts;

final readonly class ArtifactReference
{
    public function __construct(public BusinessIdentifier $identifier, public ?ArtifactRevision $revision = null, public ?string $relationship = null) {}
}
