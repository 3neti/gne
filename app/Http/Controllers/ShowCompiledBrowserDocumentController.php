<?php

namespace App\Http\Controllers;

use App\Domain\Compilation\CompilationSubjectNotFound;
use App\Domain\Compilation\DocumentDefinitionNotFound;
use App\Domain\Compilation\DocumentResolutionException;
use App\Integration\XDocument\BrowserDocumentRepresentationResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use LBHurtado\XDocument\Browser\Host\BrowserRepresentation;
use LBHurtado\XDocumentLaravel\Contracts\DocumentHttpResponseFactory;
use LBHurtado\XDocumentLaravel\Data\DocumentHttpRequestContext;
use Symfony\Component\HttpFoundation\Response;

final class ShowCompiledBrowserDocumentController extends Controller
{
    public function __invoke(
        Request $request,
        string $subject,
        string $document,
        BrowserDocumentRepresentationResolver $resolver,
        DocumentHttpResponseFactory $responses,
    ): Response {
        Gate::authorize('view-demonstration-documents');
        $representation = $this->representation($request);

        try {
            $hostResponse = $resolver->handle(base_path(), $document, $subject, $representation);
        } catch (DocumentDefinitionNotFound|CompilationSubjectNotFound $exception) {
            abort(404, $exception->getMessage());
        } catch (DocumentResolutionException $exception) {
            abort(422, $exception->getMessage());
        }

        return $responses->make(
            $hostResponse,
            DocumentHttpRequestContext::fromLaravelRequest($request),
        );
    }

    private function representation(Request $request): BrowserRepresentation
    {
        $identifier = $request->query('representation', BrowserRepresentation::CompositionStyledHtml->value);
        abort_unless(is_string($identifier), 400, 'The browser representation must be a string.');
        $representation = BrowserRepresentation::tryFrom($identifier);
        abort_unless(in_array($representation, [
            BrowserRepresentation::Composition,
            BrowserRepresentation::CompositionHtml,
            BrowserRepresentation::CompositionStyledHtml,
        ], true), 400, 'The requested browser representation is not supported by the GNE MVP.');

        return $representation;
    }
}
