<?php

namespace App\Infrastructure\Rostering;

use Illuminate\Filesystem\Filesystem;

final readonly class RenderManualRosterReport
{
    public function __construct(private Filesystem $files) {}

    /**
     * @param  array<string, mixed>  $report
     * @return array{entrypoint: string, pages: int, aggregate_fingerprint: string}
     */
    public function handle(string $root, array $report): array
    {
        $html = $root.'/html';
        $this->files->deleteDirectory($html);
        $this->files->ensureDirectoryExists($html.'/assets');
        $this->files->put($html.'/assets/report.css', $this->css());
        $pages = [
            'index.html' => ['Manual roster report', $this->summary($report)],
            'roster-calendar.html' => ['Assigned roster calendar', $this->calendar($report)],
            'doctor-matrix.html' => ['Doctor assignment matrix', $this->matrix($report)],
            'doctor-hours.html' => ['Doctor assigned hours', $this->hours($report)],
            'staffing.html' => ['Daily staffing', $this->staffing($report)],
            'validation.html' => ['Roster validation', $this->validation($report)],
            'revisions.html' => ['Complete revision history', $this->revisions($report)],
            'audit.html' => ['Complete period audit history', $this->audit($report)],
        ];
        foreach ($pages as $file => [$title, $body]) {
            $this->files->put($html.'/'.$file, $this->page($title, $body));
        }
        $this->files->put($html.'/print.html', $this->page('Anaesthesia Department Draft Roster', $this->print($report), 'print'));
        $assets = collect($this->files->allFiles($html))->map(fn (\SplFileInfo $file): string => str_replace('\\', '/', $file->getRelativePathname())."\0".hash_file('sha256', $file->getPathname()))->sort()->values()->all();

        return ['entrypoint' => 'html/index.html', 'pages' => count($pages), 'aggregate_fingerprint' => hash('sha256', implode("\0", $assets))];
    }

    /** @param array<string, mixed> $report */
    private function summary(array $report): string
    {
        $staffing = array_count_values(array_map(fn (array $day): string => $day['staffing_status'], $report['calendar']));

        return $this->heading().'<div class="stats"><div><b>Doctors</b><span>'.count($report['doctor_hours']).'</span></div><div><b>Dates</b><span>'.count($report['calendar']).'</span></div><div><b>Assignments</b><span>'.count($report['assignments']).'</span></div><div><b>Revision</b><span>'.$report['roster']['revision'].'</span></div></div><p class="notice">This artifact shows deliberately assigned doctors. Eligibility and availability remain contextual evidence and are not assignments.</p><p><b>Status:</b> '.$this->e($report['roster']['status']).' · <b>Validation:</b> '.$this->e($report['validation']['status']).' · Fully staffed '.($staffing['fully_staffed'] ?? 0).' · Overstaffed '.($staffing['overstaffed'] ?? 0).' · Understaffed '.($staffing['understaffed'] ?? 0).'</p><p><b>Audit isolation:</b> '.$report['isolation']['period_scoped_audit_count'].' records for '.$this->e($report['isolation']['selected_roster_period']).' · unrelated identifiers present '.$report['isolation']['unrelated_identifiers_present'].'</p><nav><a href="roster-calendar.html">Roster calendar</a><a href="doctor-matrix.html">Doctor matrix</a><a href="doctor-hours.html">Doctor hours</a><a href="staffing.html">Staffing</a><a href="validation.html">Validation</a><a href="revisions.html">Complete revisions</a><a href="audit.html">Complete audit</a><a href="../pdf/anaesthesia-manual-roster.pdf">PDF</a></nav>';
    }

    private function heading(): string
    {
        return '<header><p class="eyebrow">GNE anaesthesia rostering</p><h1>Anaesthesia Department Draft Roster</h1><p class="subtitle">Manually Authored Draft — Not Yet Published</p></header>';
    }

    /** @param array<string, mixed> $report */
    private function calendar(array $report): string
    {
        $weeks = implode('', array_map(function (array $week): string {
            $days = implode('', array_map(function (array $day): string {
                $names = implode('', array_map(fn (array $doctor): string => '<li>'.$this->e($doctor['doctor_name']).'</li>', $day['assigned_doctors']));

                return '<article class="day '.$this->e($day['staffing_status']).'"><h3>'.$this->e($day['weekday']).'<small>'.$this->e($day['date']).'</small></h3><p>Required '.$day['required_count'].' · Assigned '.$day['assigned_count'].' · Variance '.$day['variance'].'</p><strong>'.$this->e(str_replace('_', ' ', $day['staffing_status'])).'</strong><ul>'.$names.'</ul></article>';
            }, $week['dates']));

            return '<section class="week"><h2>Week '.$week['week'].'</h2><div class="week-grid">'.$days.'</div></section>';
        }, $report['weeks']));

        return $this->back().'<h1>Four-week Assigned Roster Calendar</h1>'.$this->legend().$weeks;
    }

    /** @param array<string, mixed> $report */
    private function matrix(array $report): string
    {
        $head = implode('', array_map(fn (array $day): string => '<th>'.$this->e(substr($day['date'], 5)).'</th>', $report['calendar']));
        $rows = implode('', array_map(function (array $doctor): string {
            $cells = implode('', array_map(fn (array $cell): string => '<td>'.$this->e($cell['state']).'</td>', $doctor['dates']));

            return '<tr><th>'.$this->e($doctor['doctor_name']).'<small>'.$this->e($doctor['doctor_identifier']).'</small></th>'.$cells.'</tr>';
        }, $report['doctor_matrix']));

        return $this->back().'<h1>Doctor-by-date Assignment Matrix</h1>'.$this->legend().'<div class="table-wrap"><table class="matrix"><thead><tr><th>Doctor</th>'.$head.'</tr></thead><tbody>'.$rows.'</tbody></table></div>';
    }

    /** @param array<string, mixed> $report */
    private function hours(array $report): string
    {
        $rows = implode('', array_map(fn (array $doctor): string => '<tr><td>'.$this->e($doctor['doctor_name']).'</td><td>'.$doctor['required_hours'].'</td><td>'.$doctor['assigned_hours'].'</td><td>'.$doctor['variance'].'</td><td>'.$this->e(implode(', ', $doctor['assigned_dates'])).'</td><td>'.$this->e(str_replace('_', ' ', $doctor['status'])).'</td></tr>', $report['doctor_hours']));

        return $this->back().'<h1>Doctor Assigned-hours Report</h1><table><thead><tr><th>Doctor</th><th>Required</th><th>Assigned</th><th>Variance</th><th>Assigned dates</th><th>Status</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    /** @param array<string, mixed> $report */
    private function staffing(array $report): string
    {
        $rows = implode('', array_map(fn (array $day): string => '<tr><td>'.$day['date'].'</td><td>'.$day['weekday'].'</td><td>'.$day['required_count'].'</td><td>'.$day['assigned_count'].'</td><td>'.$day['variance'].'</td><td>'.$this->e($day['staffing_status']).'</td><td>'.count($day['findings']).'</td></tr>', $report['calendar']));

        return $this->back().'<h1>Daily Staffing Report</h1><table><thead><tr><th>Date</th><th>Day</th><th>Required</th><th>Assigned</th><th>Variance</th><th>Status</th><th>Findings</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    /** @param array<string, mixed> $report */
    private function validation(array $report): string
    {
        $items = implode('', array_map(fn (array $finding): string => '<article class="finding '.$finding['severity'].'"><h2>'.$this->e(strtoupper($finding['severity']).' · '.$finding['code']).'</h2><p>'.$this->e($finding['message']).'</p><pre>'.$this->e(json_encode($finding['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)).'</pre></article>', $report['validation']['findings']));

        return $this->back().'<h1>Assignment Warnings and Errors</h1><p>'.$report['validation']['errors'].' errors · '.$report['validation']['warnings'].' warnings · '.$this->e($report['validation']['status']).'</p>'.$items;
    }

    /** @param array<string, mixed> $report */
    private function operatorValidation(array $report): string
    {
        $counts = collect($report['validation']['findings'])->countBy('code')->sortKeys();
        $rows = $counts->map(fn (int $count, string $code): string => '<tr><td>'.$this->e($code).'</td><td>'.$count.'</td></tr>')->implode('');
        $notable = collect($report['validation']['findings'])->whereIn('code', ['ROSTER_DAY_OVERSTAFFED', 'ASSIGNMENT_PREFERRED_OFF'])->map(fn (array $finding): string => '<article class="finding '.$finding['severity'].'"><h2>'.$this->e(strtoupper($finding['severity']).' · '.$finding['code']).'</h2><p>'.$this->e($finding['message']).'</p></article>')->implode('');

        return '<h1>Roster Validation Summary</h1><p>'.$report['validation']['errors'].' errors · '.$report['validation']['warnings'].' warnings · '.$this->e($report['validation']['status']).'</p><table><thead><tr><th>Finding</th><th>Count</th></tr></thead><tbody>'.$rows.'</tbody></table><h2>Notable final warnings</h2>'.$notable.'<p>Complete structured findings remain available in report.json and html/validation.html.</p>';
    }

    /** @param array<string, mixed> $report */
    private function revisions(array $report): string
    {
        $revisions = implode('', array_map(fn (array $revision): string => '<tr><td>'.$revision['revision_number'].'</td><td>'.$this->e((string) $revision['created_at']).'</td><td>'.$this->e((string) $revision['actor']).'</td><td>'.$this->e($revision['reason']).'</td><td>'.$this->e(json_encode($revision['summary'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)).'</td><td>'.$this->e($revision['validation_status']).'</td></tr>', $report['revisions']));

        return $this->back().'<h1>Complete Revision History</h1><p>Validation describes the state of the entire roster immediately after each revision; it does not mean the committed mutation command was invalid.</p><table><thead><tr><th>Revision</th><th>Timestamp</th><th>Actor</th><th>Reason</th><th>Change summary</th><th>Roster state after revision</th></tr></thead><tbody>'.$revisions.'</tbody></table>';
    }

    /** @param array<string, mixed> $report */
    private function audit(array $report): string
    {
        $groups = collect($report['complete_audit'])->groupBy(fn (array $entry): string => match (true) {
            str_starts_with($entry['action'], 'roster_period.'), str_starts_with($entry['action'], 'roster_day.'), str_starts_with($entry['action'], 'doctor_requirement.') => 'Period setup',
            str_starts_with($entry['action'], 'doctor_request.') => 'Requests',
            $entry['action'] === 'roster_assignment.created' => 'Assignment creation',
            in_array($entry['action'], ['roster_assignment.moved', 'roster_assignment.replaced', 'roster_assignment.removed'], true) => 'Manual corrections',
            default => 'Revision evidence',
        })->map(function ($entries, string $group): string {
            $rows = $entries->map(fn (array $entry): string => '<tr><td>'.$this->e($entry['action']).'</td><td>'.$this->e($entry['entity_type']).'</td><td>'.$this->e($entry['entity_identifier']).'</td><td>'.$this->e((string) $entry['created_at']).'</td><td>'.$this->e((string) $entry['reason']).'</td></tr>')->implode('');

            return '<section><h2>'.$this->e($group).'</h2><table><thead><tr><th>Action</th><th>Entity type</th><th>Entity</th><th>Recorded</th><th>Reason</th></tr></thead><tbody>'.$rows.'</tbody></table></section>';
        })->implode('');

        return $this->back().'<h1>Complete Exact-period Audit History</h1><p>'.$report['audit_summary']['total'].' records scoped to '.$this->e($report['roster']['identifier']).'. Revision evidence and operational audit evidence are related but not interchangeable.</p>'.$groups;
    }

    /** @param array<string, mixed> $report */
    private function operatorHistory(array $report): string
    {
        $mutationCounts = implode('', collect($report['revision_summary']['mutation_counts'])->map(fn (int $count, string $operation): string => '<tr><td>'.$this->e(str_replace('_', ' ', $operation)).'</td><td>'.$count.'</td></tr>')->all());
        $recentRevisions = implode('', array_map(fn (array $revision): string => '<tr><td>'.$revision['revision_number'].'</td><td>'.$this->e($revision['reason']).'</td><td>'.$this->e($revision['validation_status']).'</td></tr>', $report['revision_summary']['recent']));
        $auditCounts = implode('', collect($report['audit_summary']['by_action'])->map(fn (int $count, string $action): string => '<tr><td>'.$this->e($action).'</td><td>'.$count.'</td></tr>')->all());
        $recentAudit = implode('', array_map(fn (array $entry): string => '<li>'.$this->e($entry['action'].' · '.$entry['entity_identifier'].' · '.($entry['reason'] ?? '')).'</li>', array_slice($report['recent_audit'], 0, 6)));

        return '<h1>Revision and Audit Summary</h1><div class="history-grid"><section><h2>Revision summary</h2><p><b>Current:</b> '.$report['revision_summary']['current_revision'].' of '.$report['revision_summary']['total_revisions'].' · <b>Roster state after revision:</b> '.$this->e($report['revision_summary']['current_validation_status']).'</p><p>'.$this->e((string) $report['revision_summary']['first_revision_at']).' → '.$this->e((string) $report['revision_summary']['latest_revision_at']).'</p><table><thead><tr><th>Mutation</th><th>Count</th></tr></thead><tbody>'.$mutationCounts.'</tbody></table><h3>Latest 10 revisions</h3><table><thead><tr><th>Revision</th><th>Reason</th><th>Roster state</th></tr></thead><tbody>'.$recentRevisions.'</tbody></table></section><section><h2>Period audit summary</h2><p>'.$report['audit_summary']['total'].' complete exact-period records. Unrelated identifiers present: '.$report['isolation']['unrelated_identifiers_present'].'.</p><table><thead><tr><th>Audit action</th><th>Count</th></tr></thead><tbody>'.$auditCounts.'</tbody></table><h3>Recent and notable evidence</h3><ul>'.$recentAudit.'</ul></section></div>';
    }

    /** @param array<string, mixed> $report */
    private function print(array $report): string
    {
        return '<section class="cover">'.$this->heading().'<p>'.$this->e($report['roster']['title']).'</p><p>Revision '.$report['roster']['revision'].' · '.$this->e($report['validation']['status']).'</p></section><section class="page">'.$this->summary($report).'</section><section class="page">'.$this->calendar($report).'</section><section class="page">'.$this->matrix($report).'</section><section class="page">'.$this->staffing($report).'</section><section class="page">'.$this->hours($report).'</section><section class="page">'.$this->operatorValidation($report).'</section><section class="page">'.$this->operatorHistory($report).'</section><section class="page conclusion"><h1>Readiness and review conclusion</h1><p>This assigned roster is mandatory-valid and warning-bearing. It was manually authored, remains unpublished, and is ready for administrator review.</p><p>Complete period-scoped revision evidence is retained in report.json, html/revisions.html, and html/audit.html.</p><strong>Manually Authored Draft — Not Yet Published</strong></section>';
    }

    private function legend(): string
    {
        return '<p class="legend"><b>Legend:</b> ✓ assigned · L leave · U unavailable · PW preferred work · PO preferred off · – not assigned · ! finding. Green/fully staffed, amber/overstaffed warning, red/understaffed error; text labels remain authoritative.</p>';
    }

    private function back(): string
    {
        return '<p><a href="index.html">Back to report</a></p>';
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function page(string $title, string $body, string $class = ''): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$this->e($title).'</title><link rel="stylesheet" href="assets/report.css"></head><body class="'.$class.'"><main>'.$body.'</main></body></html>';
    }

    private function css(): string
    {
        return ':root{font-family:Inter,Arial,sans-serif;color:#15232c;background:#edf2f3}*{box-sizing:border-box}body{margin:0}main{max-width:1500px;margin:auto;padding:32px;background:#fff;min-height:100vh}h1{font-size:30px}.eyebrow{text-transform:uppercase;letter-spacing:.15em;color:#126b65;font-weight:700}.subtitle{font-size:20px;font-weight:700;color:#8a4c00}.notice,.legend{padding:12px 16px;border-left:5px solid #c88400;background:#fff7df}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.stats div{border:1px solid #ccd8dc;padding:16px;border-radius:10px}.stats b,.stats span{display:block}.stats span{font-size:28px;color:#126b65}nav{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}a{color:#126b65}.week{margin:22px 0}.week-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:8px}.day{border:2px solid #83969f;border-radius:8px;padding:9px;break-inside:avoid}.day h3{margin:0}.day h3 small,.matrix th small{display:block;font-size:9px}.day p,.day li{font-size:10px}.day ul{padding-left:15px}.fully_staffed{border-color:#25845c;background:#effaf5}.overstaffed{border-color:#c88400;background:#fff7df}.understaffed{border-color:#b42318;background:#fff1f0}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:10px}th,td{border:1px solid #ccd8dc;padding:5px;text-align:left}.matrix{white-space:nowrap;font-size:8px}.matrix td{text-align:center;font-weight:700}.finding{padding:12px;border:1px solid #ccd8dc;border-left-width:5px;margin:8px 0}.finding.error{border-left-color:#b42318}.finding.warning{border-left-color:#c88400}.history-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.cover,.page{break-after:page;min-height:180mm;padding:8mm}.cover,.conclusion{display:flex;flex-direction:column;justify-content:center}.print main{max-width:none;padding:0}@page{size:A4 landscape;margin:7mm}@media print{body{background:#fff}.week-grid{grid-template-columns:repeat(7,1fr)}.day{padding:5px}.day li{font-size:7px}.page table{font-size:7px}a{display:none}}';
    }
}
