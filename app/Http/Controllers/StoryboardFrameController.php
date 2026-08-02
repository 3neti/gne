<?php

namespace App\Http\Controllers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Response;

final class StoryboardFrameController extends Controller
{
    public function __invoke(string $storyboard, string $frame, Filesystem $files): Response
    {
        abort_unless(preg_match('/^[a-z0-9-]+$/', $storyboard) === 1 && preg_match('/^[a-z0-9-]+$/', $frame) === 1, 404);
        $path = base_path(".gne/storyboards/{$storyboard}/manifest.json");
        abort_unless($files->exists($path), 404, 'Generate the storyboard manifest before opening a frame.');
        $manifest = json_decode($files->get($path), true, flags: JSON_THROW_ON_ERROR);
        abort_unless(is_array($manifest) && is_array($manifest['frames'] ?? null), 500, 'Generated storyboard manifest is invalid.');
        $entry = null;
        foreach ($manifest['frames'] as $candidate) {
            if (is_array($candidate) && ($candidate['identifier'] ?? null) === $frame) {
                $entry = $candidate;
                break;
            }
        }
        abort_if($entry === null, 404);
        $snapshot = $entry['snapshot'];
        $documents = implode('', array_map(fn (array $document): string => '<li><strong>'.e($document['identifier']).'</strong> - '.e($document['readiness']).'</li>', $snapshot['documents']));
        $artifacts = implode('', array_map(fn (string $identifier): string => '<li>'.e($identifier).'</li>', $snapshot['artifact_identifiers']));
        $browser = $snapshot['browser'] === null ? 'No resolved browser document for this stage.' : 'Checksum '.e($snapshot['browser']['checksum']).' · ETag '.e($snapshot['browser']['etag']);
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($entry['title']).'</title><style>body{margin:0;background:#eef3f4;color:#132b32;font-family:Inter,ui-sans-serif,system-ui}main{max-width:1180px;margin:0 auto;padding:42px}.eyebrow{color:#9a4b14;font-weight:800;text-transform:uppercase;letter-spacing:.08em}.card{background:#fff;border:1px solid #cbd8dc;border-radius:18px;padding:28px;margin-top:22px;box-shadow:0 18px 50px rgba(26,57,66,.08)}h1{font-size:42px;margin:.25rem 0}h2{font-size:18px}dl{display:grid;grid-template-columns:220px 1fr;gap:10px}dt{font-weight:700}dd{margin:0}code{overflow-wrap:anywhere}.cols{display:grid;grid-template-columns:1fr 1fr;gap:20px}footer{margin-top:28px;color:#526b72}</style></head><body><main data-storyboard-frame="'.e($entry['identifier']).'"><p class="eyebrow">'.e($entry['act']).' · frame '.e((string) $entry['sequence']).'</p><h1>'.e($entry['title']).'</h1><p>'.e($entry['action']).'</p><section class="card"><dl><dt>Persona</dt><dd>'.e($entry['persona']).'</dd><dt>Business route observed</dt><dd><code>'.e($entry['route']).'</code></dd><dt>Expected visible state</dt><dd>'.e($entry['expected']).'</dd><dt>Subject</dt><dd>'.e($snapshot['subject']['identifier']).'</dd><dt>Lifecycle</dt><dd>'.e($snapshot['lifecycle']['current_stage'] ?? 'not started').'</dd><dt>Next stage</dt><dd>'.e($snapshot['lifecycle']['next_stage'] ?? 'complete').'</dd><dt>Browser evidence</dt><dd>'.$browser.'</dd></dl></section><div class="cols"><section class="card"><h2>Document readiness</h2><ul>'.$documents.'</ul></section><section class="card"><h2>Accepted immutable evidence</h2><ul>'.$artifacts.'</ul></section></div><footer>Repository fingerprint <code>'.e($snapshot['repository_fingerprint']).'</code><br>Storyboard observation only - it does not author or mutate business truth.</footer></main></body></html>';

        return response($html)->header('Content-Type', 'text/html; charset=utf-8')->header('Cache-Control', 'no-store');
    }
}
