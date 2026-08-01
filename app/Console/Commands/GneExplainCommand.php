<?php

namespace App\Console\Commands;

use App\Domain\Repository\ExplainRepository;
use App\Domain\Repository\ValidateRepository;
use App\Integration\XDocument\XDocumentRuntimeDiagnostics;
use Illuminate\Console\Command;

class GneExplainCommand extends Command
{
    protected $signature = 'gne:explain {--json : Emit structured JSON} {--profile= : Limit the explanation to one profile slug or identifier}';

    protected $description = 'Explain this GNE repository from deterministic repository evidence';

    public function handle(ValidateRepository $validator, ExplainRepository $explainer, XDocumentRuntimeDiagnostics $runtime): int
    {
        $manifest = $validator->handle(base_path());
        $explanation = $explainer->handle(base_path(), $manifest);
        $explanation['x_document_runtime'] = $runtime->toArray();
        if ($profile = $this->option('profile')) {
            $explanation['profiles'] = array_values(array_filter($manifest->profiles, fn (array $item): bool => in_array($profile, [$item['identifier'], $item['slug']], true)));
            $explanation['scenarios'] = array_values(array_filter($manifest->scenarios, fn (array $item): bool => in_array($item['profile'], [$profile, $explanation['profiles'][0]['identifier'] ?? null], true)));
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
            $this->line('x-document installed: '.($explanation['x_document_runtime']['x_document_installed'] ? 'yes' : 'no'));
            $this->line('x-document baseline matches: '.($explanation['x_document_runtime']['x_document_baseline_matches'] ? 'yes' : 'no'));
            $this->line('x-document-laravel installed: '.($explanation['x_document_runtime']['x_document_laravel_installed'] ? 'yes' : 'no'));
            $this->line('x-document-laravel baseline matches: '.($explanation['x_document_runtime']['x_document_laravel_baseline_matches'] ? 'yes' : 'no'));
            $this->line('Contract smoke passed: '.($explanation['x_document_runtime']['contract_smoke_passed'] ? 'yes' : 'no'));
            $this->line('Browser composition available: '.($explanation['x_document_runtime']['browser_composition_available'] ? 'yes' : 'no'));
            $this->line('HTTP response factory bound: '.($explanation['x_document_runtime']['http_factory_bound'] ? 'yes' : 'no'));
            $this->line('Authenticated browser route available: '.($explanation['x_document_runtime']['authenticated_http_route_available'] ? 'yes' : 'no'));
        }

        return $manifest->hasErrors() ? self::FAILURE : self::SUCCESS;
    }
}
