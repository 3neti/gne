<?php

namespace App\Integration\XDocument;

final readonly class InstalledPackageBaseline
{
    public function __construct(
        public string $package,
        public string $expectedCommit,
        public ?string $actualCommit,
        public ?string $sourcePath,
        public ?string $version,
        public bool $installed,
        public bool $gitAvailable,
    ) {}

    public function matches(): bool
    {
        return $this->installed
            && $this->gitAvailable
            && hash_equals($this->expectedCommit, $this->actualCommit ?? '');
    }

    /** @return array<string, bool|string|null> */
    public function toArray(): array
    {
        return [
            'package' => $this->package,
            'expected_commit' => $this->expectedCommit,
            'actual_commit' => $this->actualCommit,
            'source_path' => $this->sourcePath,
            'version' => $this->version,
            'installed' => $this->installed,
            'git_available' => $this->gitAvailable,
            'matches' => $this->matches(),
        ];
    }
}
