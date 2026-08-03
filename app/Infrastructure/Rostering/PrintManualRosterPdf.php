<?php

namespace App\Infrastructure\Rostering;

use Illuminate\Support\Facades\Process;
use RuntimeException;

final class PrintManualRosterPdf
{
    /** @return array{path: string, pages: int, byte_length: int, sha256: string} */
    public function handle(string $root): array
    {
        $source = $root.'/html/print.html';
        $output = $root.'/pdf/anaesthesia-manual-roster.pdf';
        if (! is_file($source)) {
            throw new RuntimeException('Final manual roster report HTML is missing.');
        }
        if (! is_dir(dirname($output))) {
            mkdir(dirname($output), 0775, true);
        }
        $result = Process::timeout(180)->run(['node', base_path('scripts/gne-storyboard-print.mjs'), $source, $output]);
        if (! $result->successful() || ! is_file($output)) {
            throw new RuntimeException(trim($result->errorOutput() ?: $result->output()) ?: 'Manual roster PDF generation failed.');
        }
        $info = Process::run(['pdfinfo', $output]);
        preg_match('/Pages:\s+(\d+)/', $info->output(), $matches);

        return ['path' => 'pdf/anaesthesia-manual-roster.pdf', 'pages' => (int) ($matches[1] ?? 0), 'byte_length' => filesize($output) ?: 0, 'sha256' => hash_file('sha256', $output) ?: ''];
    }
}
