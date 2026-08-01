<?php

namespace App\Integration\XDocument;

final readonly class XDocumentContractSmokeResult
{
    public function __construct(
        public bool $passed,
        public string $document,
        public string $subject,
        public string $representation,
        public string $format,
        public string $checksum,
        public string $etag,
        public int $byteLength,
    ) {}

    /** @return array<string, bool|int|string> */
    public function toArray(): array
    {
        return [
            'passed' => $this->passed,
            'document' => $this->document,
            'subject' => $this->subject,
            'representation' => $this->representation,
            'format' => $this->format,
            'checksum' => $this->checksum,
            'etag' => $this->etag,
            'byte_length' => $this->byteLength,
        ];
    }
}
