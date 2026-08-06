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
            'index.html' => ['Anaesthesia Generation Policy Calibration', $this->cover($report).$this->links()],
            'confirmability-legend.html' => ['Confirmability Legend', $this->confirmabilityLegend($policies)],
            'current-effective-policy.html' => ['Current Effective Policy', $this->status($report).$this->policyTable($policies)],
            'policy-compatibility.html' => ['Required Hours Compatibility', $this->compatibility($report)],
            'enforcement-readiness.html' => ['Enforcement Readiness', $this->readiness($report)],
            'availability-decision.html' => ['Availability Decision', $this->decision($policies['unspecified_availability'], ['May a doctor with no accepted request be assigned?', 'Does employment type change the rule?'])],
            'required-hours-decision.html' => ['Required Hours Meaning', $this->decision($policies['required_hours_meaning'], ['What does the authored number represent?', 'Do leave and education count? Enforcement is selected on a separate page.'])],
            'employment-types-decision.html' => ['Employment-Type Availability Eligibility', $this->decision($policies['employment_type_eligibility'], ['Select only which employment categories require explicit accepted availability. Target treatment is outside this policy.'])],
            'structural-allocation-decision.html' => ['Structural Allocation Decision', $this->decision($policies['structural_hours_allocation'], ['Choose how unavoidable excess or shortage is allocated.']).$this->comparison($report)],
            'weekend-decision.html' => ['Weekend Distribution', $this->decision($policies['weekend_distribution'], ['Enter the maximum difference and choose combined or separate Saturday/Sunday evaluation. Public holidays are excluded.'])],
            'consecutive-day-decision.html' => ['Consecutive Assigned Days', $this->decision($policies['consecutive_day_limit'], ['Enter the maximum assigned-calendar-day run. Leave and unassigned days break it; on-call and rest-after-run are deferred.'])],
            'target-hours-decision.html' => ['Target Hours Enforcement', $this->decision($policies['target_hours_enforcement'], ['Required-hours meaning defines the number; this page defines enforcement only. Hard limits are not operationally ready.'])],
            'preferences-decision.html' => ['Preferences', $this->decision($policies['preference_strength'], ['Preferred-off prohibition is a hard eligibility exclusion; override support is deferred.'])],
            'holidays-and-credited-hours-decision.html' => ['Public Holidays and Credited Hours — Discovery Only', '<div class="notice"><b>Discovery topic only · Not supported in this release</b><p>These decisions cannot be confirmed because the roster supports standard-day assignments and has no authoritative holiday source.</p></div>'.$this->prompts(['Future authoritative public-holiday source', 'Future varying duty-credit model'])],
            'permanent-supersession.html' => ['Permanent Supersession', $this->permanentSupersession($report)],
            'impact-comparison.html' => ['No-Mutation Impact Comparison', $this->impact($report)],
            'confirmation-summary.html' => ['Decision Authority and Confirmation Record', $this->decisionRecord($report)],
        ];
        foreach ($pages as $file => [$title, $body]) {
            $this->files->put($html.'/'.$file, $this->page($title, $body));
        }
        $printSections = collect($pages)->map(fn (array $page, string $file): string => '<section class="print-page"><p class="eyebrow">GNE · Anaesthesia Rostering</p><h1>'.$this->e($page[0]).'</h1>'.$page[1].'<footer>'.($file === 'confirmation-summary.html' ? 'Technical identity: '.$this->e($report['policy']['fingerprint']) : 'Department decision pack · '.$this->e($file)).'</footer></section>')->implode('');
        $this->files->put($html.'/print.html', $this->page('Anaesthesia Generation Policy Calibration', $printSections, true));
        $assets = collect($this->files->allFiles($html))->map(fn (\SplFileInfo $file): string => $file->getRelativePathname()."\0".hash_file('sha256', $file->getPathname()))->sort()->values()->all();

        return ['entrypoint' => 'html/index.html', 'pages' => count($pages), 'aggregate_fingerprint' => hash('sha256', implode("\0", $assets))];
    }

    /** @param array<string, mixed> $report */
    private function cover(array $report): string
    {
        return '<div class="hero"><p class="kicker">Department decision pack</p><h2>Choices, operational impacts, authority, and effective dates</h2><p>This pack converts eight provisional working assumptions into answerable, repository-registered decisions. It does not confirm policy automatically.</p>'.$this->status($report).'</div>';
    }

    /** @param array<string, mixed> $report */
    private function status(array $report): string
    {
        $status = $report['policy']['calibration'];

        return '<div class="stats"><div><b>'.count($status['confirmed']).'</b><span>Confirmed decisions</span></div><div><b>'.count($status['provisional']).'</b><span>Provisional assumptions</span></div><div><b>'.$status['pending_department_decisions'].'</b><span>Pending confirmation</span></div><div><b>'.count($status['unresolved_mandatory']).'</b><span>Blocking unresolved</span></div><div><b>'.count($status['unresolved_quality']).'</b><span>Non-blocking unresolved</span></div></div><p class="small">Evaluation date: '.$this->e((string) $status['evaluation_date']).'</p>';
    }

    /** @param array<string, mixed> $policies */
    private function policyTable(array $policies): string
    {
        $rows = collect($policies)->map(fn (array $policy): string => '<tr><td>'.$this->e($this->human($policy['key'])).'</td><td>'.$this->e($this->human($policy['selected_value'])).'</td><td>'.$this->e($policy['status']).'</td><td>r'.$this->e((string) $policy['revision']).'</td><td>'.$this->e($policy['effective_date'] ?? 'Repository fallback').'</td></tr>')->implode('');

        return '<table><thead><tr><th>Policy</th><th>Current effective choice</th><th>Status</th><th>Revision</th><th>Effective from</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    /** @param array<string, mixed> $report */
    private function compatibility(array $report): string
    {
        $rows = '';
        /** @var array{required_hours_meaning: string, target_hours_enforcement: string, compatible: bool} $entry */
        foreach ($report['compatibility_matrix']['entries'] as $entry) {
            $rows .= '<tr><td>'.$this->e($this->human($entry['required_hours_meaning'])).'</td><td>'.$this->e($this->human($entry['target_hours_enforcement'])).'</td><td>'.($entry['compatible'] ? 'Compatible' : 'Conflict').'</td></tr>';
        }

        return '<div class="notice"><b>One authority per concern</b><p>Required Hours Meaning defines what the number represents. Target Hours Enforcement defines how that number is applied.</p></div><table><thead><tr><th>Meaning</th><th>Enforcement</th><th>Result</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    /** @param array<string, mixed> $report */
    private function readiness(array $report): string
    {
        $readiness = $report['enforcement_readiness'];
        $coverage = '';
        /** @var array{policy_key: string, selected_value: string, generator: bool, validator: bool, quality: bool, enforcement_ready: bool} $entry */
        foreach ($readiness['coverage'] as $entry) {
            $coverage .= '<tr><td>'.$this->e($this->human($entry['policy_key'])).'</td><td>'.$this->e($this->human($entry['selected_value'])).'</td><td>'.($entry['generator'] ? 'Yes' : 'No').'</td><td>'.($entry['validator'] ? 'Yes' : 'No').'</td><td>'.($entry['quality'] ? 'Yes' : 'No').'</td><td>'.($entry['enforcement_ready'] ? 'Ready' : 'Not ready').'</td></tr>';
        }

        return '<div class="hero"><p class="kicker">Operational activation</p><h2>'.$this->e($this->human($readiness['status'])).'</h2><p>Confirmation status: '.$this->e($this->human($readiness['activation_status'])).'</p><p>Confirmed choices are not operational until coherence, runtime coverage, and selected-period feasibility pass.</p></div><table><thead><tr><th>Policy</th><th>Choice</th><th>Generator</th><th>Validator</th><th>Quality</th><th>Enforcement</th></tr></thead><tbody>'.$coverage.'</tbody></table><p class="technical">Enforcement fingerprint: '.$this->e($readiness['enforcement_fingerprint']).'</p>';
    }

    /** @param array<string, mixed> $report */
    private function temporal(array $report): string
    {
        $future = $report['policy']['future_policies'];
        $expired = $report['policy']['expired_policies'];

        return '<div class="columns"><div><h2>Future scheduled</h2>'.($future === [] ? '<p>None in the demonstration.</p>' : $this->history($future)).'</div><div><h2>Expired or superseded</h2>'.($expired === [] ? '<p>None in the demonstration.</p>' : $this->history($expired)).'</div></div><div class="notice"><b>Temporal rule</b><p>A higher revision does not become effective before its start date. Expired and rejected revisions are not current. Historical snapshots remain immutable.</p></div>';
    }

    /** @param list<array<string, mixed>> $items */
    private function history(array $items): string
    {
        return '<ul>'.collect($items)->map(fn (array $item): string => '<li>'.$this->e($this->human($item['policy_key'])).' r'.$this->e((string) $item['revision']).': '.$this->e($this->human($item['selected_value'])).' ('.$this->e((string) ($item['effective_from'] ?? '')).' - '.$this->e((string) ($item['effective_until'] ?? 'open')).')</li>')->implode('').'</ul>';
    }

    /** @param array<string, mixed> $report */
    private function questionnaireOverview(array $report): string
    {
        return '<p>Each section presents registered choices and impacts. Record one selection, notes, authority, effective date, and evidence reference.</p><ol>'.collect($report['questionnaire_sections'])->map(fn (string $section): string => '<li>'.$this->e(ucwords($section)).'</li>')->implode('').'</ol><div class="notice">"Other" requires a future registered grammar revision; it cannot be typed as an arbitrary operational value.</div>';
    }

    /** @param array<string, mixed> $policy @param list<string> $prompts */
    private function decision(array $policy, array $prompts): string
    {
        $options = collect($policy['options'])->map(function (array $option): string {
            $status = ! $option['supported'] ? 'Not supported in this release' : (! $option['confirmation_required'] ? 'Decision pending' : ($option['parameters'] === [] ? 'Confirmable' : 'Configuration required'));
            $parameters = collect($option['parameters'])->map(fn (array $parameter): string => $parameter['type'] === 'enum_list'
                ? '<p class="parameter">'.$this->e($parameter['label']).': '.implode(' &nbsp; ', array_map(fn (string $value): string => '□ '.$this->e($this->human($value)), $parameter['values'])).'</p>'
                : '<p class="parameter">'.$this->e($parameter['label']).': ____________________ <small>('.$this->e($parameter['type']).($parameter['minimum'] !== null ? ', minimum '.$parameter['minimum'] : '').($parameter['values'] !== [] ? ': '.implode(' / ', array_map($this->e(...), $parameter['values'])) : '').')</small></p>')->implode('');
            $dependencies = $option['unsupported_dependencies'] === [] ? '' : '<p class="unsupported">Requires: '.$this->e(implode(', ', $option['unsupported_dependencies'])).'</p>';

            return '<div class="option '.(! $option['supported'] ? 'disabled' : '').'"><span class="checkbox">□</span><div><b>'.$this->e($option['label']).'</b> · <span class="badge">'.$this->e($status).'</span><p>'.$this->e($option['description']).'</p><p class="impact"><b>Operational impact:</b> '.$this->e($option['impact']).'</p>'.$parameters.$dependencies.'</div></div>';
        })->implode('');

        return '<div class="current"><b>Question</b><p>'.$this->e($policy['question']).'</p><b>Current provisional choice</b><p>'.$this->e($this->human($policy['selected_value'])).'</p></div>'.$this->prompts($prompts).'<h2>Registered candidate choices</h2><div class="options">'.$options.'</div>'.$this->decisionLines();
    }

    /** @param array<string, mixed> $policies */
    private function confirmabilityLegend(array $policies): string
    {
        $options = collect($policies)->flatMap(fn (array $policy): array => $policy['options']);

        return '<div class="stats"><div><b>'.$options->filter(fn (array $option): bool => $option['supported'] && $option['confirmation_required'] && $option['parameters'] === [])->count().'</b><span>Confirmable now</span></div><div><b>'.$options->filter(fn (array $option): bool => $option['supported'] && $option['confirmation_required'] && $option['parameters'] !== [])->count().'</b><span>Configuration required</span></div><div><b>'.$options->where('supported', false)->count().'</b><span>Not supported</span></div><div><b>2</b><span>Discovery-only topics</span></div><div><b>8</b><span>Runtime policies</span></div></div><div class="notice"><b>Operational completeness</b><p>A registered choice is confirmable only when it is supported and every typed parameter is supplied. Unknown parameters fail closed.</p></div>';
    }

    /** @param array<string, mixed> $report */
    private function permanentSupersession(array $report): string
    {
        return '<div class="notice"><b>Option A — permanent supersession</b><p>A new confirmed revision closes the earlier revision on the day before its effective date. If the new revision expires, the earlier revision does not resume. The repository provisional fallback applies unless another confirmed revision is scheduled.</p></div><ol><li>Revision 1 begins 2026-01-01.</li><li>Revision 2 begins 2026-09-01 and expires 2026-09-30.</li><li>Revision 1 closes on 2026-08-31.</li><li>After September, use the provisional fallback—not Revision 1.</li></ol><p><b>Operator warning:</b> schedule the next revision when confirmed coverage must continue.</p><p class="technical">Current fingerprint: '.$this->e($report['policy']['fingerprint']).'</p>';
    }

    /** @param list<string> $prompts */
    private function prompts(array $prompts): string
    {
        return '<div class="prompts"><h2>Points to decide</h2><ul>'.collect($prompts)->map(fn (string $prompt): string => '<li>'.$this->e($prompt).'</li>')->implode('').'</ul></div>';
    }

    private function decisionLines(): string
    {
        return '<div class="record"><p>Department selection: __________________________________________</p><p>Notes: _______________________________________________________</p><p>Decision authority: __________________ Effective date: __________</p><p>Evidence / meeting reference: __________________________________</p></div>';
    }

    /** @param array<string, mixed> $report */
    private function comparison(array $report): string
    {
        return '<h2>160 / 80 target example</h2><table><thead><tr><th>Allocation</th><th>Doctor A (160h)</th><th>Doctor B (80h)</th></tr></thead><tbody><tr><td>Equal</td><td>12h</td><td>12h</td></tr><tr><td>Proportional</td><td>16h</td><td>8h</td></tr><tr><td>Unresolved</td><td colspan="2">Individual fairness not classified</td></tr></tbody></table><p>'.$this->e($report['comparison']['unresolved']['explanation']).'</p>';
    }

    /** @param array<string, mixed> $report */
    private function impact(array $report): string
    {
        return '<div class="columns"><div class="impact-card"><h2>Explicit availability required</h2><p><b>Before:</b> 179 of 180 demonstration assignments used unspecified eligibility.</p><p><b>After:</b> those 179 would no longer be eligible without explicit accepted availability.</p><p><b>Effect:</b> readiness may fall and dates may become understaffable.</p></div><div class="impact-card"><h2>Proportional structural allocation</h2><p><b>Equal:</b> 12 / 12 hours.</p><p><b>Proportional:</b> 16 / 8 hours.</p><p><b>Effect:</b> residual fairness is classified against target obligations.</p></div></div><div class="notice"><b>No mutation</b><p>Impact previews calculate candidate fingerprints but do not save policy decisions or change roster assignments.</p></div>';
    }

    /** @param array<string, mixed> $report */
    private function decisionRecord(array $report): string
    {
        return $this->status($report).'<div class="signature"><p>Meeting date: _________________________________________________</p><p>Decision authority name: _____________________________________</p><p>Decision authority designation: _______________________________</p><p>Department: Anaesthesia Department</p><p>Effective date: _______________________________________________</p><p>Evidence / reference: _________________________________________</p><p>General notes:</p><div class="notes"></div><p>Signature / acknowledgment: __________________________________</p></div><p class="technical"><b>Technical effective policy fingerprint</b><br>'.$this->e($report['policy']['fingerprint']).'</p>';
    }

    private function links(): string
    {
        return '<nav><a href="current-effective-policy.html">Current effective policy</a><a href="policy-compatibility.html">Compatibility matrix</a><a href="enforcement-readiness.html">Enforcement readiness</a><a href="impact-comparison.html">Impact comparison</a><a href="confirmation-summary.html">Confirmation record</a></nav>';
    }

    private function page(string $title, string $body, bool $print = false): string
    {
        $heading = $print ? '' : '<p class="eyebrow">GNE · Anaesthesia Rostering</p><h1>'.$this->e($title).'</h1>';

        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>'.$this->e($title).'</title><link rel="stylesheet" href="assets/report.css"></head><body class="'.($print ? 'print' : '').'"><main>'.$heading.$body.'</main></body></html>';
    }

    private function human(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }

    private function css(): string
    {
        return ':root{font-family:Arial,sans-serif;color:#17313a;background:#eef4f2}*{box-sizing:border-box}body{margin:0}main{max-width:1120px;margin:0 auto;padding:40px;background:#fff;min-height:100vh}h1{color:#0d5d5d;font-size:30px}h2{color:#23444d;font-size:18px;margin:18px 0 8px}.eyebrow,.kicker{color:#087f75;font-weight:700;letter-spacing:.08em;text-transform:uppercase;font-size:12px}.hero{padding:28px;border-radius:18px;background:linear-gradient(135deg,#e5f6f2,#fef7df)}.notice,.current,.prompts,.record,.signature,.impact-card{border-left:5px solid #cf9517;background:#fff8e6;padding:14px;margin:14px 0}.stats{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin:18px 0}.stats div{padding:12px;background:#e6f3ef;border-radius:9px}.stats b{display:block;font-size:25px;color:#0d5d5d}.stats span{font-size:11px}.small{font-size:12px;color:#52666c}.columns{display:grid;grid-template-columns:1fr 1fr;gap:18px}.options{display:grid;grid-template-columns:1fr 1fr;gap:9px}.option{display:flex;gap:10px;border:1px solid #bfd1cc;border-radius:8px;padding:10px;break-inside:avoid}.option p{margin:4px 0;font-size:12px}.checkbox{font-size:20px}.impact{color:#425c64}table{width:100%;border-collapse:collapse;font-size:11px}th,td{text-align:left;vertical-align:top;border:1px solid #c5d5d1;padding:7px}th{background:#e6f3ef}nav{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:22px}a{color:#0d5d5d}.record p,.signature p{margin:11px 0}.notes{height:80px;border:1px solid #879b9f;background:#fff}.technical{font-family:monospace;font-size:9px;overflow-wrap:anywhere;margin-top:20px;color:#596d72}footer{position:absolute;bottom:8mm;left:14mm;right:14mm;border-top:1px solid #ccd8d5;padding-top:3mm;color:#68797d;font-size:8px;overflow-wrap:anywhere}@media print{@page{size:A4;margin:0}body{background:#fff}.print main{max-width:none;padding:0}.print-page{position:relative;width:210mm;height:297mm;padding:13mm 14mm 17mm;break-after:page;overflow:hidden}.print-page:last-child{break-after:auto}.print-page h1{font-size:24px;margin:4px 0 10px}.print-page h2{font-size:15px;margin:10px 0 5px}.print-page p,.print-page li{font-size:10px;line-height:1.35}.print-page .stats{margin:9px 0}.print-page .option{padding:6px}.print-page .option p{font-size:9px}.print-page .record{padding:8px}.print-page .record p{margin:6px 0}.print-page .signature .notes{height:35mm}.print-page .compact h2{margin:4px 0 2px;font-size:12px}.print-page .compact .current,.print-page .compact .prompts,.print-page .compact .record{padding:4px;margin:4px 0}.print-page .compact p,.print-page .compact li{font-size:8px;line-height:1.2;margin:2px 0}.print-page .compact .options{gap:3px}.print-page .compact .option{padding:3px}.print-page .compact .option p{font-size:7px}.print-page .compact .record p{margin:2px 0}}';
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
