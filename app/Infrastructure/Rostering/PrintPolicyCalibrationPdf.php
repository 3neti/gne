<?php

namespace App\Infrastructure\Rostering;

use Illuminate\Support\Facades\Process;
use RuntimeException;

final class PrintPolicyCalibrationPdf
{
    /** @return array{path: string, pages: int, byte_length: int, sha256: string} */
    public function handle(string $root): array
    {
        $output = $root.'/pdf/anaesthesia-generation-policy-calibration.pdf';
        if (! is_dir(dirname($output))) {
            mkdir(dirname($output), 0775, true);
        }
        $result = Process::timeout(180)->run(['node', base_path('scripts/gne-storyboard-print.mjs'), $root.'/html/print.html', $output]);
        if (! $result->successful() || ! is_file($output)) {
            throw new RuntimeException('Policy calibration PDF generation failed.');
        }
        $info = Process::run(['pdfinfo', $output]);
        preg_match('/Pages:\s+(\d+)/', $info->output(), $matches);

        return ['path' => 'pdf/anaesthesia-generation-policy-calibration.pdf', 'pages' => (int) ($matches[1] ?? 0), 'byte_length' => filesize($output) ?: 0, 'sha256' => hash_file('sha256', $output) ?: ''];
    }
}
