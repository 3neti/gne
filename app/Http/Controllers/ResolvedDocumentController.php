<?php

namespace App\Http\Controllers;

use App\Domain\Compilation\BrowserProjectionDriver;
use App\Domain\Compilation\CompilationSubject;
use App\Domain\Compilation\CompilationSubjectNotFound;
use App\Domain\Compilation\DocumentDefinitionNotFound;
use App\Domain\Compilation\DocumentResolutionException;
use App\Domain\Compilation\DocumentResolutionRequest;
use App\Domain\Compilation\ResolveDocument;
use App\Domain\Repository\ValidateRepository;
use Inertia\Inertia;
use Inertia\Response;

class ResolvedDocumentController extends Controller
{
    public function __invoke(string $document, string $subject, ValidateRepository $validator, ResolveDocument $resolver, BrowserProjectionDriver $browserDriver): Response
    {
        $manifest = $validator->handle(base_path());
        abort_if($manifest->hasErrors(), 422, 'The repository must validate before documents can be resolved.');

        try {
            $subjectData = collect($manifest->artifacts)->first(fn (array $artifact): bool => ($artifact['subject']['identifier'] ?? null) === $subject)['subject'] ?? null;
            if (! is_array($subjectData)) {
                throw new CompilationSubjectNotFound("Compilation subject {$subject} was not found.");
            }
            $resolvedDocument = $resolver->handle(base_path(), $manifest, new DocumentResolutionRequest($document, new CompilationSubject($subjectData['identifier'], $subjectData['type'])));
        } catch (DocumentDefinitionNotFound|CompilationSubjectNotFound $exception) {
            abort(404, $exception->getMessage());
        } catch (DocumentResolutionException $exception) {
            abort(422, $exception->getMessage());
        }

        return Inertia::render('ResolvedDocumentWorkbench', ['document' => $resolvedDocument->toArray(), 'projection' => $browserDriver->project($resolvedDocument)->toArray()]);
    }
}
