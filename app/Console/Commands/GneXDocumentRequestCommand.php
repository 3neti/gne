<?php

namespace App\Console\Commands;

use App\Domain\Compilation\CompilationSubject;
use App\Domain\Compilation\CompilationSubjectNotFound;
use App\Domain\Compilation\DocumentDefinitionNotFound;
use App\Domain\Compilation\DocumentEvidenceNotFound;
use App\Domain\Compilation\DocumentResolutionRequest;
use App\Domain\Compilation\ResolveDocument;
use App\Domain\Repository\ValidateRepository;
use App\Integration\XDocument\PrepareXDocumentCompilationRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gne:x-document:request {--repository= : Repository root} {--document= : Document definition identifier} {--subject= : Compilation subject identifier} {--driver=json : Requested future x-document driver} {--include-evidence=1 : Include descriptive provenance} {--json : Emit the complete request JSON}')]
#[Description('Prepare and inspect a versioned x-document contract request without invoking x-document')]
class GneXDocumentRequestCommand extends Command
{
    public function handle(ValidateRepository $validator, ResolveDocument $resolver, PrepareXDocumentCompilationRequest $adapter): int
    {
        $repositoryRoot = is_string($this->option('repository')) ? $this->option('repository') : base_path();
        $documentIdentifier = $this->option('document');
        $subjectIdentifier = $this->option('subject');
        $driver = $this->option('driver');
        if (! is_string($documentIdentifier) || ! is_string($subjectIdentifier) || ! is_string($driver)) {
            $this->components->error('--document and --subject are required.');

            return self::FAILURE;
        }
        $manifest = $validator->handle($repositoryRoot);
        if ($manifest->hasErrors()) {
            $this->components->error('Request preparation stopped because repository validation failed.');

            return self::FAILURE;
        }
        $artifact = collect($manifest->artifacts)->first(fn (array $item): bool => ($item['subject']['identifier'] ?? null) === $subjectIdentifier);
        if (! is_array($artifact) || ! is_string($artifact['subject']['type'] ?? null)) {
            $this->components->error("Compilation subject {$subjectIdentifier} was not found.");

            return self::FAILURE;
        }
        try {
            $resolved = $resolver->handle($repositoryRoot, $manifest, new DocumentResolutionRequest($documentIdentifier, new CompilationSubject($subjectIdentifier, $artifact['subject']['type'])));
        } catch (DocumentDefinitionNotFound|CompilationSubjectNotFound|DocumentEvidenceNotFound $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $request = $adapter->handle($resolved, $driver, filter_var($this->option('include-evidence'), FILTER_VALIDATE_BOOL));
        if ($this->option('json')) {
            $this->line(rtrim($request->toJson()));

            return self::SUCCESS;
        }
        $this->components->info('Prepared x-document contract request.');
        $this->line("Contract version: {$request->contractVersion}");
        $this->line("Document: {$request->document->identifier}");
        $this->line("Subject: {$request->document->subject->identifier}");
        $this->line("Requested driver: {$request->requestedDriver}");
        $this->line("Request fingerprint: {$request->requestFingerprint}");
        $this->warn('Inspection only: x-document is not installed or invoked.');

        return self::SUCCESS;
    }
}
