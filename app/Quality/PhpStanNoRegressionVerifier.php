<?php

namespace App\Quality;

final readonly class PhpStanNoRegressionVerifier
{
    /**
     * @param  array<string, mixed>  $analysis
     * @param  array{reviewed_commit: string, finding_count: int, finding_identities: list<string>}  $baseline
     * @param  list<string>  $newProductionFiles
     * @return array{reviewed_commit: string, baseline_findings: int, current_findings: int, new_findings: list<string>, new_production_file_findings: list<string>, accepted: bool}
     */
    public function verify(array $analysis, array $baseline, array $newProductionFiles): array
    {
        $current = $this->identities($analysis);
        $new = array_values(array_diff($current, $baseline['finding_identities']));
        $newFileFindings = array_values(array_filter($current, function (string $identity) use ($newProductionFiles): bool {
            foreach ($newProductionFiles as $file) {
                if (str_starts_with($identity, $file.'|')) {
                    return true;
                }
            }

            return false;
        }));
        $currentCount = (int) ($analysis['totals']['file_errors'] ?? count($current));

        return ['reviewed_commit' => $baseline['reviewed_commit'], 'baseline_findings' => $baseline['finding_count'], 'current_findings' => $currentCount, 'new_findings' => $new, 'new_production_file_findings' => $newFileFindings, 'accepted' => $currentCount <= $baseline['finding_count'] && $new === [] && $newFileFindings === []];
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return list<string>
     */
    private function identities(array $analysis): array
    {
        $identities = [];
        foreach ($analysis['files'] ?? [] as $path => $file) {
            $relativePath = $this->relativePath((string) $path);
            foreach ($file['messages'] ?? [] as $message) {
                $identities[] = $relativePath.'|'.($message['identifier'] ?? 'unknown').'|'.($message['message'] ?? '');
            }
        }
        sort($identities);

        return $identities;
    }

    private function relativePath(string $path): string
    {
        foreach (['/app/', '/bootstrap/', '/config/', '/database/', '/routes/'] as $root) {
            $position = strpos($path, $root);
            if ($position !== false) {
                return ltrim(substr($path, $position + 1), '/');
            }
        }

        return $path;
    }
}
