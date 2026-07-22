<?php

namespace App\Domain\Compilation;

final readonly class CompilationSubject
{
    public function __construct(public string $identifier, public string $type)
    {
        if (trim($identifier) === '' || preg_match('/\s/', $identifier) === 1) {
            throw new DocumentResolutionException('Compilation subject identifier must be non-empty and contain no whitespace.');
        }
        if (trim($type) === '') {
            throw new DocumentResolutionException('Compilation subject type must be non-empty.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->identifier === $other->identifier && $this->type === $other->type;
    }

    /** @return array{identifier: string, type: string} */
    public function toArray(): array
    {
        return ['identifier' => $this->identifier, 'type' => $this->type];
    }
}
