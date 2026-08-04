<?php

namespace App\Infrastructure\Rostering;

use Illuminate\Filesystem\Filesystem;

final readonly class RenderPolicyCalibrationReport
{
    public function __construct(private Filesystem $files) {}

    /** @param array<string, mixed> $report @return array{entrypoint: string, pages: int, aggregate_fingerprint: string} */
    public function handle(string $root, array $report): array
    {
        $html = $root.'/html';
        $this->files->deleteDirectory($html);
        $this->files->ensureDirectoryExists($html.'/assets');
        $this->files->put($html.'/assets/report.css', $this->css());
        $policies = $report['policy']['policies'];
        $pages = [
            'index.html' => ['Anaesthesia Generation Policy Calibration', '<p class="notice">Department meeting pack. Current choices are provisional until explicitly confirmed.</p>'.$this->status($report).$this->links()],
            'policy-register.html' => ['Policy Register', $this->policyTable($policies)],
            'department-questionnaire.html' => ['Department Questionnaire', $this->questionnaire($report)],
            'current-assumptions.html' => ['Current Provisional Assumptions', $this->policyTable($policies)],
            'impact-comparison.html' => ['Policy Impact Comparison', $this->comparison($report)],
            'structural-allocation.html' => ['How Extra Staffing Hours Are Shared', $this->comparison($report).'<p>The application must not choose a fair-share policy without department direction.</p>'],
            'unresolved-policies.html' => ['Unresolved Policies and Confirmation', $this->status($report).'<h2>Confirmation record</h2><p>Meeting date: __________ Authority: __________ Evidence reference: __________</p>'],
        ];
        foreach ($pages as $file => [$title, $body]) {
            $this->files->put($html.'/'.$file, $this->page($title, $body));
        }
        $print = '<section class="cover"><h1>Anaesthesia Generation Policy Calibration</h1><p>Department meeting questionnaire and decision record</p></section><section><h1>Current assumptions</h1>'.$this->policyTable($policies).'</section><section><h1>Department questions</h1>'.$this->questionnaire($report).'</section><section><h1>Impact comparison</h1>'.$this->comparison($report).'</section><section><h1>Unresolved and confirmation</h1>'.$this->status($report).'<p>Meeting date: __________</p><p>Decision authority: __________</p><p>Evidence reference: __________</p><p>Notes:</p><div class="notes"></div></section>';
        $this->files->put($html.'/print.html', $this->page('Anaesthesia Generation Policy Calibration', $print, true));
        $assets = collect($this->files->allFiles($html))->map(fn (\SplFileInfo $file): string => $file->getRelativePathname()."\0".hash_file('sha256', $file->getPathname()))->sort()->values()->all();

        return ['entrypoint' => 'html/index.html', 'pages' => count($pages), 'aggregate_fingerprint' => hash('sha256', implode("\0", $assets))];
    }

    /** @param array<string, mixed> $report */
    private function status(array $report): string
    {
        $status = $report['policy']['calibration'];

        return '<div class="stats"><b>Confirmed '.$this->e((string) count($status['confirmed'])).'</b><b>Provisional '.$this->e((string) count($status['provisional'])).'</b><b>Mandatory unresolved '.$this->e((string) count($status['unresolved_mandatory'])).'</b><b>Quality unresolved '.$this->e((string) count($status['unresolved_quality'])).'</b></div><p class="fingerprint">'.$this->e($status['effective_policy_fingerprint']).'</p>';
    }

    /** @param array<string, mixed> $policies */
    private function policyTable(array $policies): string
    {
        $rows = collect($policies)->map(fn (array $policy): string => '<tr><td>'.$this->e(str_replace('_', ' ', $policy['key'])).'</td><td>'.$this->e($policy['question']).'</td><td>'.$this->e(str_replace('_', ' ', $policy['selected_value'])).'</td><td><b>'.$this->e($policy['status']).'</b></td><td>'.$this->e($policy['decision_authority']).'</td></tr>')->implode('');

        return '<table><thead><tr><th>Policy</th><th>Question</th><th>Current choice</th><th>Status</th><th>Authority</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    /** @param array<string, mixed> $report */
    private function questionnaire(array $report): string
    {
        return '<ol>'.collect($report['questionnaire_sections'])->map(fn (string $section): string => '<li><b>'.$this->e(ucfirst($section)).'</b><br>Decision: ____________________ Notes: ______________________________</li>')->implode('').'</ol>';
    }

    /** @param array<string, mixed> $report */
    private function comparison(array $report): string
    {
        $comparison = $report['comparison'];

        return '<p>Doctor A target: 160 hours. Doctor B target: 80 hours. Extra staffing hours: 24.</p><table><thead><tr><th>Fair-share policy</th><th>Doctor A</th><th>Doctor B</th></tr></thead><tbody><tr><td>Equal</td><td>12</td><td>12</td></tr><tr><td>Proportional to target</td><td>16</td><td>8</td></tr><tr><td>Unresolved</td><td>Not classified</td><td>Not classified</td></tr></tbody></table><p>'.$this->e($comparison['unresolved']['explanation']).'</p>';
    }

    private function links(): string
    {
        return '<nav><a href="policy-register.html">Policy register</a><a href="department-questionnaire.html">Questionnaire</a><a href="current-assumptions.html">Current assumptions</a><a href="impact-comparison.html">Impact comparison</a><a href="structural-allocation.html">Structural allocation</a><a href="unresolved-policies.html">Unresolved policies</a></nav>';
    }

    private function page(string $title, string $body, bool $print = false): string
    {
        $heading = $print ? '' : '<p class="eyebrow">GNE · Anaesthesia Rostering</p><h1>'.$this->e($title).'</h1>';

        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>'.$this->e($title).'</title><link rel="stylesheet" href="assets/report.css"></head><body class="'.($print ? 'print' : '').'"><main>'.$heading.$body.'</main></body></html>';
    }

    private function css(): string
    {
        return ':root{font-family:Arial,sans-serif;color:#18313b;background:#f3f7f5}body{margin:0}main{max-width:1100px;margin:0 auto;padding:40px;background:white;min-height:100vh}h1{color:#0f5c5c}.eyebrow{color:#087f75;font-weight:700;letter-spacing:.08em}.notice{border-left:5px solid #d89b16;background:#fff7df;padding:16px}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.stats b{padding:16px;background:#e7f4f1;border-radius:8px}table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;vertical-align:top;border:1px solid #cad7d3;padding:9px}th{background:#e7f4f1}nav{display:grid;gap:8px;margin-top:24px}a{color:#0f5c5c}li{margin:14px 0}.fingerprint{font-family:monospace;font-size:11px;overflow-wrap:anywhere}.notes{height:150px;border:1px solid #789}@media print{body{background:#fff}.print main{padding:0}.print section{box-sizing:border-box;break-after:page;padding:14mm}.print section:last-child{break-after:auto}.cover{display:grid;place-content:center;text-align:center;height:185mm}.stats{grid-template-columns:repeat(2,1fr)}}';
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
