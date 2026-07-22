<?php

namespace App\Integration\XDocument;

final readonly class XDocumentContractVersion
{
    public const Current = '1.0';

    public function __construct(public string $value = self::Current)
    {
        if ($value !== self::Current) {
            throw new UnsupportedXDocumentContractVersion("Unsupported x-document contract version: {$value}.");
        }
    }
}
