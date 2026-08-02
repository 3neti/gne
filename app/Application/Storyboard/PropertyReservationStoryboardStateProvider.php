<?php

namespace App\Application\Storyboard;

use App\Domain\Compilation\BuildResolvedDocumentSet;
use App\Domain\Compilation\CompilationSubject;
use App\Domain\Compilation\DocumentReadiness;
use App\Domain\Repository\ValidateRepository;
use App\Integration\XDocument\ResolveXDocumentBrowserRepresentation;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

final class PropertyReservationStoryboardStateProvider
{
    public const Subject = 'PROPERTY-RESERVATION-MVP-ACCEPTANCE-000001';

    public const SubjectType = 'PropertyReservation';

    public function __construct(
        private readonly Filesystem $files,
        private readonly ValidateRepository $validator,
        private readonly BuildResolvedDocumentSet $sets,
        private readonly ResolveXDocumentBrowserRepresentation $browser,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(string $sourceRoot, string $stage): array
    {
        $root = sys_get_temp_dir().'/gne-storyboard-state-'.Str::uuid();
        try {
            return $this->prepareSnapshot($sourceRoot, $stage, $root);
        } finally {
            $this->files->deleteDirectory($root);
        }
    }

    /** @return array<string, mixed> */
    public function prepareSnapshot(string $sourceRoot, string $stage, string $targetRoot): array
    {
        $this->files->deleteDirectory($targetRoot);
        $this->files->makeDirectory($targetRoot, 0755, true);
        $this->files->copy($sourceRoot.'/GENEI.md', $targetRoot.'/GENEI.md');
        $this->files->copy($sourceRoot.'/gne.yaml', $targetRoot.'/gne.yaml');
        $this->files->copyDirectory($sourceRoot.'/business', $targetRoot.'/business');
        $this->apply($targetRoot, $stage);
        $manifest = $this->validator->handle($targetRoot);
        if ($manifest->hasErrors()) {
            throw new \RuntimeException('Prepared storyboard repository did not validate.');
        }
        $set = $this->sets->handle($targetRoot, $manifest, new CompilationSubject(self::Subject, self::SubjectType));
        $document = $this->documentForStage($stage);
        $entry = collect($set->entries)->firstWhere('definitionIdentifier', $document);
        $browser = $entry?->readiness === DocumentReadiness::Resolved
            ? $this->browser->handle($targetRoot, $document, self::Subject)
            : null;

        return [
            'stage' => $stage,
            'repository_fingerprint' => $manifest->fingerprint,
            'subject' => ['identifier' => self::Subject, 'type' => self::SubjectType],
            'lifecycle' => $set->lifecyclePosition->toArray(),
            'document_set_fingerprint' => $set->fingerprint,
            'documents' => array_map(fn ($item): array => [
                'identifier' => $item->definitionIdentifier,
                'readiness' => $item->readiness->value,
                'missing_evidence' => array_map(fn ($missing): array => $missing->toArray(), $item->missingEvidence),
            ], $set->entries),
            'artifact_identifiers' => collect($manifest->artifacts)
                ->where('subject.identifier', self::Subject)->pluck('identifier')->unique()->sort()->values()->all(),
            'browser' => $browser === null ? null : [
                'document' => $document,
                'checksum' => $browser->output->checksum,
                'etag' => $browser->etag,
                'representation' => $browser->descriptor->representation->value,
            ],
        ];
    }

    private function apply(string $root, string $stage): void
    {
        $this->write($root, 'storyboard-offering-r1.yaml', 'ARTIFACT-PROPERTY-OFFERING-STORYBOARD-000001', 'PropertyOffering', [], ['lot_identifier' => 'LOT-DEMO-101']);
        $this->write($root, 'storyboard-application-r1.yaml', 'ARTIFACT-APPLICATION-STORYBOARD-000001', 'Application', [['relationship' => 'applies_for', 'identifier' => 'ARTIFACT-PROPERTY-OFFERING-STORYBOARD-000001', 'revision' => 1]], ['applicant_alias' => 'Alicia Demonstration', 'lot_identifier' => 'LOT-DEMO-101']);
        if (in_array($stage, ['public', 'application'], true)) {
            return;
        }
        $this->write($root, 'storyboard-assessment-r1.yaml', 'ARTIFACT-ASSESSMENT-STORYBOARD-000001', 'Assessment', [['relationship' => 'assessed_by', 'identifier' => 'ARTIFACT-APPLICATION-STORYBOARD-000001', 'revision' => 1]], ['outcome' => 'approved']);
        if ($stage === 'assessment') {
            return;
        }
        $this->write($root, 'storyboard-invoice-r1.yaml', 'ARTIFACT-INVOICE-STORYBOARD-000001', 'Invoice', [['relationship' => 'produced_by', 'identifier' => 'ARTIFACT-ASSESSMENT-STORYBOARD-000001', 'revision' => 1]], ['amount' => 50000, 'currency' => 'PHP']);
        if ($stage === 'invoice-r1') {
            return;
        }
        $this->write($root, 'storyboard-invoice-r2.yaml', 'ARTIFACT-INVOICE-STORYBOARD-000001', 'Invoice', [['relationship' => 'supersedes', 'identifier' => 'ARTIFACT-INVOICE-STORYBOARD-000001', 'revision' => 1], ['relationship' => 'produced_by', 'identifier' => 'ARTIFACT-ASSESSMENT-STORYBOARD-000001', 'revision' => 1]], ['amount' => 51000, 'currency' => 'PHP', 'correction_reason' => 'Corrected fictional demonstration reservation fee'], 2);
        if ($stage === 'invoice-r2') {
            return;
        }
        $this->write($root, 'storyboard-payment-evidence-r1.yaml', 'ARTIFACT-PAYMENT-EVIDENCE-STORYBOARD-000001', 'PaymentEvidence', [['relationship' => 'payment_evidence_for', 'identifier' => 'ARTIFACT-INVOICE-STORYBOARD-000001', 'revision' => 2]], ['reference' => 'FICTIONAL-MANUAL-PROOF-101', 'amount' => 51000]);
        if ($stage === 'payment-evidence') {
            return;
        }
        $this->write($root, 'storyboard-payment-approval-r1.yaml', 'ARTIFACT-PAYMENT-APPROVAL-STORYBOARD-000001', 'PaymentApproval', [['relationship' => 'approved_by', 'identifier' => 'ARTIFACT-PAYMENT-EVIDENCE-STORYBOARD-000001', 'revision' => 1]], ['outcome' => 'approved', 'reviewer_role' => 'demonstration-payment-reviewer']);
        if ($stage === 'payment-approval') {
            return;
        }
        $this->write($root, 'storyboard-receipt-r1.yaml', 'ARTIFACT-RECEIPT-STORYBOARD-000001', 'Receipt', [['relationship' => 'authorized_by', 'identifier' => 'ARTIFACT-PAYMENT-APPROVAL-STORYBOARD-000001', 'revision' => 1]], ['amount' => 51000, 'currency' => 'PHP']);
        if ($stage === 'receipt') {
            return;
        }
        $this->write($root, 'storyboard-certificate-r1.yaml', 'ARTIFACT-RESERVATION-CERTIFICATE-STORYBOARD-000001', 'ReservationCertificate', [['relationship' => 'supports', 'identifier' => 'ARTIFACT-RECEIPT-STORYBOARD-000001', 'revision' => 1], ['relationship' => 'certifies_application', 'identifier' => 'ARTIFACT-APPLICATION-STORYBOARD-000001', 'revision' => 1]], ['lot_identifier' => 'LOT-DEMO-101']);
    }

    /**
     * @param  list<array<string, mixed>>  $references
     * @param  array<string, mixed>  $payload
     */
    private function write(string $root, string $filename, string $identifier, string $type, array $references, array $payload, int $revision = 1): void
    {
        $this->files->put($root.'/business/profiles/property-reservation/examples/artifacts/'.$filename, Yaml::dump([
            'identifier' => $identifier, 'type' => $type, 'revision' => $revision, 'status' => 'accepted',
            'accepted_at' => sprintf('2026-08-02T%02d:00:00+08:00', 8 + $revision),
            'profile' => 'PROFILE-PROPERTY-RESERVATION', 'scenario' => 'SCENARIO-MANUAL-PAYMENT-RESERVATION',
            'subject' => ['identifier' => self::Subject, 'type' => self::SubjectType],
            'references' => $references, 'payload' => $payload,
            'provenance' => ['source' => 'fictional-storyboard-fixture', 'actor' => 'storyboard-state-provider', 'recorded_by' => 'isolated-repository'],
        ], 8, 2));
    }

    private function documentForStage(string $stage): string
    {
        return match ($stage) {
            'invoice-r1', 'invoice-r2', 'payment-evidence', 'payment-approval' => 'DOCUMENT-INVOICE',
            'receipt' => 'DOCUMENT-RECEIPT',
            'certificate' => 'DOCUMENT-RESERVATION-CERTIFICATE',
            default => 'DOCUMENT-APPLICATION',
        };
    }
}
