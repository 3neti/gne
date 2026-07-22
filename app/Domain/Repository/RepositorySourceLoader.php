<?php

namespace App\Domain\Repository;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use JsonException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class RepositorySourceLoader
{
    public function __construct(private readonly Filesystem $files) {}

    /** @throws RepositorySourceException */
    public function jsonObject(string $path): object
    {
        try {
            $decoded = json_decode($this->contents($path), false, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RepositorySourceException($exception->getMessage(), previous: $exception);
        }

        if (! is_object($decoded)) {
            throw new RepositorySourceException("JSON source must contain an object: {$path}");
        }

        return $decoded;
    }

    /** @return array<string, mixed> @throws RepositorySourceException */
    public function yamlMapping(string $path): array
    {
        try {
            $decoded = Yaml::parse($this->contents($path));
        } catch (ParseException $exception) {
            throw new RepositorySourceException($exception->getMessage(), previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new RepositorySourceException("YAML source must contain a mapping: {$path}");
        }

        return $decoded;
    }

    /** @throws RepositorySourceException */
    private function contents(string $path): string
    {
        try {
            $contents = $this->files->get($path);
        } catch (FileNotFoundException $exception) {
            throw new RepositorySourceException($exception->getMessage(), previous: $exception);
        }

        if (! is_string($contents)) {
            throw new RepositorySourceException("Source is unreadable: {$path}");
        }

        return $contents;
    }
}
