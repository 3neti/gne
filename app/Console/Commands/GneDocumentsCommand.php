<?php

namespace App\Console\Commands;

use App\Domain\Compilation\BuildResolvedDocumentSet;
use App\Domain\Compilation\CompilationSubject;
use App\Domain\Repository\ValidateRepository;
use Illuminate\Console\Command;

class GneDocumentsCommand extends Command
{
    protected $signature = 'gne:documents {--repository= : Repository root (defaults to the application root)} {--subject= : Limit inventory to one compilation subject} {--json : Emit structured JSON}';

    protected $description = 'Explain document readiness and lifecycle position for compilation subjects';

    public function handle(ValidateRepository $validator, BuildResolvedDocumentSet $builder): int
    {
        $repositoryRoot = is_string($this->option('repository')) ? $this->option('repository') : base_path();
        $manifest = $validator->handle($repositoryRoot);
        if ($manifest->hasErrors()) {
            $this->components->error('Document inventory stopped because validation failed.');

            return self::FAILURE;
        }
        $requestedSubject = $this->option('subject');
        $subjectsByIdentifier = [];
        foreach ($manifest->artifacts as $artifact) {
            if (is_array($artifact['subject'] ?? null)) {
                $subjectsByIdentifier[$artifact['subject']['identifier']] = $artifact['subject'];
            }
        }
        ksort($subjectsByIdentifier);
        $subjects = array_values($subjectsByIdentifier);
        if (is_string($requestedSubject)) {
            $subjects = array_values(array_filter($subjects, fn (array $subject): bool => $subject['identifier'] === $requestedSubject));
        }
        if ($subjects === []) {
            $this->components->error('The requested compilation subject was not found.');

            return self::FAILURE;
        }
        $sets = array_map(fn (array $subject): array => $builder->handle($repositoryRoot, $manifest, new CompilationSubject($subject['identifier'], $subject['type']))->toArray(), $subjects);
        if ($this->option('json')) {
            $this->line(json_encode(['valid' => true, 'document_sets' => $sets], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }
        foreach ($sets as $set) {
            $this->components->info("Compilation Subject: {$set['compilation_subject']['identifier']} ({$set['compilation_subject']['type']})");
            $this->line('Lifecycle current: '.($set['lifecycle']['current_stage'] ?? 'not started'));
            $this->line('Lifecycle next: '.($set['lifecycle']['next_stage'] ?? 'complete'));
            foreach ($set['entries'] as $entry) {
                $missing = implode(', ', array_column($entry['missing_evidence'], 'artifact_type'));
                $suffix = $missing !== '' ? " — missing {$missing}" : '';
                $this->line(strtoupper($entry['readiness'])."\t{$entry['title']}{$suffix}");
            }
        }

        return self::SUCCESS;
    }
}
