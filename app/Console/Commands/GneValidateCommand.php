<?php

namespace App\Console\Commands;

use App\Domain\Repository\ValidateRepository;
use App\Domain\Repository\ValidationSeverity;
use Illuminate\Console\Command;

class GneValidateCommand extends Command
{
    protected $signature = 'gne:validate {--repository= : Repository root (defaults to the application root)} {--json : Emit structured JSON}';

    protected $description = 'Validate canonical GNE repository source without using the database';

    public function handle(ValidateRepository $validator): int
    {
        $repositoryRoot = is_string($this->option('repository')) ? $this->option('repository') : base_path();
        $manifest = $validator->handle($repositoryRoot);
        $summary = [
            'errors' => collect($manifest->findings)->where('severity', ValidationSeverity::Error)->count(),
            'warnings' => collect($manifest->findings)->where('severity', ValidationSeverity::Warning)->count(),
            'info' => collect($manifest->findings)->where('severity', ValidationSeverity::Info)->count(),
        ];
        if ($this->option('json')) {
            $this->line(json_encode(['valid' => ! $manifest->hasErrors(), 'fingerprint' => $manifest->fingerprint, 'summary' => $summary, 'findings' => array_map(fn ($finding): array => $finding->toArray(), $manifest->findings)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->components->info(sprintf('Discovered %d profile(s), %d scenario(s), and %d artifact revision(s).', count($manifest->profiles), count($manifest->scenarios), count($manifest->artifacts)));
            foreach ($manifest->findings as $finding) {
                $this->line(strtoupper($finding->severity->value)." [{$finding->code}] {$finding->message}".($finding->sourcePath ? " ({$finding->sourcePath}".($finding->location ? ":{$finding->location}" : '').')' : ''));
            }
            $manifest->hasErrors() ? $this->components->error('Repository validation failed.') : $this->components->info('Repository validation passed.');
        }

        return $manifest->hasErrors() ? self::FAILURE : self::SUCCESS;
    }
}
