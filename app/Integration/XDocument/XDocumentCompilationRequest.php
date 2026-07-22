<?php

namespace App\Integration\XDocument;

final readonly class XDocumentCompilationRequest
{
    /**
     * @param  list<string>  $requestedCapabilities
     * @param  array<string, mixed>  $options
     */
    public function __construct(public string $contractVersion, public string $requestIdentifier, public string $requestFingerprint, public string $correlationIdentifier, public string $requestedDriver, public array $requestedCapabilities, public array $options, public XDocumentResolvedDocument $document) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['contract_version' => $this->contractVersion, 'request_identifier' => $this->requestIdentifier, 'request_fingerprint' => $this->requestFingerprint, 'correlation_identifier' => $this->correlationIdentifier, 'requested_driver' => $this->requestedDriver, 'requested_capabilities' => $this->requestedCapabilities, 'options' => $this->options, 'document' => $this->document->toArray()];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
    }
}
