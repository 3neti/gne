<?php

namespace App\Console\Commands;

use App\Models\SubjectAccessGrant;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gne:subject-access:list {--user= : Limit by user email} {--json : Emit structured JSON}')]
#[Description('List host-owned Compilation Subject access grants')]
final class GneSubjectAccessListCommand extends Command
{
    public function handle(): int
    {
        $userEmail = $this->option('user');
        $user = is_string($userEmail) ? User::query()->where('email', $userEmail)->first() : null;
        if (is_string($userEmail) && $user === null) {
            $this->components->error('Unknown user.');

            return self::FAILURE;
        }
        $grants = SubjectAccessGrant::query()->with('user')
            ->when($user, fn ($query) => $query->whereBelongsTo($user))
            ->orderBy('subject_identifier')->orderBy('permission')->orderBy('user_id')->get()
            ->map(fn (SubjectAccessGrant $grant): array => [
                'user' => $grant->user->email,
                'subject_identifier' => $grant->subject_identifier,
                'permission' => $grant->permission->value,
                'active' => $grant->isActive(),
                'expires_at' => $grant->expires_at?->toIso8601String(),
            ])->all();
        if ($this->option('json')) {
            $this->line(json_encode(['grants' => $grants], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['User', 'Subject', 'Permission', 'Active', 'Expires'], array_map(fn (array $grant): array => [$grant['user'], $grant['subject_identifier'], $grant['permission'], $grant['active'] ? 'yes' : 'no', $grant['expires_at'] ?? 'never'], $grants));
        }

        return self::SUCCESS;
    }
}
