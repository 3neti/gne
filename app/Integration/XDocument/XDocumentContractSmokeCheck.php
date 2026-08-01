<?php

namespace App\Integration\XDocument;

use LBHurtado\XDocument\Browser\Host\BrowserRepresentation;

final readonly class XDocumentContractSmokeCheck
{
    public function __construct(private ResolveXDocumentBrowserRepresentation $resolver) {}

    public function handle(string $repositoryRoot): XDocumentContractSmokeResult
    {
        $document = 'DOCUMENT-INVOICE';
        $subject = 'RESERVATION-000001';
        $representation = BrowserRepresentation::CompositionStyledHtml;
        $response = $this->resolver->handle($repositoryRoot, $document, $subject, $representation);
        $passed = $response->descriptor->format === 'browser-composition-html-styled/1.0'
            && $response->output->mediaType === 'text/html; charset=utf-8'
            && $response->output->inlineContent !== null
            && $response->output->byteLength === strlen($response->output->inlineContent)
            && $response->etag === '"'.$response->output->checksum.'"';

        return new XDocumentContractSmokeResult(
            passed: $passed,
            document: $document,
            subject: $subject,
            representation: $representation->value,
            format: $response->descriptor->format,
            checksum: $response->output->checksum,
            etag: $response->etag,
            byteLength: $response->output->byteLength,
        );
    }
}
