<?php

namespace App\Console\Commands;

use App\Domain\Materialization\MaterializeRepository;
use App\Domain\Repository\ValidateRepository;
use App\Domain\Semantics\BuildSemanticIndex;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class GneRebuildCommand extends Command
{
    protected $signature = 'gne:rebuild {--force : Confirm disposable projection replacement in non-interactive environments} {--json : Emit structured JSON}';

    protected $description = 'Validate and rebuild disposable semantic and database projections';

    public function handle(ValidateRepository $validator, BuildSemanticIndex $indexer, MaterializeRepository $materializer, Filesystem $files): int
    {
        $manifest = $validator->handle(base_path());
        if ($manifest->hasErrors()) {
            $this->components->error('Rebuild stopped because repository validation failed.');

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm('Replace disposable semantic files and database projection rows? Canonical business files are never deleted.')) {
            return self::FAILURE;
        }
        $semanticPath = base_path((string) $manifest->generatedPath.'/semantic');
        if ($files->isDirectory($semanticPath)) {
            $files->deleteDirectory($semanticPath);
        }
        $indexCounts = $indexer->handle(base_path(), $manifest);
        $materialization = $materializer->handle($manifest);
        $result = ['status' => 'completed', 'cleared' => [(string) $manifest->generatedPath.'/semantic', 'database projection rows'], 'index' => $indexCounts, 'materialization' => $materialization];
        $this->option('json') ? $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) : $this->components->info('Disposable projections rebuilt; canonical repository source was unchanged.');

        return self::SUCCESS;
    }
}
