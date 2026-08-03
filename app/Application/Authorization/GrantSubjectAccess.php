<?php

namespace App\Application\Authorization;

use App\Domain\Authorization\SubjectPermission;
use App\Domain\Compilation\CompilationSubject;
use App\Models\SubjectAccessGrant;
use App\Models\User;
use DateTimeInterface;

final class GrantSubjectAccess
{
    public function handle(User $user, CompilationSubject $subject, SubjectPermission $permission, ?User $grantedBy = null, ?DateTimeInterface $expiresAt = null): SubjectAccessGrant
    {
        return SubjectAccessGrant::query()->firstOrCreate(
            ['user_id' => $user->getKey(), 'subject_identifier' => $subject->identifier, 'permission' => $permission->value],
            ['granted_by_user_id' => $grantedBy?->getKey(), 'granted_at' => now(), 'expires_at' => $expiresAt],
        );
    }
}
