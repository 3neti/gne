<?php

namespace App\Infrastructure\Rostering;

use Illuminate\Filesystem\Filesystem;

final readonly class RenderRequestsAvailabilityReport
{
    public function __construct(private Filesystem $files) {}

    /** @param array<string, mixed> $report
     * @return array{entrypoint: string, pages: int, aggregate_fingerprint: string}
     */
    public function handle(string $root, array $report): array
    {
        $html = $root.'/html';
        $this->files->deleteDirectory($html);
        $this->files->ensureDirectoryExists($html.'/assets');
        $this->files->put($html.'/assets/report.css', $this->css());
        $this->files->put($html.'/index.html', $this->page('Requests and availability report', $this->summary($report)));
        $this->files->put($html.'/visual-calendar.html', $this->page('Visual availability calendar', $this->visualCalendar($report)));
        $this->files->put($html.'/doctor-matrix.html', $this->page('Doctor availability matrix', $this->matrix($report)));
        $this->files->put($html.'/doctor-availability.html', $this->page('Doctor availability', $this->doctors($report)));
        $this->files->put($html.'/conflicts.html', $this->page('Conflicts and validation', $this->conflicts($report)));
        $this->files->put($html.'/print.html', $this->page('Anaesthesia Requests and Availability', $this->print($report), 'print'));
        $assets = collect($this->files->allFiles($html))->map(fn (\SplFileInfo $file): string => str_replace('\\', '/', $file->getRelativePathname())."\0".hash_file('sha256', $file->getPathname()))->sort()->values()->all();

        return ['entrypoint' => 'html/index.html', 'pages' => 5, 'aggregate_fingerprint' => hash('sha256', implode("\0", $assets))];
    }

    /** @param array<string, mixed> $report */
    private function summary(array $report): string
    {
        $summary = $report['availability_summary'];

        return '<header><p class="eyebrow">GNE anaesthesia rostering</p><h1>Requests and Availability</h1><p class="lede">Eligibility evidence before any roster assignment exists.</p></header>'.$this->notice().$this->legend().'<div class="stats"><div><b>Doctors</b><span>'.count($report['doctors']).'</span></div><div><b>Explicit cells</b><span>'.$summary['explicit_available_total'].'</span></div><div><b>Unspecified cells</b><span>'.$summary['unspecified_total'].'</span></div><div><b>Warnings</b><span>'.$report['validation']['warnings'].'</span></div></div><h2>Lifecycle conclusion</h2><p>'.$this->e($report['lifecycle']['explanation']).'</p><nav><a href="visual-calendar.html">Four-week visual calendar</a><a href="doctor-matrix.html">Doctor matrix</a><a href="doctor-availability.html">Doctor details</a><a href="conflicts.html">Conflicts and validation</a><a href="../pdf/anaesthesia-requests-and-availability.pdf">PDF rendition</a></nav>';
    }

    /** @param array<string, mixed> $report */
    private function visualCalendar(array $report): string
    {
        $weeks = implode('', array_map(function (array $week): string {
            $cards = implode('', array_map(fn (array $day): string => '<article class="day '.$this->statusClass($day['staffing_input_status']).'"><header><b>'.$this->e($day['weekday']).'</b><span>'.$this->e($day['date']).'</span></header><strong>'.$this->statusLabel($day['staffing_input_status']).'</strong><dl><dt>Required</dt><dd>'.$day['required_doctor_count'].'</dd><dt>Eligible</dt><dd>'.$day['eligible_doctor_count'].'</dd><dt>Explicit</dt><dd>'.$day['explicit_available_count'].'</dd><dt>Unspecified</dt><dd>'.$day['unspecified_doctor_count'].'</dd><dt>Unavailable</dt><dd>'.$day['unavailable_doctor_count'].'</dd><dt>Leave</dt><dd>'.$day['leave_doctor_count'].'</dd><dt>Preferences</dt><dd>'.$day['preferred_work_count'].' work / '.$day['preferred_off_count'].' off</dd><dt>Conflicts</dt><dd>'.$day['conflict_count'].'</dd></dl></article>', $week['dates']));

            return '<section class="week"><h2>Week '.$week['week'].'</h2><div class="week-grid">'.$cards.'</div></section>';
        }, $report['calendar']['weeks']));
        $rows = implode('', array_map(fn (array $day): string => '<tr><td>'.$day['date'].'</td><td>'.$day['weekday'].'</td><td>'.$day['required_doctor_count'].'</td><td>'.$day['eligible_doctor_count'].'</td><td>'.$day['explicit_available_count'].'</td><td>'.$day['unspecified_doctor_count'].'</td><td>'.$day['unavailable_doctor_count'].'</td><td>'.$day['leave_doctor_count'].'</td><td>'.$day['preferred_work_count'].'</td><td>'.$day['preferred_off_count'].'</td><td>'.$day['conflict_count'].'</td></tr>', $report['calendar']['dates']));

        return $this->back().'<h1>Four-week Availability and Staffing Calendar</h1>'.$this->notice().$this->legend().$weeks.'<section class="page-break"><h2>Date-by-date staffing input summary</h2><table><thead><tr><th>Date</th><th>Day</th><th>Req.</th><th>Eligible</th><th>Explicit</th><th>Unspec.</th><th>Unavail.</th><th>Leave</th><th>Pref. Work</th><th>Pref. Off</th><th>Conflicts</th></tr></thead><tbody>'.$rows.'</tbody></table></section>';
    }

    /** @param array<string, mixed> $report */
    private function matrix(array $report): string
    {
        $head = implode('', array_map(fn (array $day): string => '<th><span>'.$this->e(substr($day['weekday'], 0, 3)).'</span>'.$this->e(substr($day['date'], 5)).'</th>', $report['calendar']['dates']));
        $rows = implode('', array_map(function (array $doctor): string {
            $cells = implode('', array_map(fn (array $cell): string => '<td class="matrix-state">'.$this->e($cell['state']).'</td>', $doctor['dates']));

            return '<tr><th class="doctor-name">'.$this->e($doctor['doctor_name']).'<small>'.$this->e($doctor['doctor_identifier']).'</small></th>'.$cells.'</tr>';
        }, $report['doctor_availability_matrix']));

        return $this->back().'<h1>Doctor-by-date Availability Matrix</h1>'.$this->notice().'<p class="matrix-legend"><b>Legend:</b> A explicit availability · U unavailable · L leave · PW preferred work · PO preferred off · – unspecified · ! conflict. Combined marks retain blocking status and preference.</p><div class="matrix-wrap"><table class="matrix"><thead><tr><th>Doctor</th>'.$head.'</tr></thead><tbody>'.$rows.'</tbody></table></div>';
    }

    /** @param array<string, mixed> $report */
    private function doctors(array $report): string
    {
        $cards = implode('', array_map(fn (array $doctor): string => '<article class="doctor"><h2>'.$this->e($doctor['name']).'</h2><p>'.$doctor['identifier'].' · required '.$this->e((string) ($doctor['required_hours'] ?? 'not set')).' hours · standard '.$this->e((string) $doctor['standard_daily_hours']).' hours/day</p><dl><dt>Explicitly available</dt><dd>'.$this->dates($doctor['explicit_available_dates']).'</dd><dt>Unspecified dates</dt><dd>'.$doctor['unspecified_date_count'].'</dd><dt>Unavailable</dt><dd>'.$this->dates($doctor['unavailable_dates']).'</dd><dt>Leave</dt><dd>'.$this->dates($doctor['leave_dates']).'</dd><dt>Preferred work</dt><dd>'.$this->dates($doctor['preferred_work_dates']).'</dd><dt>Preferred off</dt><dd>'.$this->dates($doctor['preferred_off_dates']).'</dd><dt>Conflicts</dt><dd>'.($this->e(implode(', ', $doctor['conflict_codes'])) ?: 'None').'</dd></dl></article>', $report['doctors']));

        return $this->back().'<h1>Doctor Availability Detail</h1>'.$this->notice().'<div class="doctors">'.$cards.'</div>';
    }

    /** @param array<string, mixed> $report */
    private function conflicts(array $report): string
    {
        $items = implode('', array_map(fn (array $conflict): string => '<article class="finding '.$conflict['severity'].'"><h2>'.$conflict['severity'].' · '.$conflict['code'].'</h2><p><b>'.$conflict['doctor_identifier'].'</b> · '.$conflict['date'].' · '.implode(' + ', $conflict['request_types']).'</p><p>Effective state: '.$this->e((string) ($conflict['effective_state'] ?? 'not applicable')).'</p><p>'.$this->e($conflict['message']).' '.$this->e((string) $conflict['suggested_correction']).'</p><small>Requests: '.$this->e(implode(', ', $conflict['request_identifiers'])).'</small></article>', $report['conflicts']));

        return $this->back().'<h1>Conflicts and Validation</h1><p>Errors: '.$report['validation']['errors'].' · Warnings: '.$report['validation']['warnings'].'</p>'.($items ?: '<p>No conflicts.</p>');
    }

    /** @param array<string, mixed> $report */
    private function print(array $report): string
    {
        $steps = implode('', array_map(fn (array $step): string => '<li><b>'.$step['id'].'</b> - '.$this->e($step['explanation']).'</li>', $report['steps']));
        $audit = implode('', array_map(fn (array $entry): string => '<li>'.$entry['action'].' · '.$entry['entity_identifier'].'</li>', array_slice($report['audit'], 0, 40)));

        return '<section class="cover"><p class="eyebrow">GNE Anaesthesia Rostering</p><h1>Requests, Availability, and Staffing</h1><p>'.$this->e($report['lifecycle']['title']).'</p><strong>No roster assignments have been generated.</strong></section><section class="page"><h1>Scenario and lifecycle summary</h1><p>'.$this->e($report['lifecycle']['explanation']).'</p><ol>'.$steps.'</ol></section><section class="page"><h1>Availability terminology and legend</h1>'.$this->legend().'<p class="disclaimer">Staffing input sufficiency does not account for required hours, fairness, repeated duties, preferences, fatigue, or assignment distribution.</p>'.$this->notice().'</section><section class="page">'.$this->visualCalendar($report).'</section><section class="page">'.$this->matrix($report).'</section><section class="page">'.$this->doctors($report).'</section><section class="page">'.$this->conflicts($report).'<h2>Audit summary</h2><ul class="audit-summary">'.$audit.'</ul></section><section class="page readiness"><h1>Readiness conclusion</h1><p>'.$this->e($report['lifecycle']['explanation']).'</p><strong>No roster assignments have been generated.</strong></section>';
    }

    private function legend(): string
    {
        return '<aside class="legend"><h2>Availability terminology</h2><p><b>Explicitly available</b> means an accepted available request exists. <b>Unspecified</b> means no accepted availability, leave, or unavailability exists. <b>Eligible</b> means active and not blocked by accepted leave or unavailability; under the provisional MVP policy it includes unspecified doctors.</p><p><b>Important:</b> eligibility is an input assumption, not an assignment.</p></aside>';
    }

    private function notice(): string
    {
        return '<p class="notice">Availability and Staffing Calendar — No assignments generated</p>';
    }

    private function back(): string
    {
        return '<p class="back"><a href="index.html">Back to report</a></p>';
    }

    /** @param list<string> $dates */
    private function dates(array $dates): string
    {
        return $dates === [] ? 'None' : implode(', ', $dates);
    }

    private function statusClass(string $status): string
    {
        return match ($status) {
            'request_conflict' => 'status-conflict',
            'insufficient_eligible_pool' => 'status-insufficient',
            default => 'status-sufficient',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'request_conflict' => 'Conflict — review required',
            'insufficient_eligible_pool' => 'Insufficient eligible pool',
            default => 'Eligible pool meets numeric demand',
        };
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
        return ':root{font-family:Inter,Arial,sans-serif;color:#17212b;background:#eef2f3}*{box-sizing:border-box}body{margin:0}main{max-width:1400px;margin:auto;padding:36px;background:white;min-height:100vh}h1{font-size:30px;margin:8px 0 18px}h2{font-size:18px}.eyebrow{text-transform:uppercase;letter-spacing:.14em;color:#176b66;font-weight:700;font-size:12px}.lede{font-size:20px;color:#4b5965}.notice,.disclaimer{padding:12px 16px;background:#fff4d6;border-left:4px solid #d29316;font-weight:700}.legend{padding:14px 18px;background:#eef6f5;border:1px solid #b9d7d3;border-radius:10px}.legend p{margin:6px 0}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:24px 0}.stats div,.doctor,.finding{border:1px solid #d9e0e4;border-radius:10px;padding:16px}.stats b,.stats span{display:block}.stats span{font-size:28px;color:#176b66}nav{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}a{color:#176b66}.week{margin:24px 0}.week-grid{display:grid;grid-template-columns:repeat(7,minmax(150px,1fr));gap:8px}.day{border:2px solid #b9c6cc;border-radius:10px;padding:10px;break-inside:avoid}.day header{display:flex;justify-content:space-between;gap:8px;border-bottom:1px solid #d9e0e4;padding-bottom:6px}.day header span{font-size:11px}.day>strong{display:block;font-size:11px;margin:8px 0}.day dl{display:grid;grid-template-columns:1fr auto;gap:3px;margin:0;font-size:11px}.day dd{margin:0;font-weight:700}.status-sufficient{border-color:#36936b;background:#f1faf6}.status-insufficient{border-color:#b42318;background:#fff1f0}.status-conflict{border-color:#d29316;background:#fff8e7}table{width:100%;border-collapse:collapse;font-size:11px}th,td{padding:6px;border:1px solid #ccd6da;text-align:left}th{background:#e7f1f0}.page-break{break-before:page;margin-top:28px}.matrix-wrap{overflow-x:auto}.matrix{font-size:8px;white-space:nowrap}.matrix th,.matrix td{text-align:center;padding:4px}.matrix th span,.doctor-name small{display:block;font-size:7px}.matrix .doctor-name{text-align:left;min-width:110px}.matrix-state{font-weight:700}.matrix-legend{padding:10px;background:#f3f5f6}.doctors{display:grid;grid-template-columns:1fr 1fr;gap:12px}.doctor dl{display:grid;grid-template-columns:145px 1fr;gap:5px;font-size:12px}.doctor dt{font-weight:700}.doctor dd{margin:0}.finding.error{border-left:5px solid #b42318}.finding.warning{border-left:5px solid #d29316}.audit-summary{columns:3;font-size:9px}.audit-summary li{break-inside:avoid}.cover,.page{break-after:page;min-height:180mm;padding:10mm}.cover,.readiness{display:flex;flex-direction:column;justify-content:center}.print main{max-width:none;padding:0}@page{size:A4 landscape;margin:7mm}@media print{body{background:white}.back{display:none}.week-grid{grid-template-columns:repeat(7,1fr)}.day{padding:6px}.day dl{font-size:8px}.day>strong{font-size:8px}.doctors{grid-template-columns:1fr 1fr}.doctor{break-inside:avoid}.page table{font-size:8px}}';
    }
}
