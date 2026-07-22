<?php

namespace App\Http\Controllers;

use App\Domain\Compilation\BrowserProjectionDriver;
use App\Domain\Compilation\DocumentResolutionException;
use App\Domain\Compilation\ResolveDocument;
use App\Domain\Repository\ValidateRepository;
use Inertia\Inertia;
use Inertia\Response;

class ResolvedDocumentController extends Controller
{
    public function __invoke(string $document, ValidateRepository $validator, ResolveDocument $resolver, BrowserProjectionDriver $browserDriver): Response
    {
        $manifest = $validator->handle(base_path());
        abort_if($manifest->hasErrors(), 422, 'The repository must validate before documents can be resolved.');

        try {
            $resolvedDocument = $resolver->handle(base_path(), $manifest, $document);
        } catch (DocumentResolutionException $exception) {
            abort(404, $exception->getMessage());
        }

        return Inertia::render('ResolvedDocumentWorkbench', ['document' => $resolvedDocument->toArray(), 'projection' => $browserDriver->project($resolvedDocument)->toArray()]);
    }
}
