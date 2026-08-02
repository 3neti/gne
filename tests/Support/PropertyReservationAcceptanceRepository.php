<?php

namespace Tests\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\Yaml\Yaml;

final class PropertyReservationAcceptanceRepository
{
    public const SubjectIdentifier = 'PROPERTY-RESERVATION-MVP-ACCEPTANCE-000001';

    public const SubjectType = 'PropertyReservation';

    public const Applicant = 'Alicia Demonstration';

    public const Lot = 'LOT-DEMO-101';

    private const Profile = 'PROFILE-PROPERTY-RESERVATION';

    private const Scenario = 'SCENARIO-MANUAL-PAYMENT-RESERVATION';

    private readonly Filesystem $files;

    private function __construct(public readonly string $root)
    {
        $this->files = new Filesystem;
    }

    public static function create(string $sourceRoot): self
    {
        $files = new Filesystem;
        $root = sys_get_temp_dir().'/gne-property-reservation-acceptance-'.Str::uuid();
        $files->makeDirectory($root, 0755, true);
        $files->copy($sourceRoot.'/GENEI.md', $root.'/GENEI.md');
        $files->copy($sourceRoot.'/gne.yaml', $root.'/gne.yaml');
        $files->copyDirectory($sourceRoot.'/business', $root.'/business');

        return new self($root);
    }

    public function begin(): void
    {
        $this->writeArtifact('acceptance-offering-r1.yaml', [
            ...$this->identity('ARTIFACT-PROPERTY-OFFERING-ACCEPTANCE-000001', 'PropertyOffering'),
            'references' => [],
            'payload' => ['lot_identifier' => self::Lot],
        ]);
        $this->writeArtifact('acceptance-application-r1.yaml', [
            ...$this->identity('ARTIFACT-APPLICATION-ACCEPTANCE-000001', 'Application'),
            'references' => [
                ['relationship' => 'applies_for', 'identifier' => 'ARTIFACT-PROPERTY-OFFERING-ACCEPTANCE-000001', 'revision' => 1],
            ],
            'payload' => ['applicant_alias' => self::Applicant, 'lot_identifier' => self::Lot],
        ]);
    }

    public function acceptAssessment(): void
    {
        $this->writeArtifact('acceptance-assessment-r1.yaml', [
            ...$this->identity('ARTIFACT-ASSESSMENT-ACCEPTANCE-000001', 'Assessment'),
            'references' => [
                ['relationship' => 'assessed_by', 'identifier' => 'ARTIFACT-APPLICATION-ACCEPTANCE-000001', 'revision' => 1],
            ],
            'payload' => ['outcome' => 'approved'],
        ]);
    }

    public function acceptInvoiceRevision(int $revision, int $amount): void
    {
        $references = [
            ['relationship' => 'produced_by', 'identifier' => 'ARTIFACT-ASSESSMENT-ACCEPTANCE-000001', 'revision' => 1],
        ];
        if ($revision > 1) {
            array_unshift($references, ['relationship' => 'supersedes', 'identifier' => 'ARTIFACT-INVOICE-ACCEPTANCE-000001', 'revision' => $revision - 1]);
        }
        $payload = ['amount' => $amount, 'currency' => 'PHP'];
        if ($revision > 1) {
            $payload['correction_reason'] = 'Corrected fictional demonstration reservation fee';
        }
        $this->writeArtifact("acceptance-invoice-r{$revision}.yaml", [
            ...$this->identity('ARTIFACT-INVOICE-ACCEPTANCE-000001', 'Invoice', $revision),
            'references' => $references,
            'payload' => $payload,
        ]);
    }

