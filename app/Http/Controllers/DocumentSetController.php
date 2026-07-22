<?php

namespace App\Http\Controllers;

use App\Domain\Compilation\BuildResolvedDocumentSet;
use App\Domain\Compilation\CompilationSubject;
use App\Domain\Compilation\DocumentSetBrowserProjectionDriver;
use App\Domain\Repository\ValidateRepository;
use Inertia\Inertia;
use Inertia\Response;

class DocumentSetController extends Controller
{
    public function index(ValidateRepository $validator, BuildResolvedDocumentSet $builder, DocumentSetBrowserProjectionDriver $driver): Response
    {
        return $this->render(null, $validator, $builder, $driver);
    }

    public function show(string $subject, ValidateRepository $validator, BuildResolvedDocumentSet $builder, DocumentSetBrowserProjectionDriver $driver): Response
    {
        return $this->render($subject, $validator, $builder, $driver);
    }

    private function render(?string $requestedSubject, ValidateRepository $validator, BuildResolvedDocumentSet $builder, DocumentSetBrowserProjectionDriver $driver): Response
    {
        $manifest = $validator->handle(base_path());
        abort_if($manifest->hasErrors(), 422, 'The repository must validate before document sets can be built.');
        $subjects = collect($manifest->artifacts)->filter(fn (array $artifact): bool => is_array($artifact['subject'] ?? null))->pluck('subject')->unique('identifier')->sortBy('identifier')->values();
        if ($requestedSubject !== null) {
            $subjects = $subjects->where('identifier', $requestedSubject)->values();
            abort_if($subjects->isEmpty(), 404, "Compilation subject {$requestedSubject} was not found.");
        }
        $sets = $subjects->map(fn (array $subject): array => $driver->project($builder->handle(base_path(), $manifest, new CompilationSubject($subject['identifier'], $subject['type'])))->toArray())->all();

        return Inertia::render('DocumentSetWorkbench', ['documentSets' => $sets, 'selectedSubject' => $requestedSubject]);
    }
}
