<?php

namespace App\Http\Controllers;

use App\Application\Storyboard\ResolveStoryboardRepositoryRoot;
use App\Domain\Compilation\PrepareCompilationPlan;
use App\Domain\Repository\ExplainRepository;
use App\Domain\Repository\ValidateRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RepositoryWorkbenchController extends Controller
{
    public function __invoke(Request $request, ResolveStoryboardRepositoryRoot $roots, ValidateRepository $validator, ExplainRepository $explainer, PrepareCompilationPlan $compiler): Response
    {
        $root = $roots->handle($request);
        $manifest = $validator->handle($root);
        $explanation = $explainer->handle($root, $manifest);

        return Inertia::render('RepositoryWorkbench', [
            'section' => $request->route()->defaults['section'] ?? 'dashboard',
            'repository' => $explanation,
            'findings' => array_map(fn ($finding): array => $finding->toArray(), $manifest->findings),
            'documents' => $compiler->handle($root, $manifest)['documents'],
        ]);
    }
}
