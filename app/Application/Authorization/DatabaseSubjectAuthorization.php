<?php

namespace App\Application\Authorization;

use App\Contracts\SubjectAuthorization;
use App\Domain\Authorization\SubjectAccessDecision;
use App\Domain\Authorization\SubjectPermission;
use App\Domain\Compilation\CompilationSubject;
use App\Models\SubjectAccessGrant;
use App\Models\User;

final class DatabaseSubjectAuthorization implements SubjectAuthorization
{
    public function decide(User $user, CompilationSubject $subject, SubjectPermission $permission): SubjectAccessDecision
    {
        $grant = SubjectAccessGrant::query()
            ->whereBelongsTo($user)
            ->where('subject_identifier', $subject->identifier)
            ->where('permission', $permission->value)
            ->first();

        if ($grant === null) {
            return new SubjectAccessDecision(false, 'grant_missing', $subject->identifier, $permission);
        }

        return $grant->isActive()
            ? new SubjectAccessDecision(true, 'grant_active', $subject->identifier, $permission, 'database')
            : new SubjectAccessDecision(false, 'grant_expired', $subject->identifier, $permission, 'database');
    }
}