    public function submitPaymentEvidence(): void
    {
        $this->writeArtifact('acceptance-payment-evidence-r1.yaml', [
            ...$this->identity('ARTIFACT-PAYMENT-EVIDENCE-ACCEPTANCE-000001', 'PaymentEvidence'),
            'references' => [
                ['relationship' => 'payment_evidence_for', 'identifier' => 'ARTIFACT-INVOICE-ACCEPTANCE-000001', 'revision' => 2],
            ],
            'payload' => ['reference' => 'FICTIONAL-MANUAL-PROOF-101', 'amount' => 51000],
        ]);
    }

    public function acceptPaymentApproval(): void
    {
        $this->writeArtifact('acceptance-payment-approval-r1.yaml', [
            ...$this->identity('ARTIFACT-PAYMENT-APPROVAL-ACCEPTANCE-000001', 'PaymentApproval'),
            'references' => [
                ['relationship' => 'approved_by', 'identifier' => 'ARTIFACT-PAYMENT-EVIDENCE-ACCEPTANCE-000001', 'revision' => 1],
            ],
            'payload' => ['outcome' => 'approved', 'reviewer_role' => 'demonstration-payment-reviewer'],
        ]);
    }

    public function acceptReceipt(): void
    {
        $this->writeArtifact('acceptance-receipt-r1.yaml', [
            ...$this->identity('ARTIFACT-RECEIPT-ACCEPTANCE-000001', 'Receipt'),
            'references' => [
                ['relationship' => 'authorized_by', 'identifier' => 'ARTIFACT-PAYMENT-APPROVAL-ACCEPTANCE-000001', 'revision' => 1],
            ],
            'payload' => ['amount' => 51000, 'currency' => 'PHP'],
        ]);
    }

    public function acceptReservationCertificate(): void
    {
        $this->writeArtifact('acceptance-reservation-certificate-r1.yaml', [
            ...$this->identity('ARTIFACT-RESERVATION-CERTIFICATE-ACCEPTANCE-000001', 'ReservationCertificate'),
            'references' => [
                ['relationship' => 'supports', 'identifier' => 'ARTIFACT-RECEIPT-ACCEPTANCE-000001', 'revision' => 1],
                ['relationship' => 'certifies_application', 'identifier' => 'ARTIFACT-APPLICATION-ACCEPTANCE-000001', 'revision' => 1],
            ],
            'payload' => ['lot_identifier' => self::Lot],
        ]);
    }

    /** @param array<string, mixed> $artifact */
    public function writeArtifact(string $filename, array $artifact): string
    {
        $path = $this->artifactPath($filename);
        if ($this->files->exists($path)) {
            throw new LogicException("Acceptance artifact already exists: {$filename}");
        }

        $this->files->put($path, Yaml::dump([
            ...$artifact,
            'provenance' => [
                'source' => 'fictional-mvp-acceptance-fixture',
                'actor' => 'acceptance-test',
                'recorded_by' => 'isolated-repository',
            ],
        ], 8, 2), true);

        return $path;
    }

    public function artifactPath(string $filename): string
    {
        return $this->root.'/business/profiles/property-reservation/examples/artifacts/'.$filename;
    }

    public function checksum(string $filename): string
    {
        return hash_file('sha256', $this->artifactPath($filename));
    }

    public function duplicate(string $sourceFilename, string $duplicateFilename): void
    {
        $this->files->copy($this->artifactPath($sourceFilename), $this->artifactPath($duplicateFilename));
    }

    public function cleanup(): void
    {
        if ($this->files->isDirectory($this->root)) {
            $this->files->deleteDirectory($this->root);
        }
    }

    /** @return array<string, mixed> */
    private function identity(string $identifier, string $type, int $revision = 1): array
    {
        return [
            'identifier' => $identifier,
            'type' => $type,
            'revision' => $revision,
            'status' => 'accepted',
            'accepted_at' => sprintf('2026-08-02T%02d:00:00+08:00', min(8 + $revision, 23)),
            'profile' => self::Profile,
            'scenario' => self::Scenario,
            'subject' => ['identifier' => self::SubjectIdentifier, 'type' => self::SubjectType],
        ];
    }
}
