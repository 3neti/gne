<?php

namespace App\Infrastructure\Rostering;

use Illuminate\Filesystem\Filesystem;

final readonly class RenderDraftGenerationReport
{
    public function __construct(private Filesystem $files) {}

    /** @param array<string, mixed> $report @return array{entrypoint: string, pages: int, aggregate_fingerprint: string} */
    public function handle(string $root, array $report): array
    {
        $html = $root.'/html';
        $this->files->deleteDirectory($html);
        $this->files->ensureDirectoryExists($html.'/assets');
        $this->files->put($html.'/assets/report.css', $this->css());

        $pages = [
            'index.html' => ['Anaesthesia Department Generated Draft Roster', $this->summary($report)],
            'generation-summary.html' => ['Generation Result and Current Roster', $this->stateSummary($report)],
            'feasibility.html' => ['Staffing-demand and Target-hours Feasibility', $this->feasibility($report)],
            'quality-summary.html' => ['Generation Quality Summary', $this->qualitySummary($report)],
            'generated-calendar.html' => ['Original Generated Four-week Roster Calendar', $this->calendar($report['generation_state'], 'Revision 1 - state immediately after generation')],
            'current-roster-calendar.html' => ['Current Four-week Roster Calendar', $this->calendar($report['current_state'], 'Revision 2 - state after the manual correction')],
            'assignments-by-date.html' => ['Generated Assignments by Date', $this->assignmentsByDate($report)],
            'doctor-matrix-week-1.html' => ['Doctor-by-date Matrix - Week 1', $this->matrix($report['current_state'], 0)],
            'doctor-matrix-week-2.html' => ['Doctor-by-date Matrix - Week 2', $this->matrix($report['current_state'], 1)],
            'doctor-matrix-week-3.html' => ['Doctor-by-date Matrix - Week 3', $this->matrix($report['current_state'], 2)],
            'doctor-matrix-week-4.html' => ['Doctor-by-date Matrix - Week 4', $this->matrix($report['current_state'], 3)],
            'doctor-hours.html' => ['Doctor Hours', $this->doctorHours($report)],
            'hours-balance.html' => ['Target-adjusted Hours Balance', $this->hoursBalance($report)],
            'weekend-balance.html' => ['Weekend Assignment Distribution', $this->weekendBalance($report)],
            'availability-use.html' => ['Explicit and Unspecified Availability Use', $this->availabilityUse($report)],
            'staffing.html' => ['Daily Staffing Summary', $this->staffing($report)],
            'preference-analysis.html' => ['Preference Fulfillment', $this->preferences($report)],
            'explanations.html' => ['Assignment Explanations', $this->explanations($report)],
            'validation.html' => ['Validation Findings', $this->validation($report)],
            'revisions.html' => ['Generation Revision and Manual Correction', $this->revisions($report)],
        ];

        foreach ($pages as $file => [$title, $body]) {
            $this->files->put($html.'/'.$file, $this->page($title, $body));
        }

        $this->files->put($html.'/print.html', $this->page('Anaesthesia Generated Draft Roster', $this->print($report), 'print'));
        $assets = collect($this->files->allFiles($html))->map(fn (\SplFileInfo $file): string => str_replace('\\', '/', $file->getRelativePathname())."\0".hash_file('sha256', $file->getPathname()))->sort()->values()->all();

        return ['entrypoint' => 'html/index.html', 'pages' => count($pages), 'aggregate_fingerprint' => hash('sha256', implode("\0", $assets))];
    }

    /** @param array<string, mixed> $report */
    private function summary(array $report): string
    {
        return '<p class="notice"><b>Automatically Generated Draft - Not Yet Published.</b> Editable and not mathematically optimal. The generation result and current post-correction roster are shown separately.</p>'.$this->stateSummary($report).'<h2>Generation assumptions and fingerprints</h2>'.$this->facts($report).'<nav>'.$this->links().'</nav>';
    }

    /** @param array<string, mixed> $report */
    private function stateSummary(array $report): string
    {
        $generated = $this->staffingCounts($report['generation_state']);
        $current = $this->staffingCounts($report['current_state']);

        return '<div class="state-grid"><article><h2>Generation result</h2><p>Revision 1 - immediately after generation.</p>'.$this->stats(['assignments' => count($report['generation_state']['assignments']), 'fully_staffed' => $generated['fully_staffed'], 'understaffed' => $generated['understaffed'], 'overstaffed' => $generated['overstaffed']]).'<p>'.$report['generated_validation']['errors'].' errors and '.$report['generated_validation']['warnings'].' warnings.</p></article><article><h2>Current scenario roster</h2><p>Revision 2 - after one manual move.</p>'.$this->stats(['assignments' => count($report['current_state']['assignments']), 'fully_staffed' => $current['fully_staffed'], 'understaffed' => $current['understaffed'], 'overstaffed' => $current['overstaffed']]).'<p>'.$report['current_state']['validation']['errors'].' errors and '.$report['current_state']['validation']['warnings'].' warnings.</p></article></div>';
    }

    /** @param array<string, mixed> $state */
    private function calendar(array $state, string $caption): string
    {
        $weeks = implode('', array_map(function (array $week): string {
            $days = implode('', array_map(function (array $day): string {
                $doctors = implode('', array_map(fn (array $doctor): string => '<li><b>'.$this->e($doctor['doctor_name']).'</b><small>'.$this->e($this->provenance($doctor['source'], $doctor['revision'])).'</small></li>', $day['assigned_doctors']));
                $findings = $day['findings'] === [] ? 'None' : implode(', ', array_column($day['findings'], 'code'));

                return '<article class="day '.$this->e($day['staffing_status']).'"><h3>'.$this->e($day['weekday']).'<small>'.$this->e($day['date']).'</small></h3><p><b>Required:</b> '.$day['required_count'].' <b>Assigned:</b> '.$day['assigned_count'].' <b>Variance:</b> '.$day['variance'].'</p><p class="status">'.$this->statusLabel($day['staffing_status']).'</p><ul>'.$doctors.'</ul><p class="findings"><b>Findings:</b> '.$this->e($findings).'</p></article>';
            }, $week['dates']));

            return '<section class="week"><h2>Week '.$week['week'].'</h2><div class="week-grid">'.$days.'</div></section>';
        }, $state['weeks']));

        return '<p class="state-label">'.$this->e($caption).'</p>'.$this->legend().$weeks;
    }

    /** @param array<string, mixed> $report */
    private function assignmentsByDate(array $report): string
    {
        return '<h2>Generation state - all 180 generated assignments</h2>'.$this->assignmentStateByDate($report['generation_state']).'<h2>Current state after manual correction</h2>'.$this->assignmentStateByDate($report['current_state']);
    }

    /** @param array<string, mixed> $state */
    private function assignmentStateByDate(array $state): string
    {
        $dates = implode('', array_map(function (array $day): string {
            $rows = array_map(fn (array $doctor): array => [$doctor['doctor_name'], $this->provenance($doctor['source'], $doctor['revision'])], $day['assigned_doctors']);
            $findings = $day['findings'] === [] ? 'None' : implode(', ', array_column($day['findings'], 'code'));

            return '<section class="date-assignments"><h3>'.$this->e($day['date'].' - '.$day['weekday']).'</h3><p>'.$this->e($day['day_type']).' - Required '.$day['required_count'].' - Assigned '.$day['assigned_count'].' - Variance '.$day['variance'].' - '.$this->statusLabel($day['staffing_status']).'</p>'.$this->table(['Doctor', 'Provenance'], $rows).'<p><b>Findings:</b> '.$this->e($findings).'</p></section>';
        }, $state['calendar']));

        return '<div class="assignment-grid">'.$dates.'</div>';
    }

    /** @param array<string, mixed> $state */
    private function matrix(array $state, int $weekIndex): string
    {
        $dates = array_slice($state['calendar'], $weekIndex * 7, 7);
        $heads = array_map(fn (array $day): string => substr($day['date'], 5).'|'.$day['weekday'], $dates);
        $rows = array_map(function (array $doctor) use ($weekIndex): array {
            $cells = array_slice($doctor['dates'], $weekIndex * 7, 7);

            return [$doctor['doctor_name'], ...array_map(fn (array $cell): string => $cell['state'].($cell['revision'] ? ' r'.$cell['revision'] : ''), $cells)];
        }, $state['doctor_matrix']);

        return $this->legend().$this->matrixTable(['Doctor', ...$heads], $rows);
    }

    /** @param array<string, mixed> $report */
    private function doctorHours(array $report): string
    {
        return '<h2>Generation state</h2>'.$this->hoursTable($report['generation_state']['doctor_hours']).'<h2>Current state</h2>'.$this->hoursTable($report['current_state']['doctor_hours']);
    }

    /** @param array<string, mixed> $report */
    private function feasibility(array $report): string
    {
        $feasibility = $report['generation']['feasibility'];

        return $this->stats(['required staffing hours' => $feasibility['total_required_staffing_hours'], 'combined target hours' => $feasibility['total_doctor_target_hours'], 'structural variance' => $feasibility['structural_hours_variance'], 'affected doctors' => $feasibility['affected_doctors']]).'<p class="notice">'.$this->e($feasibility['explanation']).'</p><h2>Assumptions</h2><ul>'.implode('', array_map(fn (string $assumption): string => '<li>'.$this->e($assumption).'</li>', $feasibility['assumptions'])).'</ul>';
    }

    /** @param array<string, mixed> $report */
    private function qualitySummary(array $report): string
    {
        $quality = $report['generation']['quality'];

        return '<p class="quality"><b>Overall quality:</b> '.$this->e(str_replace('_', ' ', $quality['classification'])).'</p>'.$this->stats($quality['metrics']).'<p class="notice">Quality is assessed against feasible staffing demand. Structural target variance is not individual allocation imbalance.</p>';
    }

    /** @param array<string, mixed> $report */
    private function hoursBalance(array $report): string
    {
        return $this->table(['Doctor', 'Target', 'Assigned', 'Raw variance', 'Structural allocation', 'Residual variance', 'Interpretation'], array_map(fn (array $doctor): array => [$doctor['doctor_name'], $doctor['required_hours'], $doctor['assigned_hours'], $doctor['raw_variance'], $doctor['allocated_structural_variance'], $doctor['residual_variance'], str_replace('_', ' ', $doctor['quality_status'])], $report['generation']['quality']['doctor_hours']));
    }

    /** @param array<string, mixed> $report */
    private function weekendBalance(array $report): string
    {
        $quality = $report['generation']['quality'];

        return $this->table(['Doctor', 'Weekdays', 'Saturdays', 'Sundays', 'Total weekends'], array_map(fn (array $doctor): array => [$doctor['doctor_name'], $doctor['weekday_assignments'], $doctor['saturday_assignments'], $doctor['sunday_assignments'], $doctor['weekend_assignments']], $quality['weekend_distribution'])).'<p class="notice">Consecutive-day reporting is descriptive only. No fatigue or rest-period policy is currently enforced. Maximum consecutive assigned days: '.$quality['metrics']['maximum_consecutive_assigned_days'].'.</p>';
    }

    /** @param array<string, mixed> $report */
    private function availabilityUse(array $report): string
    {
        return $this->stats($report['generation']['quality']['availability_use']).'<p>Explicit availability is positive evidence. Assignments from unspecified eligibility rely on the documented provisional policy and do not imply that a doctor affirmatively volunteered.</p>';
    }

    /** @param list<array<string, mixed>> $hours */
    private function hoursTable(array $hours): string
    {
        return $this->table(['Doctor', 'Target', 'Assigned', 'Variance', 'Status'], array_map(fn (array $doctor): array => [$doctor['doctor_name'], $doctor['required_hours'], $doctor['assigned_hours'], $doctor['variance'], $doctor['status']], $hours));
    }

    /** @param array<string, mixed> $report */
    private function staffing(array $report): string
    {
        return '<h2>Generation state</h2>'.$this->staffingTable($report['generation_state']).'<h2>Current state</h2>'.$this->staffingTable($report['current_state']);
    }

    /** @param array<string, mixed> $state */
    private function staffingTable(array $state): string
    {
        return $this->table(['Date', 'Required', 'Assigned', 'Variance', 'Status'], array_map(fn (array $day): array => [$day['date'], $day['required_count'], $day['assigned_count'], $day['variance'], $this->statusLabel($day['staffing_status'])], $state['calendar']));
    }

    /** @param array<string, mixed> $report */
    private function preferences(array $report): string
    {
        return $this->table(['Doctor', 'Date', 'Preference', 'Influence', 'Outcome', 'Explanation'], array_map(fn (array $item): array => [$item['doctor_name'], $item['date'], str_replace('_', ' ', $item['type']), $item['ranking_influence'], $item['honored'] ? 'honored' : 'not honored', $item['explanation']], $report['generation']['preferences']));
    }

    /** @param array<string, mixed> $report */
    private function explanations(array $report): string
    {
        return implode('', array_map(function (array $assignment): string {
            $selectedOver = $assignment['explanation']['selected_over'];
            $reasons = implode('; ', array_map(fn (string $code): string => $this->humanReason($code), $assignment['explanation']['reason_codes']));
            $comparison = $this->selectedOverExplanation($assignment);

            return '<article class="explanation"><h2>'.$this->e($assignment['doctor_name'].' - '.$assignment['date']).'</h2><p>'.$this->e($reasons).'</p><p><b>Selected over:</b> '.$this->e($comparison).'</p></article>';
        }, $report['generation']['assignments']));
    }

    /** @param array<string, mixed> $report */
    private function validation(array $report): string
    {
        return '<h2>Generation state</h2>'.$this->findingsTable($report['generated_validation']['findings']).'<h2>Current state</h2>'.$this->findingsTable($report['current_state']['validation']['findings']);
    }

    /** @param list<array<string, mixed>> $findings */
    private function findingsTable(array $findings): string
    {
        return $this->table(['Severity', 'Code', 'Message'], array_map(fn (array $finding): array => [$finding['severity'], $finding['code'], $finding['message']], $findings));
    }

    /** @param array<string, mixed> $report */
    private function revisions(array $report): string
    {
        $moved = collect($report['current_state']['assignments'])->firstWhere('source', 'manually_changed');

        return '<p><b>Generation revision:</b> '.$this->e($report['batch']['generation_revision']).' - '.$report['batch']['revision_changes'].' assignment changes - '.$report['batch']['generation_audits'].' generation audit.</p><p><b>Manual correction:</b> revision '.$report['manual_correction']['revision'].' - operation move - source '.$this->e($report['manual_correction']['source']).'.</p><p><b>Changed assignment:</b> '.$this->e($moved['doctor_name'].' - '.$moved['date'].' - '.$this->provenance($moved['source'], $moved['revision'])).'.</p><p>The manual correction changed current roster validity but does not retroactively alter the historical generation-quality assessment.</p>';
    }

    /** @param array<string, mixed> $report */
    private function print(array $report): string
    {
        $sections = [
            '<section class="cover"><h1>Anaesthesia Department Generated Draft Roster</h1><p>Automatically Generated Draft - Not Yet Published</p></section>',
            '<section class="page"><h1>Generation Result and Current Roster</h1>'.$this->stateSummary($report).'</section>',
            '<section class="page"><h1>Staffing-demand and Target-hours Feasibility</h1>'.$this->feasibility($report).'</section>',
            '<section class="page"><h1>Generation Quality Summary</h1>'.$this->qualitySummary($report).'</section>',
            '<section class="page"><h1>Generation Assumptions and Fingerprints</h1>'.$this->facts($report).'</section>',
        ];
        foreach ([['Original Generated Roster', $report['generation_state']], ['Current Roster After Manual Correction', $report['current_state']]] as [$title, $state]) {
            foreach ($state['weeks'] as $week) {
                $weekState = $state;
                $weekState['weeks'] = [$week];
                $sections[] = '<section class="page"><h1>'.$this->e($title).' - Week '.$week['week'].'</h1>'.$this->calendar($weekState, $title).'</section>';
            }
        }
        foreach ($report['current_state']['weeks'] as $week) {
            $weekState = $report['current_state'];
            $weekState['calendar'] = $week['dates'];
            $sections[] = '<section class="page"><h1>Current Assignments by Date - Week '.$week['week'].'</h1>'.$this->assignmentStateByDate($weekState).'</section>';
        }
        foreach (range(0, 3) as $weekIndex) {
            $sections[] = '<section class="page"><h1>Doctor-by-date Matrix - Week '.($weekIndex + 1).'</h1>'.$this->matrix($report['current_state'], $weekIndex).'</section>';
        }
        $sections[] = '<section class="page"><h1>Daily Staffing Summary - Generation State</h1>'.$this->staffingTable($report['generation_state']).'</section>';
        $sections[] = '<section class="page"><h1>Daily Staffing Summary - Current State</h1>'.$this->staffingTable($report['current_state']).'</section>';
        $sections[] = '<section class="page"><h1>Doctor Hours</h1>'.$this->doctorHours($report).'</section>';
        $sections[] = '<section class="page"><h1>Target-adjusted Hours Balance</h1>'.$this->hoursBalance($report).'</section>';
        $sections[] = '<section class="page"><h1>Weekend Assignment Distribution</h1>'.$this->weekendBalance($report).'</section>';
        $sections[] = '<section class="page"><h1>Availability Use</h1>'.$this->availabilityUse($report).'</section>';
        $sections[] = '<section class="page"><h1>Preference Fulfillment</h1>'.$this->preferences($report).'</section>';
        $sections[] = '<section class="page"><h1>Validation Findings</h1>'.$this->validation($report).'</section>';
        $sections[] = '<section class="page"><h1>Representative Assignment Explanations</h1>'.$this->table(['Doctor', 'Date', 'Selection explanation'], array_map(fn (array $assignment): array => [$assignment['doctor_name'], $assignment['date'], implode('; ', array_map(fn (string $code): string => $this->humanReason($code), $assignment['explanation']['reason_codes'])).' '.$this->selectedOverExplanation($assignment)], array_slice($report['generation']['assignments'], 0, 10))).'</section>';
        $sections[] = '<section class="page"><h1>Generation Revision and Manual Correction</h1>'.$this->revisions($report).'</section>';
        $sections[] = '<section class="page conclusion"><h1>Review Conclusion</h1><p>The deterministic generated draft remains editable and requires administrator review before publication. The original generation state and current manually corrected state are deliberately distinct.</p></section>';

        return implode('', $sections);
    }

    /** @param array<string, mixed> $report */
    private function facts(array $report): string
    {
        return '<p>Generator: balanced_greedy 1.0</p><p class="mono">Policy '.$this->e($report['generator']['policy_fingerprint']).'<br>Input '.$this->e($report['generator']['input_fingerprint']).'<br>Generation '.$this->e($report['generator']['generation_fingerprint']).'</p>';
    }

    /** @param array<string, int|float|string> $values */
    private function stats(array $values): string
    {
        return '<div class="stats">'.implode('', array_map(fn (string $key, int|float|string $value): string => '<div><b>'.$this->e(str_replace('_', ' ', $key)).'</b><span>'.$this->e((string) $value).'</span></div>', array_keys($values), $values)).'</div>';
    }

    /** @param array<string, mixed> $state @return array{fully_staffed: int, understaffed: int, overstaffed: int} */
    private function staffingCounts(array $state): array
    {
        $counts = array_count_values(array_column($state['calendar'], 'staffing_status'));

        return ['fully_staffed' => $counts['fully_staffed'] ?? 0, 'understaffed' => $counts['understaffed'] ?? 0, 'overstaffed' => $counts['overstaffed'] ?? 0];
    }

    /** @param list<string> $heads @param list<list<string|int|float|null>> $rows */
    private function table(array $heads, array $rows): string
    {
        return '<table><thead><tr>'.implode('', array_map(fn (string $head): string => '<th>'.$this->e($head).'</th>', $heads)).'</tr></thead><tbody>'.implode('', array_map(fn (array $row): string => '<tr>'.implode('', array_map(fn (mixed $cell): string => '<td>'.$this->e((string) $cell).'</td>', $row)).'</tr>', $rows)).'</tbody></table>';
    }

    /** @param list<string> $heads @param list<list<string>> $rows */
    private function matrixTable(array $heads, array $rows): string
    {
        $head = implode('', array_map(fn (string $value): string => '<th>'.$this->safeHeading($value).'</th>', $heads));
        $body = implode('', array_map(function (array $row): string {
            $cells = implode('', array_map(fn (string $cell): string => '<td>'.$this->e($cell).'</td>', array_slice($row, 1)));

            return '<tr><th>'.$this->e($row[0]).'</th>'.$cells.'</tr>';
        }, $rows));

        return '<table class="matrix"><thead><tr>'.$head.'</tr></thead><tbody>'.$body.'</tbody></table>';
    }

    private function links(): string
    {
        $files = ['generation-summary.html', 'feasibility.html', 'quality-summary.html', 'generated-calendar.html', 'current-roster-calendar.html', 'assignments-by-date.html', 'doctor-matrix-week-1.html', 'doctor-matrix-week-2.html', 'doctor-matrix-week-3.html', 'doctor-matrix-week-4.html', 'doctor-hours.html', 'hours-balance.html', 'weekend-balance.html', 'availability-use.html', 'staffing.html', 'preference-analysis.html', 'explanations.html', 'validation.html', 'revisions.html'];

        return implode(' ', array_map(fn (string $file): string => '<a href="'.$file.'">'.$file.'</a>', $files));
    }

    private function legend(): string
    {
        return '<p class="legend"><b>Legend:</b> G generated assignment - M manually changed assignment - L leave - U unavailable - PW preferred work - PO preferred off - ! finding - r# source revision - dash not assigned. FULL, UNDER, and OVER text labels are authoritative.</p>';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'fully_staffed' => 'FULL',
            'understaffed' => 'UNDER',
            'overstaffed' => 'OVER',
            default => strtoupper($status),
        };
    }

    private function safeHeading(string $value): string
    {
        if (! str_contains($value, '|')) {
            return $this->e($value);
        }

        [$label, $detail] = explode('|', $value, 2);

        return $this->e($label).'<small>'.$this->e($detail).'</small>';
    }

    private function provenance(string $source, int $revision): string
    {
        return match ($source) {
            'generated' => 'Generated draft · revision '.$revision,
            'manually_changed' => 'Manual correction · revision '.$revision,
            default => ucfirst(str_replace('_', ' ', $source)).' · revision '.$revision,
        };
    }

    private function humanReason(string $code): string
    {
        return match ($code) {
            'ACTIVE_DOCTOR' => 'The doctor was active.',
            'ELIGIBLE_ON_DATE' => 'The doctor was eligible on this date.',
            'DAILY_SLOT_REQUIRED' => 'The day still required another doctor.',
            'BELOW_REQUIRED_HOURS' => 'The doctor remained below the authored target at selection time.',
            'LOWER_ASSIGNMENT_COUNT' => 'The doctor had fewer assignments than comparable candidates.',
            'PREFERRED_WORK' => 'A preferred-work request increased the doctor’s ranking.',
            'EXPLICIT_AVAILABLE' => 'Explicit availability increased the doctor’s ranking.',
            'STABLE_TIE_BREAK' => 'A stable deterministic tie-break completed the ranking.',
            default => ucfirst(strtolower(str_replace('_', ' ', $code))).'.',
        };
    }

    /** @param array<string, mixed> $assignment */
    private function selectedOverExplanation(array $assignment): string
    {
        $next = $assignment['explanation']['selected_over'];
        if ($next === null) {
            return 'No next-ranked eligible candidate remained.';
        }

        $selected = $assignment['explanation']['ranked_candidates'][0]['criteria'];
        $nextCriteria = $next['criteria'];
        $difference = match (true) {
            $selected['preferred_work'] !== $nextCriteria['preferred_work'] => $selected['preferred_work'] ? 'the selected doctor had a preferred-work request' : 'the next candidate had a preferred-work request',
            $selected['explicit_availability'] !== $nextCriteria['explicit_availability'] => $selected['explicit_availability'] ? 'the selected doctor was explicitly available' : 'the next candidate was explicitly available',
            $selected['remaining_target_hours'] !== $nextCriteria['remaining_target_hours'] => 'the selected doctor had more remaining target hours',
            $selected['assignment_ratio'] !== $nextCriteria['assignment_ratio'] => 'the selected doctor had a lower assignment ratio',
            $selected['assignment_count'] !== $nextCriteria['assignment_count'] => 'the selected doctor had fewer assignments',
            $selected['preferred_off'] !== $nextCriteria['preferred_off'] => ! $selected['preferred_off'] ? 'the next candidate had a preferred-off request' : 'the selected doctor had a preferred-off request',
            default => 'the stable identifier tie-break resolved otherwise equal ranking facts',
        };

        return $assignment['doctor_name'].' ranked ahead of '.$next['doctor_name'].' because '.$difference.'.';
    }

    private function page(string $title, string $body, string $class = ''): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$this->e($title).'</title><link rel="stylesheet" href="assets/report.css"></head><body class="'.$class.'"><main><header><p class="eyebrow">GNE Anaesthesia Rostering</p><h1>'.$this->e($title).'</h1></header>'.$body.'</main></body></html>';
    }

    private function css(): string
    {
        return ':root{font-family:Inter,Arial,sans-serif;color:#15232c;background:#edf2f3}*{box-sizing:border-box}body{margin:0}main{max-width:1500px;margin:auto;padding:28px;background:#fff;min-height:100vh}h1{font-size:27px}h2{font-size:18px}.eyebrow{text-transform:uppercase;letter-spacing:.14em;color:#126b65;font-weight:700}.notice,.legend,.state-label,.quality{padding:10px 14px;border-left:5px solid #c88400;background:#fff7df}.state-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.state-grid article,.explanation{border:1px solid #ccd8dc;padding:12px}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:7px}.stats div{border:1px solid #ccd8dc;padding:8px}.stats b,.stats span{display:block}.stats span{font-size:22px;color:#126b65}nav{display:flex;gap:8px;flex-wrap:wrap;margin-top:20px}a{color:#126b65}.week{margin:14px 0}.week-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:5px}.day{border:2px solid #83969f;border-radius:7px;padding:7px;break-inside:avoid}.day h3{margin:0}.day h3 small,.day li small,.matrix th small{display:block;font-size:8px}.day p,.day li{font-size:8px}.day ul{padding-left:13px;margin:5px 0}.day .status{font-weight:800;font-size:10px}.day .findings{font-size:7px}.fully_staffed{border-color:#25845c;background:#effaf5}.overstaffed{border-color:#c88400;background:#fff7df}.understaffed{border-color:#b42318;background:#fff1f0}.assignment-grid{display:grid;grid-template-columns:1fr 1fr;gap:3px 10px}.date-assignments{break-inside:avoid;margin:0}.date-assignments h3{font-size:9px;margin:1px 0}.date-assignments p{font-size:6px;margin:1px 0}.date-assignments table{font-size:6px}.date-assignments th,.date-assignments td{padding:1px 2px}table{width:100%;border-collapse:collapse;font-size:9px}th,td{border:1px solid #ccd8dc;padding:4px;text-align:left}.matrix td{text-align:center;font-weight:700;font-size:11px}.mono{font-family:monospace;font-size:10px}.cover,.page{break-after:page;min-height:180mm;padding:4mm}.cover,.conclusion{display:flex;flex-direction:column;justify-content:center}.print main{max-width:none;padding:0}@page{size:A4 landscape;margin:6mm}@media print{body{background:#fff}.week-grid{grid-template-columns:repeat(7,1fr)}.day{padding:4px}.day li{font-size:7px}.page table{font-size:7px}.date-assignments table{font-size:6px}a,header{display:none}}';
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
