<?php

namespace App\Console\Commands;

use App\Application\Storyboard\BuildStoryboard;
use App\Application\Storyboard\LoadStoryboardDefinition;
use App\Infrastructure\Storyboard\CaptureStoryboard;
use App\Infrastructure\Storyboard\StoryboardPdfRenderer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

#[Signature('gne:storyboard {storyboard} {--capture : Capture authenticated browser frames} {--json : Emit a machine-readable report} {--base-url= : Override APP_URL for capture}')]
#[Description('Build a deterministic repository-derived demonstration storyboard')]
final class GneStoryboardCommand extends Command
{
    public function handle(LoadStoryboardDefinition $loader, BuildStoryboard $builder, StoryboardPdfRenderer $pdfs, CaptureStoryboard $capture, Filesystem $files): int
    {
        $identifier = (string) $this->argument('storyboard');
        $baseUrl = rtrim((string) ($this->option('base-url') ?: config('app.url')), '/');
        $definition = $loader->handle(base_path(), $identifier);
        $manifest = $builder->handle(base_path(), $definition, $baseUrl);
        $root = base_path('.gne/storyboards/'.$identifier);
        $pdfPath = $root.'/pdf/'.$identifier.'.pdf';
        $files->put($pdfPath, $pdfs->render($manifest));
        $manifest['outputs']['pdf'] = ['path' => 'pdf/'.$identifier.'.pdf', 'status' => 'generated', 'pages' => count($manifest['frames']) + 1, 'sha256' => hash_file('sha256', $pdfPath)];

        $captureStatus = 'not_requested';
        if ($this->option('capture')) {
            $captureStatus = $capture->handle($this, $baseUrl, $root, $manifest);
            if ($captureStatus === 'captured') {
                $manifest['outputs']['pdf'] = ['path' => 'pdf/'.$identifier.'.pdf', 'status' => 'generated', 'pages' => count($manifest['frames']) + 1, 'sha256' => hash_file('sha256', $pdfPath)];
            }
        }
        $manifest['capture_status'] = $captureStatus;
        $builder->persist(base_path(), $manifest);
        $screenshotCount = 0;
        foreach ($manifest['frames'] as $frame) {
            if ($frame['capture_status'] === 'captured') {
                $screenshotCount++;
            }
        }
        $report = [
            'passed' => ! $this->option('capture') || $captureStatus === 'captured',
            'storyboard' => $identifier,
            'base_url' => $baseUrl,
            'frame_count' => count($manifest['frames']),
            'screenshot_count' => $screenshotCount,
            'capture_status' => $captureStatus,
            'manifest' => $root.'/manifest.json',
            'pdf' => $pdfPath,
            'pdf_pages' => count($manifest['frames']) + 1,
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
            $this->line('PDF: '.$pdfPath);
            $this->line('Movie: build-ready manifest (FFmpeg optional)');
        }

        return $report['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
