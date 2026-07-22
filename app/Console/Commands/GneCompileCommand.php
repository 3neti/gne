<?php

namespace App\Console\Commands;

use App\Domain\Compilation\PrepareCompilationPlan;
use App\Domain\Repository\ValidateRepository;
use App\Domain\Semantics\BuildSemanticIndex;
use Illuminate\Console\Command;

class GneCompileCommand extends Command
{
    protected $signature = 'gne:compile {--repository= : Repository root (defaults to the application root)} {--document= : Compile one document definition} {--subject= : Compile one compilation subject} {--json : Emit structured JSON}';

    protected $description = 'Validate, index, and prepare an honest GNE compilation plan';

    public function handle(ValidateRepository $validator, BuildSemanticIndex $indexer, PrepareCompilationPlan $planner): int
    {
        $repositoryRoot = is_string($this->option('repository')) ? $this->option('repository') : base_path();
        $manifest = $validator->handle($repositoryRoot);
        if ($manifest->hasErrors()) {
            $this->components->error('Compilation planning stopped because validation failed.');

            return self::FAILURE;
        }
        $indexer->handle($repositoryRoot, $manifest);
        $document = $this->option('document');
        $subject = $this->option('subject');
        if (($document === null) !== ($subject === null)) {
            $this->components->error('--document and --subject must be supplied together.');

            return self::FAILURE;
        }
        $plan = $planner->handle($repositoryRoot, $manifest, is_string($document) ? $document : null, is_string($subject) ? $subject : null);
        if ($document !== null && $plan['documents'] === []) {
            $this->components->error('The requested document definition or compilation subject was not found.');

            return self::FAILURE;
        }
        if ($this->option('json')) {
            $this->line(json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->components->info('Validated repository.');
            $this->line("Resolved {$plan['profiles']} profile(s), {$plan['scenarios']} scenario(s), and {$plan['artifacts']} artifact revision(s).");
            $this->line("Compilation subjects: {$plan['compilation_subjects']}");
            $this->line("Compiled {$plan['resolved_documents']} resolved document(s) into {$plan['browser_projections']} browser projection(s).");
            foreach ($plan['documents'] as $document) {
                $reason = isset($document['reason']) ? " — {$document['reason']}" : '';
                $this->line("{$document['subject']['identifier']} · {$document['identifier']}: {$document['status']}{$reason}");
            }
            $this->warn('Document driver unavailable: x-document not installed.');
            $this->warn('Settlement driver unavailable: x-change not configured.');
            $this->components->info('Compilation plan completed.');
        }

        return self::SUCCESS;
    }
}
