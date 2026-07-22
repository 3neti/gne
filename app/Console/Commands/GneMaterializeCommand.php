<?php

namespace App\Console\Commands;

use App\Domain\Materialization\MaterializeRepository;
use App\Domain\Repository\ValidateRepository;
use Illuminate\Console\Command;

class GneMaterializeCommand extends Command
{
    protected $signature = 'gne:materialize {--json : Emit structured JSON}';

    protected $description = 'Materialize repository truth into rebuildable database projections';

    public function handle(ValidateRepository $validator, MaterializeRepository $materializer): int
    {
        $manifest = $validator->handle(base_path());
        if ($manifest->hasErrors()) {
            $this->components->error('Materialization stopped because repository validation failed.');

            return self::FAILURE;
        }
        $result = $materializer->handle($manifest);
        $this->option('json') ? $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) : $this->components->info("Materialization run {$result['run_id']} completed ({$result['fingerprint']}).");

        return self::SUCCESS;
    }
}
