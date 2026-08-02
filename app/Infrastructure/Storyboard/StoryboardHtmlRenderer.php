<?php

namespace App\Infrastructure\Storyboard;

use Illuminate\Filesystem\Filesystem;

final readonly class StoryboardHtmlRenderer
{
    public const Format = 'gne-storyboard-html/1.0';

    public function __construct(private Filesystem $files) {}

    /** @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    public function render(string $root, array $manifest): array
    {
        $frames = $this->frames($manifest);
        $this->assertCaptured($root, $manifest);
        $htmlRoot = $root.'/html';
        $this->files->deleteDirectory($htmlRoot);
        foreach (['assets/frames', 'acts', 'frames'] as $directory) {
            $this->files->ensureDirectoryExists($htmlRoot.'/'.$directory);
        }

        foreach ($frames as $frame) {
            $this->files->copy($root.'/'.$frame['capture_filename'], $htmlRoot.'/assets/'.$frame['capture_filename']);
        }
        $this->files->put($htmlRoot.'/assets/storyboard.css', $this->stylesheet());
        $this->files->put($htmlRoot.'/index.html', $this->index($manifest));

        $acts = $this->groupActs($frames);
        foreach ($acts as $act => $frames) {
            $this->files->put($htmlRoot.'/acts/'.$act.'.html', $this->act($manifest, $act, $frames));
        }
        foreach ($frames = $this->frames($manifest) as $index => $frame) {
            $this->files->put(
                $htmlRoot.'/frames/'.$this->framePage($frame),
                $this->frame($manifest, $frame, $frames[$index - 1] ?? null, $frames[$index + 1] ?? null),
            );
        }
        $this->files->put($htmlRoot.'/print.html', $this->print($manifest));

        $assets = collect($this->files->allFiles($htmlRoot))
            ->reject(fn (\SplFileInfo $file): bool => $file->getRelativePathname() === 'manifest.json')
            ->map(fn (\SplFileInfo $file): array => [
                'path' => str_replace('\\', '/', $file->getRelativePathname()),
                'sha256' => $this->hash($file->getPathname()),
                'byte_length' => $file->getSize(),
            ])->sortBy('path')->values()->all();
        $aggregate = hash('sha256', implode("\0", array_map(
            fn (array $asset): string => $asset['path']."\0".$asset['sha256'],
            $assets,
        )));
        $htmlManifest = [
            'format' => 'gne-storyboard-html-assets/1.0',
            'storyboard' => $manifest['identifier'],
            'entrypoint' => 'index.html',
            'source_manifest_fingerprint' => $manifest['finalized_frame_fingerprint'],
            'assets' => $assets,
            'aggregate_fingerprint' => $aggregate,
        ];
        $this->writeJson($htmlRoot.'/manifest.json', $htmlManifest);

        return [
            'format' => self::Format,
            'status' => 'generated',
            'entrypoint' => 'html/index.html',
            'page_count' => count($frames) + count($acts) + 1,
            'frame_page_count' => count($frames),
            'asset_count' => count($assets),
            'asset_manifest' => 'html/manifest.json',
            'aggregate_fingerprint' => $aggregate,
        ];
    }

    /** @param array<string, mixed> $manifest */
    private function assertCaptured(string $root, array $manifest): void
    {
        foreach ($this->frames($manifest) as $frame) {
            $path = $root.'/'.$frame['capture_filename'];
            if ($frame['capture_status'] !== 'captured' || ! is_file($path) || $this->hash($path) !== $frame['capture_checksum']) {
                throw new StoryboardArtifactException("Final storyboard rendition requires captured frame {$frame['identifier']}.");
            }
        }
    }

    /** @param array<string, mixed> $manifest */
    private function index(array $manifest): string
    {
        $frames = $this->frames($manifest);
        $acts = $this->groupActs($frames);
        $actLinks = implode('', array_map(fn (string $act, array $actFrames): string => '<li><a href="acts/'.$this->e($act).'.html">'.$this->e($this->actTitle($act)).'</a><span>'.count($actFrames).' frames</span></li>', array_keys($acts), $acts));
        $cards = implode('', array_map(fn (array $frame): string => '<article class="card"><a href="frames/'.$this->framePage($frame).'"><img src="assets/'.$this->e($frame['capture_filename']).'" alt="'.$this->e($frame['title'].': '.$frame['expected']).'"><span class="eyebrow">Frame '.$frame['sequence'].' · '.$this->e($this->actTitle($frame['act'])).'</span><h2>'.$this->e($frame['title']).'</h2><p>'.$this->e($frame['expected']).'</p></a></article>', $frames));
        $last = $frames[count($frames) - 1]['snapshot']['lifecycle'];
        $body = '<section class="hero"><p class="eyebrow">GNE repository-native demonstration</p><h1>'.$this->e($manifest['title']).'</h1><p class="lede">One compiled journey, captured from authenticated application pages and projected as durable offline evidence.</p></section>'
            .$this->notice().'<section class="facts"><div><b>Subject</b><span>'.$this->e($manifest['subject_identifier']).'</span></div><div><b>Frames</b><span>'.count($frames).' captured</span></div><div><b>Final lifecycle</b><span>'.$this->e($last['current_stage'] ?? 'not started').'</span></div><div><b>Frame fingerprint</b><code>'.$this->e($manifest['finalized_frame_fingerprint']).'</code></div></section>'
            .'<section><h2>Five-act journey</h2><ul class="act-list">'.$actLinks.'</ul></section><section><h2>Captured walkthrough</h2><div class="cards">'.$cards.'</div></section>'
            .'<section><h2>Portable renditions</h2><ul><li><a href="../pdf/'.$this->e($manifest['identifier']).'.pdf">PDF rendition</a></li><li><a href="../narration.md">Narration</a></li><li><a href="../movie/manifest.json">Movie build manifest</a></li><li><a href="manifest.json">HTML asset checksums</a></li></ul></section>'
            .'<section><h2>Architecture boundary</h2><p>Repository truth is reconstructed in isolated working repositories. These HTML, PDF, screenshot, and movie assets are disposable projections and never author business truth.</p><h3>Personas</h3><p>'.$this->e(implode(', ', $manifest['personas'])).'</p><h3>Limitations</h3><p>Local operator demonstration only. No production deployment, business mutation, payment settlement, or action execution is represented.</p></section>';

        return $this->page($manifest['title'], '', $body);
    }

    /** @param array<string, mixed> $manifest
     * @param  list<array<string, mixed>>  $frames
     */
    private function act(array $manifest, string $act, array $frames): string
    {
        $items = implode('', array_map(fn (array $frame): string => '<article class="wide-card"><a href="../frames/'.$this->framePage($frame).'"><img src="../assets/'.$this->e($frame['capture_filename']).'" alt="'.$this->e($frame['title'].': '.$frame['expected']).'"><div><span class="eyebrow">Frame '.$frame['sequence'].'</span><h2>'.$this->e($frame['title']).'</h2><p>'.$this->e($frame['action']).'</p><p><b>Lifecycle:</b> '.$this->e($frame['snapshot']['lifecycle']['current_stage'] ?? 'not started').'</p></div></a></article>', $frames));
        $body = '<p><a href="../index.html">← Storyboard index</a></p><section class="hero compact"><p class="eyebrow">Act</p><h1>'.$this->e($this->actTitle($act)).'</h1><p class="lede">'.$this->e($this->actPurpose($act)).'</p></section>'.$this->notice().'<div class="wide-cards">'.$items.'</div>';

        return $this->page($manifest['title'].' - '.$this->actTitle($act), '../', $body);
    }

    /** @param array<string, mixed> $manifest
     * @param  array<string, mixed>  $frame
     * @param  array<string, mixed>|null  $previous
     * @param  array<string, mixed>|null  $next
     */
    private function frame(array $manifest, array $frame, ?array $previous, ?array $next): string
    {
        $snapshot = $frame['snapshot'];
        $resolvedIdentifiers = [];
        $pendingIdentifiers = [];
        $missingEvidence = [];
        foreach ($snapshot['documents'] as $document) {
            if ($document['readiness'] === 'resolved') {
                $resolvedIdentifiers[] = $document['identifier'];
            }
            if ($document['readiness'] === 'pending') {
                $pendingIdentifiers[] = $document['identifier'];
                foreach ($document['missing_evidence'] as $evidence) {
                    $missingEvidence[] = $evidence['artifact_type'] ?? $evidence['reason'] ?? 'unspecified';
                }
            }
        }
        $resolved = implode(', ', $resolvedIdentifiers) ?: 'None';
        $pending = implode(', ', $pendingIdentifiers) ?: 'None';
        $missing = implode(', ', array_values(array_unique($missingEvidence))) ?: 'None';
        $browser = $snapshot['browser'];
        $navigation = '<nav><span>'.($previous === null ? '' : '<a href="'.$this->framePage($previous).'">← '.$this->e($previous['title']).'</a>').'</span><a href="../index.html">Index</a><span>'.($next === null ? '' : '<a href="'.$this->framePage($next).'">'.$this->e($next['title']).' →</a>').'</span></nav>';
        $details = [
            'Persona' => $frame['persona'], 'User action' => $frame['action'], 'Application route' => $frame['route'],
            'Expected visible state' => $frame['expected'], 'Lifecycle stage' => $snapshot['lifecycle']['current_stage'] ?? 'not started',
            'Next stage' => $snapshot['lifecycle']['next_stage'] ?? 'complete', 'Resolved documents' => $resolved,
            'Pending documents' => $pending, 'Missing evidence' => $missing,
            'Repository fingerprint' => $snapshot['repository_fingerprint'], 'Screenshot SHA-256' => $frame['capture_checksum'],
            'Browser representation' => $browser['representation'] ?? 'not applicable', 'Browser checksum' => $browser['checksum'] ?? 'not applicable',
            'Browser ETag' => $browser['etag'] ?? 'not applicable',
        ];
        $rows = '';
        foreach ($details as $label => $value) {
            $rows .= '<dt>'.$this->e((string) $label).'</dt><dd>'.$this->e((string) $value).'</dd>';
        }
        $body = $navigation.'<header class="frame-heading"><span class="eyebrow">Frame '.$frame['sequence'].' · '.$this->e($this->actTitle($frame['act'])).'</span><h1>'.$this->e($frame['title']).'</h1><p class="lede">'.$this->e($frame['action'].' '.$frame['expected'].'.').'</p></header>'.$this->notice().'<a class="screenshot" href="../assets/'.$this->e($frame['capture_filename']).'"><img src="../assets/'.$this->e($frame['capture_filename']).'" alt="'.$this->e($frame['title'].': '.$frame['expected']).'"></a><dl class="metadata">'.$rows.'</dl>'.$navigation;

        return $this->page($manifest['title'].' - '.$frame['title'], '../', $body);
    }

    /** @param array<string, mixed> $manifest */
    private function print(array $manifest): string
    {
        $frames = $this->frames($manifest);
        $pages = implode('', array_map(function (array $frame): string {
            $lifecycle = $frame['snapshot']['lifecycle'];

            return '<section class="print-page"><header><span class="eyebrow">'.$this->e($this->actTitle($frame['act'])).' · Frame '.$frame['sequence'].'</span><h1>'.$this->e($frame['title']).'</h1></header><div class="print-layout"><img src="assets/'.$this->e($frame['capture_filename']).'" alt="'.$this->e($frame['title'].': '.$frame['expected']).'"><aside><h2>'.$this->e($frame['persona']).'</h2><p>'.$this->e($frame['action']).'</p><dl><dt>Expected state</dt><dd>'.$this->e($frame['expected']).'</dd><dt>Lifecycle</dt><dd>'.$this->e($lifecycle['current_stage'] ?? 'not started').'</dd><dt>Next stage</dt><dd>'.$this->e($lifecycle['next_stage'] ?? 'complete').'</dd><dt>Capture</dt><dd>Captured · '.$this->e($frame['capture_checksum']).'</dd></dl></aside></div></section>';
        }, $frames));
        $body = '<section class="print-page print-cover"><p class="eyebrow">GNE Repository-Native Business Compiler</p><h1>'.$this->e($manifest['title']).'</h1><p>'.count($frames).' authenticated captured frames</p><p>'.$this->e($manifest['subject_identifier']).'</p><p>Storyboard observation only - no business truth is authored by this projection.</p></section>'.$pages;

        return $this->page($manifest['title'].' print rendition', '', $body, 'print-document');
    }

    private function page(string $title, string $assetPrefix, string $body, string $bodyClass = ''): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$this->e($title).'</title><link rel="stylesheet" href="'.$assetPrefix.'assets/storyboard.css"></head><body class="'.$bodyClass.'"><main>'.$body.'</main></body></html>';
    }

    private function notice(): string
    {
        return '<p class="notice">Static storyboard evidence. Links do not execute business actions, and captured screens are not editable.</p>';
    }

    /** @param array<string, mixed> $frame */
    private function framePage(array $frame): string
    {
        return sprintf('%03d-%s.html', $frame['sequence'], $frame['identifier']);
    }

    private function actTitle(string $act): string
    {
        return ucwords(str_replace('-', ' ', $act));
    }

    private function actPurpose(string $act): string
    {
        return match ($act) {
            'entering-system' => 'Authenticate and locate the explicit repository-native business subject.',
            'starting-process' => 'Follow the offering, application, and assessment evidence that begins the reservation.',
            'commercial-commitment' => 'Observe immutable invoice revision and browser document expression.',
            'manual-payment' => 'Keep payment proof, approval, and receipt readiness truthfully distinct.',
            'completion' => 'Trace receipt and certification evidence through the final resolved document.',
            default => 'Review this ordered segment of the repository-derived journey.',
        };
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @param array<string, mixed> $manifest
     * @return list<array<string, mixed>>
     */
    private function frames(array $manifest): array
    {
        if (! isset($manifest['frames']) || ! is_array($manifest['frames'])) {
            throw new StoryboardArtifactException('Storyboard manifest frames are missing.');
        }
        $frames = [];
        foreach ($manifest['frames'] as $frame) {
            if (! is_array($frame)) {
                throw new StoryboardArtifactException('Storyboard manifest contains an invalid frame.');
            }
            $frames[] = $frame;
        }

        return $frames;
    }

    /** @param list<array<string, mixed>> $frames
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupActs(array $frames): array
    {
        $acts = [];
        foreach ($frames as $frame) {
            $acts[$frame['act']][] = $frame;
        }

        return $acts;
    }

    private function hash(string $path): string
    {
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new StoryboardArtifactException("Unable to fingerprint storyboard asset {$path}.");
        }

        return $hash;
    }

    /** @param array<string, mixed> $value */
    private function writeJson(string $path, array $value): void
    {
        $this->files->put($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    }

    private function stylesheet(): string
    {
        return <<<'CSS'
:root{color-scheme:light;--ink:#17323a;--muted:#587078;--paper:#f5f2ea;--card:#fff;--line:#c8d4d5;--accent:#b76025;--deep:#123b3d}*{box-sizing:border-box}html{background:var(--paper)}body{margin:0;color:var(--ink);font:16px/1.55 ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}main{width:min(1180px,calc(100% - 32px));margin:0 auto;padding:40px 0 80px}a{color:var(--deep);text-decoration-color:var(--accent);text-underline-offset:3px}h1,h2,h3{line-height:1.1}.hero{background:var(--deep);color:white;padding:clamp(32px,7vw,88px);border-radius:22px}.hero.compact{padding:42px}.hero h1{font:700 clamp(42px,7vw,78px)/.96 Georgia,serif;margin:.2em 0}.lede{font-size:1.22rem;max-width:760px}.eyebrow{font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--accent);font-size:.78rem}.notice{border-left:4px solid var(--accent);background:#fff7ec;padding:12px 16px}.facts{display:grid;grid-template-columns:repeat(2,1fr);gap:1px;background:var(--line);margin:32px 0}.facts div{background:white;padding:20px;display:flex;flex-direction:column}.facts code{font-size:.72rem;overflow-wrap:anywhere}.act-list{list-style:none;padding:0}.act-list li{display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding:14px 0}.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}.card,.wide-card{background:var(--card);border:1px solid var(--line);border-radius:14px;overflow:hidden}.card a,.wide-card a{display:block;color:inherit;text-decoration:none}.card img{width:100%;aspect-ratio:16/10;object-fit:cover;border-bottom:1px solid var(--line)}.card h2,.card p,.card span{margin-left:16px;margin-right:16px}.card span{display:block;margin-top:14px}.wide-cards{display:grid;gap:24px}.wide-card a{display:grid;grid-template-columns:1.5fr 1fr;gap:24px;padding:16px}.wide-card img{width:100%;height:340px;object-fit:contain;background:#eef2f1}nav{display:grid;grid-template-columns:1fr auto 1fr;gap:16px;align-items:center;margin:16px 0}nav span:last-child{text-align:right}.frame-heading{border-bottom:2px solid var(--accent);margin:28px 0}.screenshot{display:block;background:#e5eceb;border:1px solid var(--line);padding:10px}.screenshot img{display:block;width:100%;height:auto}.metadata{display:grid;grid-template-columns:minmax(180px,1fr) 3fr;background:white;border:1px solid var(--line);margin:24px 0}.metadata dt,.metadata dd{padding:11px 14px;margin:0;border-bottom:1px solid var(--line)}.metadata dt{font-weight:700}.metadata dd{overflow-wrap:anywhere}.print-document main{width:100%;padding:0}.print-page{page-break-after:always;width:100%;min-height:180mm;padding:7mm;display:flex;flex-direction:column}.print-cover{justify-content:center;background:var(--deep);color:white;padding:30mm}.print-cover h1{font:700 40px/1 Georgia,serif}.print-layout{display:grid;grid-template-columns:1.7fr 1fr;gap:8mm;min-height:0;flex:1}.print-layout img{width:100%;height:100%;object-fit:contain;border:1px solid var(--line);background:#eef2f1}.print-layout aside{font-size:11px}.print-layout dt{font-weight:700;margin-top:3mm}.print-layout dd{margin:0;overflow-wrap:anywhere}@media(max-width:760px){.cards{grid-template-columns:1fr}.wide-card a{grid-template-columns:1fr}.facts{grid-template-columns:1fr}.metadata{grid-template-columns:1fr}.metadata dd{border-bottom:2px solid var(--line)}}@media print{@page{size:A4 landscape;margin:8mm}body{background:white}.print-page{height:185mm;min-height:185mm}}
CSS;
    }
}
