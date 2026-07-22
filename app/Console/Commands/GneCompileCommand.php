<?php

namespace App\Console\Commands;

use App\Domain\Compilation\PrepareCompilationPlan;
use App\Domain\Repository\ValidateRepository;
use App\Domain\Semantics\BuildSemanticIndex;
use Illuminate\Console\Command;

class GneCompileCommand extends Command
{
    protected $signature = 'gne:compile {--json : Emit structured JSON}';

    protected $description = 'Validate, index, and prepare an honest GNE compilation plan';

    public function handle(ValidateRepository $validator, BuildSemanticIndex $indexer, PrepareCompilationPlan $planner): int
    {
        $manifest = $validator->handle(base_path());
        if ($manifest->hasErrors()) {
            $this->components->error('Compilation planning stopped because validation failed.');

            return self::FAILURE;
        }
        $indexer->handle(base_path(), $manifest);
        $plan = $planner->handle(base_path(), $manifest);
        if ($this->option('json')) {
            $this->line(json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->components->info('Validated repository.');
            $this->line("Resolved {$plan['profiles']} profile(s), {$plan['scenarios']} scenario(s), and {$plan['artifacts']} artifact revision(s).");
            $this->line("Compiled {$plan['resolved_documents']} resolved document(s) into {$plan['browser_projections']} browser projection(s).");
            foreach ($plan['documents'] as $document) {
                $reason = isset($document['reason']) ? " — {$document['reason']}" : '';
                $this->line("{$document['identifier']}: {$document['status']}{$reason}");
            }
            $this->warn('Document driver unavailable: x-document not installed.');
            $this->warn('Settlement driver unavailable: x-change not configured.');
            $this->components->info('Compilation plan completed.');
        }

        return self::SUCCESS;
    }
}
