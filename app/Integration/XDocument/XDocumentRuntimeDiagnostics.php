<?php

namespace App\Integration\XDocument;

use Composer\InstalledVersions;
use Illuminate\Contracts\Container\Container;
use LBHurtado\XDocument\Browser\Host\ResolveBrowserRepresentation;
use LBHurtado\XDocument\Contract\ValidateDocumentCompilationRequest;
use LBHurtado\XDocumentLaravel\Contracts\DocumentHttpResponseFactory;

final readonly class XDocumentRuntimeDiagnostics
{
    public const XDocumentCommit = '29853fae23939cba0b440db3ae04e351c499a78e';

    public const XDocumentLaravelCommit = 'b299d5bfbe7bdf93ecaf840431349804b676a6c7';

    public function __construct(private Container $container) {}

    /** @return array<string, bool|string|null> */
    public function toArray(): array
    {
        return [
            'x_document_installed' => class_exists(ValidateDocumentCompilationRequest::class),
            'x_document_version' => InstalledVersions::getPrettyVersion('3neti/x-document'),
            'x_document_commit' => self::XDocumentCommit,
            'x_document_contract_compatible' => true,
            'x_document_laravel_installed' => interface_exists(DocumentHttpResponseFactory::class),
            'x_document_laravel_version' => InstalledVersions::getPrettyVersion('3neti/x-document-laravel'),
            'x_document_laravel_commit' => self::XDocumentLaravelCommit,
            'browser_composition_available' => class_exists(ResolveBrowserRepresentation::class),
            'http_delivery_available' => $this->container->bound(DocumentHttpResponseFactory::class),
            'pdf_available' => false,
            'action_execution_available' => false,
        ];
    }
}
