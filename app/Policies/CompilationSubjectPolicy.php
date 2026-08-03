<?php

namespace App\Policies;

use App\Contracts\SubjectAuthorization;
use App\Domain\Authorization\SubjectPermission;
use App\Domain\Compilation\CompilationSubject;
use App\Models\User;

final readonly class CompilationSubjectPolicy
{
    public function __construct(private SubjectAuthorization $authorization) {}

    public function view(User $user, CompilationSubject $subject): bool
    {
        return $this->authorization->decide($user, $subject, SubjectPermission::View)->allowed;
    }
}
