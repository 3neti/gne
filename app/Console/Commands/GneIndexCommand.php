<?php

namespace App\Console\Commands;

use App\Domain\Repository\ValidateRepository;
use App\Domain\Semantics\BuildSemanticIndex;
use Illuminate\Console\Command;

class GneIndexCommand extends Command
{
    protected $signature = 'gne:index {--json : Emit structured JSON}';

    protected $description = 'Rebuild the disposable semantic index from canonical repository evidence';

    public function handle(ValidateRepository $validator, BuildSemanticIndex $indexer): int
    {
        $manifest = $validator->handle(base_path());
        if ($manifest->hasErrors()) {
            $this->components->error('Indexing stopped because repository validation failed.');

            return self::FAILURE;
        }
        $counts = $indexer->handle(base_path(), $manifest);
        $this->option('json') ? $this->line(json_encode(['status' => 'completed', 'counts' => $counts], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) : $this->components->info('Semantic index rebuilt: '.collect($counts)->map(fn (int $count, string $name): string => "{$count} {$name}")->implode(', ').'.');

        return self::SUCCESS;
    }
}
