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
        $this->files->ensureDirectoryExists($html.'/assets');
        $css = 'body{font:14px system-ui;color:#17232b;margin:32px}h1,h2{color:#123b42}header{border-bottom:3px solid #168477;margin-bottom:24px}.notice{background:#fff4ce;border-left:4px solid #d59b00;padding:12px}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.stats div,article{border:1px solid #ccd8da;border-radius:8px;padding:10px}table{border-collapse:collapse;width:100%;font-size:11px}th,td{border:1px solid #ccd8da;padding:4px;text-align:left}.page{page-break-after:always}.mono{font-family:monospace;font-size:10px}@media print{nav{display:none}body{margin:18mm}}';
        $this->files->put($html.'/assets/report.css', $css);
        $summary = $report['generation']['summary'];
        $pages = [
            'index.html' => $this->page('Anaesthesia Department Generated Draft Roster', '<p class="notice"><b>Automatically Generated Draft — Not Yet Published.</b> Editable and not mathematically optimal.</p>'.$this->facts($report).'<nav>'.$this->links().'</nav>'),
            'generation-summary.html' => $this->page('Generation Summary', $this->stats($summary).$this->facts($report)),
            'roster-calendar.html' => $this->page('Four-week Generated Roster Calendar', $this->table(['Date', 'Required', 'Assigned', 'Status'], array_map(fn (array $day): array => [$day['date'], $day['required_count'], $day['assigned_count'], $day['staffing_status']], $report['calendar']))),
            'doctor-matrix.html' => $this->page('Doctor-by-date Assignment Matrix', $this->table(['Doctor', 'Date', 'Source'], array_map(fn (array $assignment): array => [$assignment['doctor_name'], $assignment['date'], $assignment['source']], $report['assignments']))),
            'doctor-hours.html' => $this->page('Doctor Hours', $this->table(['Doctor', 'Target', 'Assigned', 'Variance', 'Status'], array_map(fn (array $doctor): array => [$doctor['doctor_identifier'], $doctor['required_hours'], $doctor['assigned_hours'], $doctor['variance'], $doctor['status']], $report['generation']['doctor_hours']))),
            'staffing.html' => $this->page('Daily Staffing', $this->table(['Date', 'Required', 'Assigned', 'Missing', 'Status'], array_map(fn (array $day): array => [$day['date'], $day['required'], $day['assigned'], $day['missing_slots'], $day['status']], $report['generation']['daily_staffing']))),
            'preferences.html' => $this->page('Preference Fulfillment', $this->table(['Doctor', 'Date', 'Type', 'Honored', 'Explanation'], array_map(fn (array $item): array => [$item['doctor_identifier'], $item['date'], $item['type'], $item['honored'] ? 'yes' : 'no', $item['explanation']], $report['generation']['preferences']))),
            'explanations.html' => $this->page('Assignment Explanations', $this->table(['Doctor', 'Date', 'Reasons', 'Hours before', 'Assignments before', 'Availability', 'Preference'], array_map(fn (array $assignment): array => [$assignment['doctor_identifier'], $assignment['date'], implode(', ', $assignment['explanation']['reason_codes']), $assignment['explanation']['remaining_required_hours_before'], $assignment['explanation']['assignment_count_before'], $assignment['explanation']['availability'], $assignment['explanation']['preference']], $report['generation']['assignments']))),
            'validation.html' => $this->page('Validation Findings', $this->table(['Severity', 'Code', 'Message'], array_map(fn (array $finding): array => [$finding['severity'], $finding['code'], $finding['message']], $report['generation']['findings']))),
            'revisions.html' => $this->page('Generation Revision and Manual Correction', '<p>Generation revision: '.$this->e($report['batch']['generation_revision']).' · '.$report['batch']['revision_changes'].' assignment changes · '.$report['batch']['generation_audits'].' generation audit.</p><p>Manual correction revision: '.$report['manual_correction']['revision'].' · operation move · source '.$this->e($report['manual_correction']['source']).'.</p>'),
        ];
        foreach ($pages as $file => $content) {
            $this->files->put($html.'/'.$file, $content);
        }
        $operatorPages = array_diff_key($pages, array_flip(['doctor-matrix.html', 'explanations.html']));
        $sample = $this->page('Representative Assignment Explanations', $this->table(['Doctor', 'Date', 'Reasons', 'Hours before', 'Preference'], array_map(fn (array $assignment): array => [$assignment['doctor_identifier'], $assignment['date'], implode(', ', $assignment['explanation']['reason_codes']), $assignment['explanation']['remaining_required_hours_before'], $assignment['explanation']['preference']], array_slice($report['generation']['assignments'], 0, 10))));
        $operatorPages['explanation-samples'] = $sample;
        $print = implode('', array_map(fn (string $page): string => '<section class="page">'.$this->body($page).'</section>', array_values($operatorPages))).$this->page('Review Conclusion', '<p>The deterministic generated draft remains editable and requires administrator review before publication.</p><p>Complete assignment explanations remain in report.json and html/explanations.html.</p>');
        $this->files->put($html.'/print.html', '<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="assets/report.css"></head><body>'.$print.'</body></html>');

        return ['entrypoint' => 'html/index.html', 'pages' => count($pages), 'aggregate_fingerprint' => hash('sha256', implode('', $pages).$css)];
    }

    private function page(string $title, string $body): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="assets/report.css"></head><body><header><p>GNE Anaesthesia Rostering</p><h1>'.$this->e($title).'</h1></header>'.$body.'</body></html>';
    }

    private function body(string $html): string
    {
        return preg_replace('/^.*?<body>|<\/body>.*$/s', '', $html) ?? $html;
    }

    private function facts(array $report): string
    {
        return '<p>Generator: balanced_greedy 1.0</p><p class="mono">Policy '.$this->e($report['generator']['policy_fingerprint']).'<br>Input '.$this->e($report['generator']['input_fingerprint']).'<br>Generation '.$this->e($report['generator']['generation_fingerprint']).'</p>';
    }

    private function stats(array $summary): string
    {
        return '<div class="stats">'.implode('', array_map(fn (string $key, mixed $value): string => '<div><b>'.$this->e(str_replace('_', ' ', $key)).'</b><p>'.$this->e((string) $value).'</p></div>', array_keys($summary), $summary)).'</div>';
    }

    private function table(array $heads, array $rows): string
    {
        return '<table><thead><tr>'.implode('', array_map(fn ($head): string => '<th>'.$this->e((string) $head).'</th>', $heads)).'</tr></thead><tbody>'.implode('', array_map(fn (array $row): string => '<tr>'.implode('', array_map(fn ($cell): string => '<td>'.$this->e((string) $cell).'</td>', $row)).'</tr>', $rows)).'</tbody></table>';
    }

    private function links(): string
    {
        return implode(' · ', array_map(fn (string $file): string => '<a href="'.$file.'">'.$file.'</a>', ['generation-summary.html', 'roster-calendar.html', 'doctor-matrix.html', 'doctor-hours.html', 'staffing.html', 'preferences.html', 'explanations.html', 'validation.html', 'revisions.html']));
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
