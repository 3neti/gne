<?php

namespace App\Application\Authorization;

use App\Domain\Authorization\SubjectPermission;
use App\Models\SubjectAccessGrant;
use App\Models\User;

final class RevokeSubjectAccess
{
    public function handle(User $user, string $subjectIdentifier, SubjectPermission $permission): bool
    {
        return SubjectAccessGrant::query()->whereBelongsTo($user)
            ->where('subject_identifier', $subjectIdentifier)
            ->where('permission', $permission->value)
            ->delete() > 0;
    }
}
