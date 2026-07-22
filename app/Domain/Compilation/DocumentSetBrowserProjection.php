<?php

namespace App\Domain\Compilation;

final readonly class DocumentSetBrowserProjection
{
    /**
     * @param  array<string, mixed>  $subject
     * @param  array<string, mixed>  $lifecycle
     * @param  array<string, int>  $counts
     * @param  list<array<string, mixed>>  $entries
     */
    public function __construct(public string $identifier, public string $fingerprint, public array $subject, public string $profile, public string $scenario, public array $lifecycle, public array $counts, public array $entries) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
