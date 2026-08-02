<?php

namespace App\Infrastructure\Storyboard;

use Illuminate\Support\Facades\Process;

final class PrintStoryboardPdf
{
    /** @return array{path: string, pages: int, screenshot_frames: int, byte_length: int, sha256: string, source_html_sha256: string} */
    public function handle(string $root, string $identifier, int $frameCount): array
    {
        $script = base_path('scripts/gne-storyboard-print.mjs');
        $source = $root.'/html/print.html';
        $output = $root.'/pdf/'.$identifier.'.pdf';
        if (! is_file($source)) {
            throw new StoryboardArtifactException('Final storyboard HTML print source does not exist.');
        }
        $result = Process::timeout(180)->run(['node', $script, $source, $output]);
        if (! $result->successful() || ! is_file($output)) {
            throw new StoryboardArtifactException(trim($result->errorOutput() ?: $result->output()) ?: 'Chromium PDF generation failed.');
        }
        $byteLength = filesize($output);
        $sha256 = hash_file('sha256', $output);
        $sourceSha256 = hash_file('sha256', $source);
        if ($byteLength === false || $sha256 === false || $sourceSha256 === false) {
            throw new StoryboardArtifactException('Generated storyboard PDF metadata could not be read.');
        }

        return [
            'path' => 'pdf/'.$identifier.'.pdf',
            'pages' => $frameCount + 1,
            'screenshot_frames' => $frameCount,
            'byte_length' => $byteLength,
            'sha256' => $sha256,
            'source_html_sha256' => $sourceSha256,
        ];
    }
}
