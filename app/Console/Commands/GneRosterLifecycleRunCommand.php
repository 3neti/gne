<?php

namespace App\Console\Commands;

use App\Application\Rostering\RunRosterLifecycleScenario;
use App\Domain\Rostering\RosterLifecycleScenarioDefinition;
use Illuminate\Console\Command;

final class GneRosterLifecycleRunCommand extends Command
{
    protected $signature = 'gne:roster:lifecycle:run {--scenario=ANAESTHESIA-ROSTER-FOUNDATION-LIFECYCLE} {--repository= : Repository root} {--keep-state : Commit scenario state} {--json : Emit deterministic JSON}';

    protected $description = 'Run the allowlisted anaesthesia roster foundation lifecycle proof';

    public function handle(RunRosterLifecycleScenario $runner): int
    {
        if ($this->option('scenario') !== 'ANAESTHESIA-ROSTER-FOUNDATION-LIFECYCLE') {
            $this->error('Unknown roster lifecycle scenario.');

            return self::FAILURE;
        }
        $root = rtrim((string) ($this->option('repository') ?: base_path()), '/');
        $definition = RosterLifecycleScenarioDefinition::fromFile($root.'/business/profiles/anaesthesia-rostering/scenarios/foundation-lifecycle.yaml');
        $result = $runner->handle($definition, (bool) $this->option('keep-state'));
        if ($this->option('json')) {
            $this->line(json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->info("Roster lifecycle scenario: {$result->scenario}");
            foreach ($result->steps as $step) {
                $this->line(strtoupper($step['status'])." {$step['sequence']}. {$step['title']}");
            }
        }

        return $result->passed ? self::SUCCESS : self::FAILURE;
    }
}
