<?php

namespace App\Console\Commands;

use App\Application\Authorization\FindCompilationSubject;
use App\Application\Authorization\RevokeSubjectAccess;
use App\Domain\Authorization\SubjectPermission;
use App\Domain\Compilation\CompilationSubjectNotFound;
use App\Domain\Repository\ValidateRepository;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gne:subject-access:revoke {--user= : User email} {--subject= : Compilation Subject identifier} {--permission=view : Subject permission} {--json : Emit structured JSON}')]
#[Description('Revoke a host user access grant for a Compilation Subject')]
final class GneSubjectAccessRevokeCommand extends Command
{
    public function handle(ValidateRepository $validator, FindCompilationSubject $subjects, RevokeSubjectAccess $revocations): int
    {
        $user = User::query()->where('email', $this->option('user'))->first();
        $permission = SubjectPermission::tryFrom((string) $this->option('permission'));
        if ($user === null || $permission === null || ! is_string($this->option('subject'))) {
            return $this->failWith('Unknown user, subject, or permission.');
        }
        try {
            $subject = $subjects->fromManifest($validator->handle(base_path()), $this->option('subject'));
        } catch (CompilationSubjectNotFound $exception) {
            return $this->failWith($exception->getMessage());
        }
        $removed = $revocations->handle($user, $subject->identifier, $permission);
        $result = ['status' => $removed ? 'revoked' : 'absent', 'user' => $user->email, 'subject_identifier' => $subject->identifier, 'permission' => $permission->value];
        $this->line($this->option('json') ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : "{$result['status']}: {$user->email} {$subject->identifier}");

        return self::SUCCESS;
    }

    private function failWith(string $message): int
    {
        $this->components->error($message);

        return self::FAILURE;
    }
}
