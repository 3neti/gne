<?php

namespace App\Integration\XDocument;

use App\Domain\Compilation\CompilationSubject;
use App\Domain\Compilation\CompilationSubjectNotFound;
use App\Domain\Compilation\DocumentResolutionException;
use App\Domain\Compilation\DocumentResolutionRequest;
use App\Domain\Compilation\ResolveDocument;
use App\Domain\Repository\ValidateRepository;
use LBHurtado\XDocument\Browser\Host\BrowserHostRequest;
use LBHurtado\XDocument\Browser\Host\BrowserHostResponse;
use LBHurtado\XDocument\Browser\Host\BrowserRepresentation;
use LBHurtado\XDocument\Browser\Host\ResolveBrowserRepresentation;
use LBHurtado\XDocument\Contract\ValidateDocumentCompilationRequest;

final readonly class ResolveXDocumentBrowserRepresentation implements BrowserDocumentRepresentationResolver
{
    public function __construct(
        private ValidateRepository $validateRepository,
        private ResolveDocument $resolveDocument,
        private PrepareXDocumentCompilationRequest $prepareRequest,
        private ValidateDocumentCompilationRequest $loadExternalRequest,
        private ResolveBrowserRepresentation $resolveBrowserRepresentation,
    ) {}

    public function handle(
        string $repositoryRoot,
        string $documentIdentifier,
        string $subjectIdentifier,
        BrowserRepresentation $representation = BrowserRepresentation::CompositionStyledHtml,
    ): BrowserHostResponse {
        $manifest = $this->validateRepository->handle($repositoryRoot);
        if ($manifest->hasErrors()) {
            throw new DocumentResolutionException('x-document compilation stopped because repository validation failed.');
        }

        $artifact = collect($manifest->artifacts)->first(
            fn (array $item): bool => ($item['subject']['identifier'] ?? null) === $subjectIdentifier,
        );
        $subjectType = is_array($artifact) ? ($artifact['subject']['type'] ?? null) : null;
        if (! is_string($subjectType)) {
            throw new CompilationSubjectNotFound("Compilation subject {$subjectIdentifier} was not found.");
        }

        $resolvedDocument = $this->resolveDocument->handle(
            $repositoryRoot,
            $manifest,
            new DocumentResolutionRequest(
                $documentIdentifier,
                new CompilationSubject($subjectIdentifier, $subjectType),
            ),
        );
        $preparedRequest = $this->prepareRequest->handle($resolvedDocument, requestedDriver: 'browser');
        $externalRequest = $this->loadExternalRequest->handleJson($preparedRequest->toJson());

        return $this->resolveBrowserRepresentation->resolve(
            BrowserHostRequest::forCompilation($externalRequest, $representation),
        );
    }
}
