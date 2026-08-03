<?php

namespace App\Domain\Authorization;

final readonly class SubjectAccessDecision
{
    public function __construct(
        public bool $allowed,
        public string $reason,
        public string $subjectIdentifier,
        public SubjectPermission $permission,
        public ?string $grantSource = null,
    ) {}
}
