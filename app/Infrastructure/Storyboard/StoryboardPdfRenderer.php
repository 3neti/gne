<?php

namespace App\Infrastructure\Storyboard;

final class StoryboardPdfRenderer
{
    /** @param array<string, mixed> $manifest */
    public function render(array $manifest): string
    {
        $pages = [$this->page($manifest['title'], ['Repository-native demonstration projection', 'Subject: '.$manifest['subject_identifier'], 'Frames: '.count($manifest['frames'])])];
        foreach ($manifest['frames'] as $frame) {
            $lifecycle = $frame['snapshot']['lifecycle'];
            $pages[] = $this->page($frame['sequence'].'. '.$frame['title'], [
                'Act: '.$frame['act'], 'Persona: '.$frame['persona'], 'Route: '.$frame['route'],
                'Action: '.$frame['action'], 'Expected: '.$frame['expected'],
                'Lifecycle: '.($lifecycle['current_stage'] ?? 'not started'),
                'Next: '.($lifecycle['next_stage'] ?? 'complete'),
                'Repository fingerprint: '.$frame['snapshot']['repository_fingerprint'],
                'Capture: '.$frame['capture_status'].' - '.$frame['capture_filename'],
            ]);
        }

        return $this->document($pages);
    }

    /** @param list<string> $lines */
    private function page(string $title, array $lines): string
    {
        $commands = ['0.96 0.98 1 rg', '0 0 842 595 re', 'f', '0.08 0.25 0.35 rg', '0 520 842 75 re', 'f', '1 1 1 rg', 'BT', '/F2 22 Tf', '42 552 Td', '('.$this->escape($title).') Tj', 'ET', '0.1 0.15 0.2 rg', 'BT', '/F1 11 Tf', '42 482 Td'];
        foreach ($lines as $line) {
            foreach (explode("\n", wordwrap($line, 110, "\n", true)) as $wrapped) {
                $commands[] = '('.$this->escape($wrapped).') Tj';
                $commands[] = '0 -18 Td';
            }
        }
        $commands[] = 'ET';

        return implode("\n", $commands);
    }

    /** @param list<string> $contents */
    private function document(array $contents): string
    {
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', 4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>'];
        $kids = [];
        $next = 5;
        foreach ($contents as $content) {
            $page = $next++;
            $stream = $next++;
            $kids[] = "{$page} 0 R";
            $objects[$page] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$stream} 0 R >>";
            $objects[$stream] = '<< /Length '.strlen($content).">>\nstream\n{$content}\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($number = 1; $number <= count($objects); $number++) {
            $pdf .= str_pad((string) $offsets[$number], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n", '₱'], ['\\\\', '\\(', '\\)', ' ', ' ', 'PHP '], $value);
    }
}
