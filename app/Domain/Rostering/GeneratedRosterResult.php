<?php

namespace App\Domain\Rostering;

final readonly class GeneratedRosterResult
{
    /** @param list<array<string, mixed>> $assignments @param list<array<string, mixed>> $dailyStaffing @param list<array<string, mixed>> $doctorHours @param list<array<string, mixed>> $preferences @param list<array<string, mixed>> $findings */
    public function __construct(public array $assignments, public array $dailyStaffing, public array $doctorHours, public array $preferences, public array $findings, public string $status, public string $fingerprint, public ?RosterGenerationFeasibility $feasibility = null, public ?DraftRosterQualityResult $quality = null) {}

    public function hasErrors(): bool
    {
        return collect($this->findings)->contains(fn (array $finding): bool => $finding['severity'] === 'error');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['status' => $this->status, 'fingerprint' => $this->fingerprint, 'feasibility' => $this->feasibility?->toArray(), 'quality' => $this->quality?->toArray(), 'summary' => ['assignments_created' => count($this->assignments), 'dates_evaluated' => count($this->dailyStaffing), 'required_slots' => array_sum(array_column($this->dailyStaffing, 'required')), 'fully_staffed_dates' => collect($this->dailyStaffing)->where('status', 'fully_staffed')->count(), 'understaffed_dates' => collect($this->dailyStaffing)->where('status', 'understaffed')->count(), 'overstaffed_dates' => 0, 'doctors_below_target' => collect($this->doctorHours)->where('status', 'below_target')->count(), 'doctors_above_target' => collect($this->doctorHours)->where('status', 'above_target')->count(), 'preferred_work_honored' => collect($this->preferences)->where('type', 'preferred_work')->where('honored', true)->count(), 'preferred_work_unhonored' => collect($this->preferences)->where('type', 'preferred_work')->where('honored', false)->count(), 'preferred_off_honored' => collect($this->preferences)->where('type', 'preferred_off')->where('honored', true)->count(), 'preferred_off_violated' => collect($this->preferences)->where('type', 'preferred_off')->where('honored', false)->count(), 'errors' => collect($this->findings)->where('severity', 'error')->count(), 'warnings' => collect($this->findings)->where('severity', 'warning')->count()], 'assignments' => $this->assignments, 'daily_staffing' => $this->dailyStaffing, 'doctor_hours' => $this->doctorHours, 'preferences' => $this->preferences, 'findings' => $this->findings];
    }

    public function withAnalysis(RosterGenerationFeasibility $feasibility, DraftRosterQualityResult $quality): self
    {
        $findings = collect($this->findings)->reject(fn (array $finding): bool => in_array($finding['code'], ['DOCTOR_HOURS_BELOW_TARGET', 'DOCTOR_HOURS_ABOVE_TARGET'], true))->concat($quality->findings)->sortBy(fn (array $finding): string => ($finding['severity'] === 'error' ? '0' : '1').'|'.($finding['date'] ?? '').'|'.($finding['doctor_identifier'] ?? '').'|'.$finding['code'])->values()->all();
        $status = collect($findings)->contains(fn (array $finding): bool => $finding['severity'] === 'error') ? 'invalid' : ($findings === [] ? 'valid' : 'valid_with_warnings');

        return new self($this->assignments, $this->dailyStaffing, $this->doctorHours, $this->preferences, $findings, $status, $this->fingerprint, $feasibility, $quality);
    }
}
