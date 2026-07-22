<?php

namespace App\Domain\Repository;

use InvalidArgumentException;

final readonly class RepositoryAddress
{
    public string $root;

    public function __construct(string $repositoryRoot)
    {
        $resolvedRoot = realpath($repositoryRoot);

        if ($resolvedRoot === false || ! is_dir($resolvedRoot)) {
            throw new InvalidArgumentException("Repository root does not exist: {$repositoryRoot}");
        }

        $this->root = rtrim(str_replace('\\', '/', $resolvedRoot), '/');
    }

    public function absolute(string|RepositoryPath $relativePath): string
    {
        $relativePath = $relativePath instanceof RepositoryPath ? $relativePath : new RepositoryPath($relativePath);

        return $this->root.'/'.$relativePath;
    }

    public function relative(string $absolutePath): string
    {
        $normalizedPath = str_replace('\\', '/', $absolutePath);
        $prefix = $this->root.'/';

        if (! str_starts_with($normalizedPath, $prefix)) {
            throw new InvalidArgumentException("Path is outside repository root: {$absolutePath}");
        }

        return (string) new RepositoryPath(substr($normalizedPath, strlen($prefix)));
    }
}
