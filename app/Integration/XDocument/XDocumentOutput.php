<?php

namespace App\Integration\XDocument;

final readonly class XDocumentOutput
{
    /** @param array<string, mixed> $metadata */
    public function __construct(public string $mediaType, public ?string $filename = null, public ?string $checksum = null, public ?int $byteLength = null, public ?string $inlineContent = null, public ?string $contentReference = null, public array $metadata = []) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['media_type' => $this->mediaType, 'filename' => $this->filename, 'checksum' => $this->checksum, 'byte_length' => $this->byteLength, 'inline_content' => $this->inlineContent, 'content_reference' => $this->contentReference, 'metadata' => $this->metadata === [] ? (object) [] : $this->metadata];
    }
}
