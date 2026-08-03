<?php

namespace App\Console\Commands;

use App\Application\Rostering\ValidateRosterFoundation;
use App\Domain\Rostering\FoundationValidationSeverity;
use App\Models\RosterPeriod;
use Illuminate\Console\Command;

class GneRosterValidateCommand extends Command
{
    protected $signature = 'gne:roster:validate {--period= : Stable roster-period identifier} {--json : Emit structured JSON}';

    protected $description = 'Validate the structural anaesthesia rostering foundation';

    public function handle(ValidateRosterFoundation $validator): int
    {
        $period = RosterPeriod::query()->where('identifier', $this->option('period'))->first();
        if ($period === null) {
            $this->error('Roster period was not found.');

            return self::FAILURE;
        }
        $findings = $validator->handle($period);
        $errors = collect($findings)->where('severity', FoundationValidationSeverity::Error)->count();
        if ($this->option('json')) {
            $this->line(json_encode(['valid' => $errors === 0, 'period' => $period->identifier, 'findings' => array_map(fn ($finding): array => $finding->toArray(), $findings)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->info("Roster foundation: {$period->identifier}");
            foreach ($findings as $finding) {
                $this->line(strtoupper($finding->severity->value)." {$finding->code}: {$finding->message}");
            }
            $this->line(count($findings).' finding(s), '.$errors.' error(s).');
        }

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
