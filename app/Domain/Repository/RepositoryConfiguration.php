<?php

namespace App\Domain\Repository;

use InvalidArgumentException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final readonly class RepositoryConfiguration
{
    /** @param list<string> $enabledProfiles */
    public function __construct(public int $version, public RepositoryPath $businessPath, public RepositoryPath $generatedPath, public array $enabledProfiles) {}

    public static function load(string $repositoryRoot): self
    {
        $path = $repositoryRoot.'/gne.yaml';

        if (! is_file($path)) {
            throw new InvalidArgumentException('gne.yaml does not exist at the repository root.');
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (ParseException $exception) {
            throw new InvalidArgumentException('gne.yaml is malformed: '.$exception->getMessage(), previous: $exception);
        }

        if (! is_array($data) || ! is_array($data['gne'] ?? null)) {
            throw new InvalidArgumentException('gne.yaml must contain a top-level gne mapping.');
        }

        $gne = $data['gne'];
        $version = $gne['version'] ?? null;
        $businessPath = $gne['repository']['business_path'] ?? null;
        $generatedPath = $gne['repository']['generated_path'] ?? null;

        if ($version !== 1) {
            throw new InvalidArgumentException('gne.yaml gne.version must be 1.');
        }

        if (! is_string($businessPath) || ! is_string($generatedPath)) {
            throw new InvalidArgumentException('gne.yaml must declare repository.business_path and repository.generated_path.');
        }

        $enabled = $gne['profiles']['enabled'] ?? [];

        if (! is_array($enabled) || array_any($enabled, fn (mixed $profile): bool => ! is_string($profile))) {
            throw new InvalidArgumentException('gne.yaml profiles.enabled must be a list of profile identifiers.');
        }

        return new self($version, new RepositoryPath($businessPath), new RepositoryPath($generatedPath), array_values($enabled));
    }
}
