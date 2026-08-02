<?php

namespace App\Console\Commands;

use App\Application\Storyboard\BuildStoryboard;
use App\Application\Storyboard\LoadStoryboardDefinition;
use App\Infrastructure\Storyboard\CaptureStoryboard;
use App\Infrastructure\Storyboard\PrintStoryboardPdf;
use App\Infrastructure\Storyboard\StoryboardHtmlRenderer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

#[Signature('gne:storyboard {storyboard} {--capture : Capture authenticated browser frames} {--json : Emit a machine-readable report} {--base-url= : Override APP_URL for capture}')]
#[Description('Build a deterministic repository-derived demonstration storyboard')]
final class GneStoryboardCommand extends Command
{
    public function handle(
        LoadStoryboardDefinition $loader,
        BuildStoryboard $builder,
        StoryboardHtmlRenderer $html,
        PrintStoryboardPdf $pdfs,
        CaptureStoryboard $capture,
        Filesystem $files,
    ): int {
        $identifier = (string) $this->argument('storyboard');
        $baseUrl = rtrim((string) ($this->option('base-url') ?: config('app.url')), '/');
        $definition = $loader->handle(base_path(), $identifier);
        $manifest = $builder->handle(base_path(), $definition, $baseUrl);
        $root = base_path('.gne/storyboards/'.$identifier);
        $pdfPath = $root.'/pdf/'.$identifier.'.pdf';
        $files->delete($pdfPath);

        $captureStatus = 'not_requested';
        if ($this->option('capture')) {
            $captureStatus = $capture->handle($this, $baseUrl, $root, $manifest);
            if ($captureStatus === 'captured_and_verified') {
                $manifest['capture_status'] = $captureStatus;
                $manifest['finalized_frame_fingerprint'] = $this->frameFingerprint($manifest['frames']);
                $builder->persist(base_path(), $manifest);
                $manifest['outputs']['html'] = $html->render($root, $manifest);
                $manifest['outputs']['pdf'] = ['status' => 'generated', ...$pdfs->handle($root, $identifier, count($manifest['frames']))];
                $builder->finalizeMovie(base_path(), $manifest);
            }
        }
        $manifest['capture_status'] = $captureStatus;
        $builder->persist(base_path(), $manifest);
        $screenshotCount = 0;
        foreach ($manifest['frames'] as $frame) {
            if ($frame['capture_status'] === 'captured_and_verified') {
                $screenshotCount++;
            }
        }
        $report = [
            'passed' => ! $this->option('capture') || $captureStatus === 'captured_and_verified',
            'storyboard' => $identifier,
            'base_url' => $baseUrl,
            'frame_count' => count($manifest['frames']),
            'screenshot_count' => $screenshotCount,
            'capture_status' => $captureStatus,
            'authentication' => $manifest['authentication'] ?? null,
            'manifest' => $root.'/manifest.json',
            'html' => $manifest['outputs']['html']['entrypoint'] ?? null,
            'html_pages' => $manifest['outputs']['html']['page_count'] ?? 0,
            'html_aggregate_fingerprint' => $manifest['outputs']['html']['aggregate_fingerprint'] ?? null,
            'pdf' => is_file($pdfPath) ? $pdfPath : null,
            'pdf_pages' => $manifest['outputs']['pdf']['pages'] ?? 0,
            'pdf_screenshot_frames' => $manifest['outputs']['pdf']['screenshot_frames'] ?? 0,
            'pdf_sha256' => $manifest['outputs']['pdf']['sha256'] ?? null,
            'movie_status' => 'build_ready',
            'movie_manifest' => $root.'/movie/manifest.json',
            'narration' => $root.'/narration.md',
        ];
        $files->put($root.'/reports/capture-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->components->info('Property Reservation storyboard prepared.');
            $this->line('Frames: '.$report['frame_count']);
            $this->line('Screenshots: '.$report['screenshot_count']);
            $this->line('Capture: '.$captureStatus);
            $this->line('HTML: '.($report['html'] ?? 'requires --capture'));
            $this->line('PDF: '.($report['pdf'] ?? 'requires --capture'));
            $this->line('Movie: build-ready manifest (FFmpeg optional)');
        }

        return $report['passed'] ? self::SUCCESS : self::FAILURE;
    }

    /** @param list<array<string, mixed>> $frames */
    private function frameFingerprint(array $frames): string
    {
        $inventory = array_map(fn (array $frame): array => [
            'sequence' => $frame['sequence'],
            'identifier' => $frame['identifier'],
            'capture_filename' => $frame['capture_filename'],
            'capture_status' => $frame['capture_status'],
            'capture_checksum' => $frame['capture_checksum'],
            'capture_byte_length' => $frame['capture_byte_length'],
            'snapshot' => $frame['snapshot'],
            'duration_seconds' => $frame['duration_seconds'],
            'transition' => $frame['transition'],
        ], $frames);

        return hash('sha256', json_encode($inventory, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
