<?php

namespace App\Integration\XDocument;

final readonly class XDocumentAttachment
{
    /** @param array<string, mixed> $metadata */
    public function __construct(public string $identifier, public string $name, public ?string $mediaType = null, public ?int $byteLength = null, public ?string $checksum = null, public ?string $sourceReference = null, public string $disposition = 'attachment', public array $metadata = []) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['identifier' => $this->identifier, 'name' => $this->name, 'media_type' => $this->mediaType, 'byte_length' => $this->byteLength, 'checksum' => $this->checksum, 'source_reference' => $this->sourceReference, 'disposition' => $this->disposition, 'metadata' => $this->metadata === [] ? (object) [] : $this->metadata];
    }
}
