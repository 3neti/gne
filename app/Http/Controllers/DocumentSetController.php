<?php

namespace App\Http\Controllers;

use App\Application\Storyboard\ResolveStoryboardRepositoryRoot;
use App\Domain\Compilation\BuildResolvedDocumentSet;
use App\Domain\Compilation\CompilationSubject;
use App\Domain\Compilation\DocumentSetBrowserProjectionDriver;
use App\Domain\Repository\ValidateRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentSetController extends Controller
{
    public function index(Request $request, ResolveStoryboardRepositoryRoot $roots, ValidateRepository $validator, BuildResolvedDocumentSet $builder, DocumentSetBrowserProjectionDriver $driver): Response
    {
        return $this->render($roots->handle($request), null, $validator, $builder, $driver);
    }

    public function show(Request $request, string $subject, ResolveStoryboardRepositoryRoot $roots, ValidateRepository $validator, BuildResolvedDocumentSet $builder, DocumentSetBrowserProjectionDriver $driver): Response
    {
        return $this->render($roots->handle($request), $subject, $validator, $builder, $driver);
    }

    private function render(string $root, ?string $requestedSubject, ValidateRepository $validator, BuildResolvedDocumentSet $builder, DocumentSetBrowserProjectionDriver $driver): Response
    {
        $manifest = $validator->handle($root);
        abort_if($manifest->hasErrors(), 422, 'The repository must validate before document sets can be built.');
        $subjects = collect($manifest->artifacts)->filter(fn (array $artifact): bool => is_array($artifact['subject'] ?? null))->pluck('subject')->unique('identifier')->sortBy('identifier')->values();
        if ($requestedSubject !== null) {
            $subjects = $subjects->where('identifier', $requestedSubject)->values();
            abort_if($subjects->isEmpty(), 404, "Compilation subject {$requestedSubject} was not found.");
        }
        $sets = $subjects->map(fn (array $subject): array => $driver->project($builder->handle($root, $manifest, new CompilationSubject($subject['identifier'], $subject['type'])))->toArray())->all();

        return Inertia::render('DocumentSetWorkbench', ['documentSets' => $sets, 'selectedSubject' => $requestedSubject]);
    }
}
