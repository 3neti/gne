<?php

namespace App\Application\Authorization;

use App\Domain\Compilation\CompilationSubject;
use App\Domain\Compilation\CompilationSubjectNotFound;
use App\Domain\Repository\RepositoryManifest;
use App\Domain\Repository\ValidateRepository;

class FindCompilationSubject
{
    public function __construct(private readonly ValidateRepository $validator) {}

    public function handle(string $repositoryRoot, string $identifier): CompilationSubject
    {
        $manifest = $this->validator->handle($repositoryRoot);
        abort_if($manifest->hasErrors(), 422, 'The repository must validate before subjects can be authorized.');

        return $this->fromManifest($manifest, $identifier);
    }

    public function fromManifest(RepositoryManifest $manifest, string $identifier): CompilationSubject
    {
        $subject = collect($manifest->artifacts)
            ->first(fn (array $artifact): bool => ($artifact['subject']['identifier'] ?? null) === $identifier)['subject'] ?? null;
        if (! is_array($subject)) {
            throw new CompilationSubjectNotFound("Compilation subject {$identifier} was not found.");
        }

        return new CompilationSubject($subject['identifier'], $subject['type']);
    }
}
