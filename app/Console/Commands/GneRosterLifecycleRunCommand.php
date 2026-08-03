<?php

namespace App\Console\Commands;

use App\Application\Rostering\RunManualRosterScenario;
use App\Application\Rostering\RunRequestsAvailabilityScenario;
use App\Application\Rostering\RunRosterLifecycleScenario;
use App\Domain\Rostering\RosterLifecycleScenarioDefinition;
use App\Infrastructure\Rostering\PrintManualRosterPdf;
use App\Infrastructure\Rostering\PrintRequestsAvailabilityPdf;
use App\Infrastructure\Rostering\RenderManualRosterReport;
use App\Infrastructure\Rostering\RenderRequestsAvailabilityReport;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

final class GneRosterLifecycleRunCommand extends Command
{
    protected $signature = 'gne:roster:lifecycle:run {--scenario=ANAESTHESIA-ROSTER-FOUNDATION-LIFECYCLE} {--repository= : Repository root} {--keep-state : Commit scenario state} {--artifact : Generate finalized JSON, HTML, and PDF evidence} {--output= : Artifact output directory} {--json : Emit deterministic JSON}';

    protected $description = 'Run the allowlisted anaesthesia roster foundation lifecycle proof';

    public function handle(RunRosterLifecycleScenario $runner, RunRequestsAvailabilityScenario $requestsRunner, RunManualRosterScenario $manualRunner, RenderRequestsAvailabilityReport $render, PrintRequestsAvailabilityPdf $print, RenderManualRosterReport $manualRender, PrintManualRosterPdf $manualPrint, Filesystem $files): int
    {
        if (! in_array($this->option('scenario'), ['ANAESTHESIA-ROSTER-FOUNDATION-LIFECYCLE', 'ANAESTHESIA-ROSTER-REQUESTS-AND-AVAILABILITY', 'ANAESTHESIA-MANUAL-ROSTER'], true)) {
            $this->error('Unknown roster lifecycle scenario.');

            return self::FAILURE;
        }
        $root = rtrim((string) ($this->option('repository') ?: base_path()), '/');
        $requestsScenario = $this->option('scenario') === 'ANAESTHESIA-ROSTER-REQUESTS-AND-AVAILABILITY';
        $manualScenario = $this->option('scenario') === 'ANAESTHESIA-MANUAL-ROSTER';
        $source = $manualScenario ? 'manual-roster.yaml' : ($requestsScenario ? 'requests-and-availability.yaml' : 'foundation-lifecycle.yaml');
        $definition = RosterLifecycleScenarioDefinition::fromFile($root.'/business/profiles/anaesthesia-rostering/scenarios/'.$source);
        $result = $manualScenario
            ? $manualRunner->handle($definition, (bool) $this->option('keep-state'))
            : ($requestsScenario
                ? $requestsRunner->handle($definition, (bool) $this->option('keep-state'))
                : $runner->handle($definition, (bool) $this->option('keep-state')));
        $payload = $result->toArray();
        if ($requestsScenario && $this->option('artifact')) {
            $artifactRoot = rtrim((string) ($this->option('output') ?: base_path('.gne/reports/rostering/requests-and-availability')), '/');
            $files->ensureDirectoryExists($artifactRoot);
            $html = $render->handle($artifactRoot, $payload);
            $pdf = $print->handle($artifactRoot);
            $payload['artifacts'] = ['status' => 'final', 'html' => $html, 'pdf' => $pdf];
            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
            $files->put($artifactRoot.'/report.json', $json);
            $payload['artifacts']['json'] = ['path' => 'report.json', 'sha256' => hash('sha256', $json), 'byte_length' => strlen($json)];
        }
        if ($manualScenario && $this->option('artifact')) {
            $artifactRoot = rtrim((string) ($this->option('output') ?: base_path('.gne/reports/rostering/manual-roster')), '/');
            $files->ensureDirectoryExists($artifactRoot);
            $html = $manualRender->handle($artifactRoot, $payload);
            $pdf = $manualPrint->handle($artifactRoot);
            $payload['artifacts'] = ['status' => 'final', 'html' => $html, 'pdf' => $pdf];
            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
            $files->put($artifactRoot.'/report.json', $json);
            $payload['artifacts']['json'] = ['path' => 'report.json', 'sha256' => hash('sha256', $json), 'byte_length' => strlen($json)];
        }
        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $scenarioIdentifier = ($requestsScenario || $manualScenario) ? $payload['scenario']['identifier'] : $payload['scenario'];
            $this->info("Roster lifecycle scenario: {$scenarioIdentifier}");
            foreach ($payload['steps'] ?? [] as $step) {
                $this->line(strtoupper($step['status'])." {$step['sequence']}. ".($step['title'] ?? $step['id']));
            }
        }

        $passed = ($requestsScenario || $manualScenario) ? $payload['scenario']['passed'] : $payload['passed'];

        return $passed ? self::SUCCESS : self::FAILURE;
    }
}
