<?php

namespace App\Infrastructure\Rostering;

use Illuminate\Filesystem\Filesystem;

final readonly class RenderRequestsAvailabilityReport
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
        $this->files->put($html.'/index.html', $this->page('Requests and availability report', $this->summary($report)));
        $this->files->put($html.'/availability-calendar.html', $this->page('Anaesthesia Availability and Staffing Calendar', $this->calendar($report)));
        $this->files->put($html.'/doctor-availability.html', $this->page('Doctor Availability', $this->doctors($report)));
        $this->files->put($html.'/conflicts.html', $this->page('Conflicts and Validation', $this->conflicts($report)));
        $this->files->put($html.'/print.html', $this->page('Anaesthesia Requests and Availability', $this->print($report), 'print'));
        $assets = collect($this->files->allFiles($html))->map(fn (\SplFileInfo $file): string => str_replace('\\', '/', $file->getRelativePathname())."\0".hash_file('sha256', $file->getPathname()))->sort()->values()->all();

        return ['entrypoint' => 'html/index.html', 'pages' => 4, 'aggregate_fingerprint' => hash('sha256', implode("\0", $assets))];
    }

    /** @param array<string, mixed> $report */
    private function summary(array $report): string
    {
        return '<header><p class="eyebrow">GNE anaesthesia rostering</p><h1>Requests and Availability</h1><p class="lede">Generation-ready inputs, before any roster assignment exists.</p></header>'.$this->notice().'<div class="stats"><div><b>Doctors</b><span>'.count($report['doctors']).'</span></div><div><b>Dates</b><span>'.count($report['calendar']).'</span></div><div><b>Requests</b><span>'.count($report['requests']).'</span></div><div><b>Warnings</b><span>'.$report['validation']['warnings'].'</span></div></div><h2>Lifecycle conclusion</h2><p>'.$this->e($report['lifecycle']['explanation']).'</p><nav><a href="availability-calendar.html">Availability calendar</a><a href="doctor-availability.html">Doctor availability</a><a href="conflicts.html">Conflicts and validation</a><a href="../pdf/anaesthesia-requests-and-availability.pdf">PDF rendition</a></nav>';
    }

    /** @param array<string, mixed> $report */
    private function calendar(array $report): string
    {
        $rows = implode('', array_map(fn (array $day): string => '<tr><td>'.$day['date'].'</td><td>'.$day['weekday'].'</td><td>'.$day['day_type'].'</td><td>'.$day['required_doctors'].'</td><td>'.$day['available_doctor_count'].'</td><td>'.$day['unavailable_doctor_count'].'</td><td>'.$day['leave_doctor_count'].'</td><td>'.$day['preferred_work_count'].' / '.$day['preferred_off_count'].'</td><td>'.$day['conflict_count'].'</td></tr>', $report['calendar']));

        return '<p><a href="index.html">Back to report</a></p><h1>Anaesthesia Availability and Staffing Calendar</h1>'.$this->notice().'<table><thead><tr><th>Date</th><th>Weekday</th><th>Type</th><th>Required</th><th>Available</th><th>Unavailable</th><th>Leave</th><th>Work / off prefs</th><th>Conflicts</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    /** @param array<string, mixed> $report */
    private function doctors(array $report): string
    {
        $cards = implode('', array_map(fn (array $doctor): string => '<article class="doctor"><h2>'.$this->e($doctor['name']).'</h2><p>'.$doctor['identifier'].' · required '.$this->e((string) ($doctor['required_hours'] ?? 'not set')).' hours · standard '.$this->e((string) $doctor['standard_daily_hours']).' hours/day</p><dl><dt>Available</dt><dd>'.$this->dates($doctor['available_dates']).'</dd><dt>Unavailable</dt><dd>'.$this->dates($doctor['unavailable_dates']).'</dd><dt>Leave</dt><dd>'.$this->dates($doctor['leave_dates']).'</dd><dt>Preferred work</dt><dd>'.$this->dates($doctor['preferred_work_dates']).'</dd><dt>Preferred off</dt><dd>'.$this->dates($doctor['preferred_off_dates']).'</dd><dt>Conflicts</dt><dd>'.($this->e(implode(', ', $doctor['conflict_codes'])) ?: 'None').'</dd></dl></article>', $report['doctors']));

        return '<p><a href="index.html">Back to report</a></p><h1>Doctor Availability</h1>'.$this->notice().'<div class="doctors">'.$cards.'</div>';
    }

    /** @param array<string, mixed> $report */
    private function conflicts(array $report): string
    {
        $items = implode('', array_map(fn (array $conflict): string => '<article class="finding '.$conflict['severity'].'"><h2>'.$conflict['severity'].' · '.$conflict['code'].'</h2><p>'.$conflict['doctor_identifier'].' · '.$conflict['date'].' · '.$this->e($conflict['message']).'</p><p>'.$this->e((string) $conflict['suggested_correction']).'</p></article>', $report['conflicts']));

        return '<p><a href="index.html">Back to report</a></p><h1>Conflicts and Validation</h1><p>Errors: '.$report['validation']['errors'].' · Warnings: '.$report['validation']['warnings'].'</p>'.($items ?: '<p>No conflicts.</p>');
    }

    /** @param array<string, mixed> $report */
    private function print(array $report): string
    {
        $steps = implode('', array_map(fn (array $step): string => '<li><b>'.$step['id'].'</b> — '.$this->e($step['explanation']).'</li>', $report['steps']));
        $audit = implode('', array_map(fn (array $entry): string => '<li>'.$entry['action'].' · '.$entry['entity_identifier'].'</li>', array_slice($report['audit'], 0, 40)));

        return '<section class="cover"><p class="eyebrow">GNE Anaesthesia Rostering</p><h1>Requests, Availability, and Staffing</h1><p>'.$this->e($report['lifecycle']['title']).'</p><strong>No roster assignments have been generated.</strong></section><section class="page"><h1>Scenario and lifecycle summary</h1><p>'.$this->e($report['lifecycle']['explanation']).'</p><ol>'.$steps.'</ol></section><section class="page">'.$this->calendar($report).'</section><section class="page">'.$this->doctors($report).'</section><section class="page">'.$this->conflicts($report).'<h2>Audit summary</h2><ul>'.$audit.'</ul><h2>Readiness conclusion</h2><p>'.$this->e($report['lifecycle']['explanation']).'</p><strong>No roster assignments have been generated.</strong></section>';
    }

    private function notice(): string
    {
        return '<p class="notice">Availability and Staffing Calendar — No assignments generated</p>';
    }

    /** @param list<string> $dates */
    private function dates(array $dates): string
    {
        return $dates === [] ? 'None' : implode(', ', $dates);
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
        return ':root{font-family:Inter,Arial,sans-serif;color:#17212b;background:#f5f7f8}*{box-sizing:border-box}body{margin:0}main{max-width:1180px;margin:auto;padding:40px;background:white;min-height:100vh}h1{font-size:30px;margin:8px 0 18px}h2{font-size:18px}.eyebrow{text-transform:uppercase;letter-spacing:.14em;color:#176b66;font-weight:700;font-size:12px}.lede{font-size:20px;color:#4b5965}.notice{padding:12px 16px;background:#fff4d6;border-left:4px solid #d29316;font-weight:700}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:24px 0}.stats div,.doctor,.finding{border:1px solid #d9e0e4;border-radius:10px;padding:16px}.stats b,.stats span{display:block}.stats span{font-size:28px;color:#176b66}nav{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}a{color:#176b66}table{width:100%;border-collapse:collapse;font-size:12px}th,td{padding:7px;border:1px solid #ccd6da;text-align:left}th{background:#e7f1f0}.doctors{display:grid;grid-template-columns:1fr 1fr;gap:12px}.doctor dl{display:grid;grid-template-columns:120px 1fr;gap:5px;font-size:12px}.doctor dt{font-weight:700}.finding.error{border-left:5px solid #b42318}.finding.warning{border-left:5px solid #d29316}.cover,.page{break-after:page;min-height:180mm;padding:12mm}.cover{display:flex;flex-direction:column;justify-content:center}.print main{max-width:none;padding:0}@page{size:A4 landscape;margin:8mm}@media print{body{background:white}.page table{font-size:9px}.doctors{grid-template-columns:1fr 1fr}.doctor{break-inside:avoid}}';
    }
}
