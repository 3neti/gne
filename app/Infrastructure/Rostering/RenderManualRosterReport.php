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
            'revisions.html' => ['Revision and audit history', $this->revisions($report)],
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

        return $this->heading().'<div class="stats"><div><b>Doctors</b><span>'.count($report['doctor_hours']).'</span></div><div><b>Dates</b><span>'.count($report['calendar']).'</span></div><div><b>Assignments</b><span>'.count($report['assignments']).'</span></div><div><b>Revision</b><span>'.$report['roster']['revision'].'</span></div></div><p class="notice">This artifact shows deliberately assigned doctors. Eligibility and availability remain contextual evidence and are not assignments.</p><p><b>Status:</b> '.$this->e($report['roster']['status']).' · <b>Validation:</b> '.$this->e($report['validation']['status']).' · Fully staffed '.($staffing['fully_staffed'] ?? 0).' · Overstaffed '.($staffing['overstaffed'] ?? 0).' · Understaffed '.($staffing['understaffed'] ?? 0).'</p><nav><a href="roster-calendar.html">Roster calendar</a><a href="doctor-matrix.html">Doctor matrix</a><a href="doctor-hours.html">Doctor hours</a><a href="staffing.html">Staffing</a><a href="validation.html">Validation</a><a href="revisions.html">Revisions</a><a href="../pdf/anaesthesia-manual-roster.pdf">PDF</a></nav>';
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
    private function revisions(array $report): string
    {
        $revisions = implode('', array_map(fn (array $revision): string => '<tr><td>'.$revision['revision_number'].'</td><td>'.$this->e((string) $revision['created_at']).'</td><td>'.$this->e((string) $revision['actor']).'</td><td>'.$this->e($revision['reason']).'</td><td>'.$this->e(json_encode($revision['summary'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)).'</td><td>'.$this->e($revision['validation_status']).'</td></tr>', $report['revisions']));
        $audit = implode('', array_map(fn (array $entry): string => '<li>'.$this->e($entry['action'].' · '.$entry['entity_identifier']).'</li>', $report['audit']));

        return $this->back().'<h1>Revision History</h1><table><thead><tr><th>Revision</th><th>Timestamp</th><th>Actor</th><th>Reason</th><th>Change summary</th><th>Validation</th></tr></thead><tbody>'.$revisions.'</tbody></table><h2>Audit summary</h2><ul class="audit">'.$audit.'</ul>';
    }

    /** @param array<string, mixed> $report */
    private function print(array $report): string
    {
        return '<section class="cover">'.$this->heading().'<p>'.$this->e($report['roster']['title']).'</p><p>Revision '.$report['roster']['revision'].' · '.$this->e($report['validation']['status']).'</p></section><section class="page">'.$this->summary($report).'</section><section class="page">'.$this->calendar($report).'</section><section class="page">'.$this->matrix($report).'</section><section class="page">'.$this->staffing($report).'</section><section class="page">'.$this->hours($report).'</section><section class="page">'.$this->validation($report).'</section><section class="page">'.$this->revisions($report).'</section><section class="page conclusion"><h1>Readiness and review conclusion</h1><p>This assigned roster is mandatory-valid and warning-bearing. It was manually authored, remains unpublished, and is ready for administrator review.</p><strong>Manually Authored Draft — Not Yet Published</strong></section>';
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
        return ':root{font-family:Inter,Arial,sans-serif;color:#15232c;background:#edf2f3}*{box-sizing:border-box}body{margin:0}main{max-width:1500px;margin:auto;padding:32px;background:#fff;min-height:100vh}h1{font-size:30px}.eyebrow{text-transform:uppercase;letter-spacing:.15em;color:#126b65;font-weight:700}.subtitle{font-size:20px;font-weight:700;color:#8a4c00}.notice,.legend{padding:12px 16px;border-left:5px solid #c88400;background:#fff7df}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.stats div{border:1px solid #ccd8dc;padding:16px;border-radius:10px}.stats b,.stats span{display:block}.stats span{font-size:28px;color:#126b65}nav{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}a{color:#126b65}.week{margin:22px 0}.week-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:8px}.day{border:2px solid #83969f;border-radius:8px;padding:9px;break-inside:avoid}.day h3{margin:0}.day h3 small,.matrix th small{display:block;font-size:9px}.day p,.day li{font-size:10px}.day ul{padding-left:15px}.fully_staffed{border-color:#25845c;background:#effaf5}.overstaffed{border-color:#c88400;background:#fff7df}.understaffed{border-color:#b42318;background:#fff1f0}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:10px}th,td{border:1px solid #ccd8dc;padding:5px;text-align:left}.matrix{white-space:nowrap;font-size:8px}.matrix td{text-align:center;font-weight:700}.finding{padding:12px;border:1px solid #ccd8dc;border-left-width:5px;margin:8px 0}.finding.error{border-left-color:#b42318}.finding.warning{border-left-color:#c88400}.audit{columns:3;font-size:9px}.cover,.page{break-after:page;min-height:180mm;padding:8mm}.cover,.conclusion{display:flex;flex-direction:column;justify-content:center}.print main{max-width:none;padding:0}@page{size:A4 landscape;margin:7mm}@media print{body{background:#fff}.week-grid{grid-template-columns:repeat(7,1fr)}.day{padding:5px}.day li{font-size:7px}.page table{font-size:7px}a{display:none}}';
    }
}
