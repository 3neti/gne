<?php

namespace App\Contracts;

use App\Domain\Authorization\SubjectAccessDecision;
use App\Domain\Authorization\SubjectPermission;
use App\Domain\Compilation\CompilationSubject;
use App\Models\User;

interface SubjectAuthorization
{
    public function decide(User $user, CompilationSubject $subject, SubjectPermission $permission): SubjectAccessDecision;
}
