<?php

namespace App\Console\Commands;

use App\Application\Authorization\FindCompilationSubject;
use App\Application\Authorization\GrantSubjectAccess;
use App\Domain\Authorization\SubjectPermission;
use App\Domain\Compilation\CompilationSubjectNotFound;
use App\Domain\Repository\ValidateRepository;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gne:subject-access:grant {--user= : User email} {--subject= : Compilation Subject identifier} {--permission=view : Subject permission} {--expires-at= : Optional ISO-8601 expiry} {--json : Emit structured JSON}')]
#[Description('Grant a host user access to a repository-native Compilation Subject')]
final class GneSubjectAccessGrantCommand extends Command
{
    public function handle(ValidateRepository $validator, FindCompilationSubject $subjects, GrantSubjectAccess $grants): int
    {
        $user = User::query()->where('email', $this->option('user'))->first();
        $permission = SubjectPermission::tryFrom((string) $this->option('permission'));
        if ($user === null || $permission === null || ! is_string($this->option('subject'))) {
            return $this->failWith('Unknown user, subject, or permission.');
        }
        $manifest = $validator->handle(base_path());
        try {
            $subject = $subjects->fromManifest($manifest, $this->option('subject'));
        } catch (CompilationSubjectNotFound $exception) {
            return $this->failWith($exception->getMessage());
        }
        $expiry = is_string($this->option('expires-at')) ? CarbonImmutable::parse($this->option('expires-at')) : null;
        $grant = $grants->handle($user, $subject, $permission, expiresAt: $expiry);
        $status = $grant->wasRecentlyCreated ? 'created' : 'already_exists';
        $result = ['status' => $status, 'user' => $user->email, 'subject_identifier' => $subject->identifier, 'permission' => $permission->value, 'expires_at' => $grant->expires_at?->toIso8601String()];
        $this->output($result);

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $result */
    private function output(array $result): void
    {
        $this->line($this->option('json') ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : sprintf('%s: %s may %s %s', $result['status'], $result['user'], $result['permission'], $result['subject_identifier']));
    }

    private function failWith(string $message): int
    {
        $this->components->error($message);

        return self::FAILURE;
    }
}
