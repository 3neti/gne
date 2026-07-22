<?php

namespace App\Console\Commands;

use App\Domain\Repository\ValidateRepository;
use Illuminate\Console\Command;

class GneValidateCommand extends Command
{
    protected $signature = 'gne:validate {--json : Emit structured JSON}';

    protected $description = 'Validate canonical GNE repository source without using the database';

    public function handle(ValidateRepository $validator): int
    {
        $manifest = $validator->handle(base_path());
        if ($this->option('json')) {
            $this->line(json_encode(['valid' => ! $manifest->hasErrors(), ...$manifest->toArray()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->components->info(sprintf('Discovered %d profile(s), %d scenario(s), and %d artifact revision(s).', count($manifest->profiles), count($manifest->scenarios), count($manifest->artifacts)));
            foreach ($manifest->findings as $finding) {
                $this->line(strtoupper($finding->severity->value)." [{$finding->code}] {$finding->message}".($finding->sourcePath ? " ({$finding->sourcePath})" : ''));
            }
            $manifest->hasErrors() ? $this->components->error('Repository validation failed.') : $this->components->info('Repository validation passed.');
        }

        return $manifest->hasErrors() ? self::FAILURE : self::SUCCESS;
    }
}
