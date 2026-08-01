<?php

namespace App\Integration\XDocument;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Route;
use LBHurtado\XDocument\Browser\Host\ResolveBrowserRepresentation;
use LBHurtado\XDocument\Contract\ValidateDocumentCompilationRequest;
use LBHurtado\XDocumentLaravel\Contracts\DocumentHttpResponseFactory;

final readonly class XDocumentRuntimeDiagnostics
{
    public function __construct(
        private Container $container,
        private XDocumentPackageBaselineAttestor $baselines,
        private XDocumentContractSmokeCheck $contractSmoke,
    ) {}

    /** @return array<string, bool|int|string|null|array<string, bool|int|string>> */
    public function toArray(): array
    {
        $baselines = $this->baselines->attestAll();
        $smoke = $this->contractSmoke->handle(base_path());

        return [
            'x_document_installed' => $baselines['x_document']->installed,
            'x_document_version' => $baselines['x_document']->version,
            'x_document_expected_commit' => $baselines['x_document']->expectedCommit,
            'x_document_actual_commit' => $baselines['x_document']->actualCommit,
            'x_document_baseline_matches' => $baselines['x_document']->matches(),
            'x_document_laravel_installed' => $baselines['x_document_laravel']->installed,
            'x_document_laravel_version' => $baselines['x_document_laravel']->version,
            'x_document_laravel_expected_commit' => $baselines['x_document_laravel']->expectedCommit,
            'x_document_laravel_actual_commit' => $baselines['x_document_laravel']->actualCommit,
            'x_document_laravel_baseline_matches' => $baselines['x_document_laravel']->matches(),
            'contract_validator_available' => class_exists(ValidateDocumentCompilationRequest::class),
            'contract_smoke' => $smoke->toArray(),
            'contract_smoke_passed' => $smoke->passed,
            'browser_composition_available' => class_exists(ResolveBrowserRepresentation::class),
            'http_factory_bound' => $this->container->bound(DocumentHttpResponseFactory::class),
            'authenticated_http_route_available' => Route::has('documents.browser'),
            'pdf_available' => false,
            'action_execution_available' => false,
        ];
    }
}
