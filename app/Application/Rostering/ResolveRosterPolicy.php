<?php

namespace App\Application\Rostering;

use App\Domain\Rostering\ResolvedRosterPolicy;
use Illuminate\Filesystem\Filesystem;

final readonly class ResolveRosterPolicy
{
    public function __construct(private Filesystem $files) {}

    public function handle(): ResolvedRosterPolicy
    {
        $paths = collect(['active-doctor-only-assignment.yaml', 'leave-prohibits-assignment.yaml', 'unavailability-prohibits-assignment.yaml', 'unique-daily-assignment.yaml', 'daily-staffing-requirement.yaml', 'explicit-required-hours.yaml', 'explicit-availability-is-evidence.yaml', 'unspecified-doctor-interim-eligibility.yaml', 'under-target-doctor-priority.yaml', 'preferred-work-preference.yaml', 'preferred-off-avoidance.yaml', 'assignment-count-balance.yaml', 'stable-identifier-tie-break.yaml', 'generation-requires-valid-inputs.yaml'])->map(fn (string $file): string => "business/profiles/anaesthesia-rostering/policies/{$file}")->all();
        $content = collect($paths)->map(fn (string $path): string => $path."\n".$this->files->get(base_path($path)))->implode("\n");

        return new ResolvedRosterPolicy('PROFILE-ANAESTHESIA-ROSTERING', 1, 'balanced_greedy', '1.0', $paths, 'sha256:'.hash('sha256', $content));
    }
}
