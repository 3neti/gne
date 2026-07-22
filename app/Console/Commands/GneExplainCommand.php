<?php

namespace App\Console\Commands;

use App\Domain\Repository\ExplainRepository;
use App\Domain\Repository\ValidateRepository;
use Illuminate\Console\Command;

class GneExplainCommand extends Command
{
    protected $signature = 'gne:explain {--json : Emit structured JSON} {--profile= : Limit the explanation to one profile slug or identifier}';

    protected $description = 'Explain this GNE repository from deterministic repository evidence';

    public function handle(ValidateRepository $validator, ExplainRepository $explainer): int
    {
        $manifest = $validator->handle(base_path());
        $explanation = $explainer->handle(base_path(), $manifest);
        if ($profile = $this->option('profile')) {
            $explanation['profiles'] = collect($explanation['profiles'])->filter(fn (array $item): bool => in_array($profile, [$item['identifier'], $item['slug']], true))->values()->all();
            $explanation['scenarios'] = collect($explanation['scenarios'])->filter(fn (array $item): bool => in_array($item['profile'], [$profile, $explanation['profiles'][0]['identifier'] ?? null], true))->values()->all();
            if ($explanation['profiles'] === []) {
                $this->components->error("Profile {$profile} was not found.");

                return self::FAILURE;
            }
        }
        if ($this->option('json')) {
            $this->line(json_encode($explanation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->components->info($explanation['repository']);
            $this->line($explanation['thesis']);
            $this->table(['Canonical source', 'Generated state', 'Validation', 'Profiles', 'Scenarios', 'Artifacts'], [[$explanation['canonical_source_path'], $explanation['generated_projection_path'], $explanation['validation']['valid'] ? 'valid' : 'invalid', count($explanation['profiles']), count($explanation['scenarios']), $explanation['artifact_count']]]);
            $this->line('Materialization: '.$explanation['materialization']['status']);
        }

        return $manifest->hasErrors() ? self::FAILURE : self::SUCCESS;
    }
}
