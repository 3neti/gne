<?php

namespace App\Domain\Repository;

final readonly class ValidateRepository
{
    public function __construct(private DiscoverRepository $discoverRepository) {}

    public function handle(string $repositoryRoot): RepositoryManifest
    {
        return $this->discoverRepository->handle($repositoryRoot);
    }
}
