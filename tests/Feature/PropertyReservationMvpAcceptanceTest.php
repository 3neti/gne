<?php

use App\Application\Authorization\FindCompilationSubject;
use App\Application\Authorization\GrantSubjectAccess;
use App\Domain\Authorization\SubjectPermission;
use App\Domain\Compilation\AmbiguousArtifactSelection;
use App\Domain\Compilation\BuildResolvedDocumentSet;
use App\Domain\Compilation\CompilationSubject;
use App\Domain\Compilation\DocumentReadiness;
use App\Domain\Compilation\SelectArtifactChain;
use App\Domain\Repository\ValidateRepository;
use App\Integration\XDocument\BrowserDocumentRepresentationResolver;
use App\Integration\XDocument\ResolveXDocumentBrowserRepresentation;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use LBHurtado\XDocument\Browser\Host\BrowserHostResponse;
use LBHurtado\XDocument\Browser\Host\BrowserRepresentation;
use Tests\Support\PropertyReservationAcceptanceRepository;
use Tests\Support\PropertyReservationAcceptanceSnapshot;

function propertyReservationAcceptanceSnapshot(
    PropertyReservationAcceptanceRepository $repository,
    string $stage,
    ?string $browserDocument = null,
): PropertyReservationAcceptanceSnapshot {
    $manifest = app(ValidateRepository::class)->handle($repository->root);
    expect($manifest->hasErrors())->toBeFalse();
    $subject = new CompilationSubject(
        PropertyReservationAcceptanceRepository::SubjectIdentifier,
        PropertyReservationAcceptanceRepository::SubjectType,
    );
    $chain = app(SelectArtifactChain::class)->handle(
        $manifest,
        $subject,
        'PROFILE-PROPERTY-RESERVATION',
        'SCENARIO-MANUAL-PAYMENT-RESERVATION',
    );
    $set = app(BuildResolvedDocumentSet::class)->handle($repository->root, $manifest, $subject);
    $resolvedDocuments = collect($set->entries)
        ->filter(fn ($entry): bool => $entry->readiness === DocumentReadiness::Resolved)
        ->pluck('definitionIdentifier')
        ->values()
        ->all();
    $pendingDocuments = collect($set->entries)
        ->filter(fn ($entry): bool => $entry->readiness === DocumentReadiness::Pending)
        ->mapWithKeys(fn ($entry): array => [
            $entry->definitionIdentifier => collect($entry->missingEvidence)->pluck('artifactType')->values()->all(),
        ])
        ->all();
    $browserResponse = $browserDocument === null
        ? null
        : app(ResolveXDocumentBrowserRepresentation::class)->handle(
            $repository->root,
            $browserDocument,
            PropertyReservationAcceptanceRepository::SubjectIdentifier,
        );
    $browserIdentifier = $browserDocument === null
        ? null
        : collect($set->entries)->firstWhere('definitionIdentifier', $browserDocument)?->resolvedDocument?->identifier;

    return new PropertyReservationAcceptanceSnapshot(
        $stage,
        $manifest->fingerprint,
        $subject->identifier,
        collect($chain->artifacts)->mapWithKeys(
            fn (array $artifact): array => [$artifact['identifier'] => $artifact['revision']],
        )->all(),
        $set->lifecyclePosition->currentStage,
        $set->lifecyclePosition->nextStage,
        $set->lifecyclePosition->gaps,
        $set->fingerprint,
        $resolvedDocuments,
        $pendingDocuments,
        $browserIdentifier,
        $browserResponse?->output->checksum,
        $browserResponse?->etag,
    );
}

function bindAcceptanceBrowserRepository(PropertyReservationAcceptanceRepository $repository): void
{
    $delegate = app(ResolveXDocumentBrowserRepresentation::class);
    app()->instance(BrowserDocumentRepresentationResolver::class, new class($delegate, $repository->root) implements BrowserDocumentRepresentationResolver
    {
        public function __construct(
            private readonly ResolveXDocumentBrowserRepresentation $delegate,
            private readonly string $repositoryRoot,
        ) {}

        public function handle(
            string $repositoryRoot,
            string $documentIdentifier,
            string $subjectIdentifier,
            BrowserRepresentation $representation = BrowserRepresentation::CompositionStyledHtml,
        ): BrowserHostResponse {
            return $this->delegate->handle(
                $this->repositoryRoot,
                $documentIdentifier,
                $subjectIdentifier,
                $representation,
            );
        }
    });
    app()->instance(FindCompilationSubject::class, new class(app(ValidateRepository::class)) extends FindCompilationSubject
    {
        public function handle(string $repositoryRoot, string $subjectIdentifier): CompilationSubject
        {
            return new CompilationSubject($subjectIdentifier, PropertyReservationAcceptanceRepository::SubjectType);
        }
    });
}

function acceptanceBrowserUrl(string $document, string $subject = PropertyReservationAcceptanceRepository::SubjectIdentifier): string
{
    return route('documents.browser', ['subject' => $subject, 'document' => $document]);
}

function canonicalBusinessChecksum(string $root): string
{
    $files = collect((new Filesystem)->allFiles($root.'/business'))
        ->sortBy(fn (SplFileInfo $file): string => $file->getRelativePathname())
        ->map(fn (SplFileInfo $file): string => $file->getRelativePathname().'\0'.$file->getContents());

    return hash('sha256', $files->implode("\0"));
}

it('proves the complete immutable Property Reservation lifecycle and browser refresh contract', function () {
    $canonicalChecksumBefore = canonicalBusinessChecksum(base_path());
    $repository = PropertyReservationAcceptanceRepository::create(base_path());

    try {
        $repository->begin();
        bindAcceptanceBrowserRepository($repository);
        $user = User::factory()->create(['email_verified_at' => now()]);
        app(GrantSubjectAccess::class)->handle(
            $user,
            new CompilationSubject(
                PropertyReservationAcceptanceRepository::SubjectIdentifier,
                PropertyReservationAcceptanceRepository::SubjectType,
            ),
            SubjectPermission::View,
        );

        $applicationChecksum = $repository->checksum('acceptance-application-r1.yaml');
        $stageA = propertyReservationAcceptanceSnapshot($repository, 'application_submitted', 'DOCUMENT-APPLICATION');
        expect($stageA->lifecycleStage)->toBe('application_accepted')
            ->and($stageA->nextLifecycleStage)->toBe('assessment_completed')
            ->and($stageA->lifecycleGaps)->toBe([])
            ->and($stageA->resolvedDocuments)->toBe([
                'DOCUMENT-APPLICATION',
                'DOCUMENT-BROCHURE',
            ])
            ->and($stageA->pendingDocuments)->toBe([
                'DOCUMENT-INVOICE' => ['Invoice'],
                'DOCUMENT-RECEIPT' => ['PaymentApproval'],
                'DOCUMENT-RESERVATION-CERTIFICATE' => ['Receipt'],
            ]);

        $application = $this->actingAs($user)->get(acceptanceBrowserUrl('DOCUMENT-APPLICATION'));
        $application->assertSuccessful()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee(PropertyReservationAcceptanceRepository::Applicant, escape: false)
            ->assertSee(PropertyReservationAcceptanceRepository::Lot, escape: false)
            ->assertDontSee('FICTIONAL-MANUAL-PROOF-101', escape: false);
        $applicationEtag = $application->headers->get('ETag');
        expect($applicationEtag)->toBe($stageA->browserEtag)
            ->and($application->getContent())->toBe(
                app(ResolveXDocumentBrowserRepresentation::class)->handle(
                    $repository->root,
                    'DOCUMENT-APPLICATION',
                    PropertyReservationAcceptanceRepository::SubjectIdentifier,
                )->output->inlineContent,
            );
        $this->actingAs($user)->withHeader('If-None-Match', $applicationEtag)->get(acceptanceBrowserUrl('DOCUMENT-APPLICATION'))->assertNotModified();
        $this->actingAs($user)->withHeader('If-None-Match', 'W/'.$applicationEtag)->get(acceptanceBrowserUrl('DOCUMENT-APPLICATION'))->assertNotModified();
        $this->actingAs($user)->get(acceptanceBrowserUrl('DOCUMENT-INVOICE'))->assertUnprocessable();

        $repository->acceptAssessment();
        $stageB = propertyReservationAcceptanceSnapshot($repository, 'assessment_accepted', 'DOCUMENT-APPLICATION');
        expect($stageB->lifecycleStage)->toBe('assessment_completed')
            ->and($stageB->nextLifecycleStage)->toBe('invoice_accepted')
            ->and($stageB->resolvedDocuments)->toBe($stageA->resolvedDocuments)
            ->and($stageB->pendingDocuments)->toBe($stageA->pendingDocuments)
            ->and($stageB->lifecycleGaps)->toBe([])
            ->and($stageB->documentSetFingerprint)->not->toBe($stageA->documentSetFingerprint)
            ->and($stageB->browserEtag)->toBe($stageA->browserEtag)
            ->and($repository->checksum('acceptance-application-r1.yaml'))->toBe($applicationChecksum);

        $repository->acceptInvoiceRevision(1, 50000);
        $invoiceRevisionOneChecksum = $repository->checksum('acceptance-invoice-r1.yaml');
        $stageC1 = propertyReservationAcceptanceSnapshot($repository, 'invoice_revision_1', 'DOCUMENT-INVOICE');
        $invoiceRevisionOne = $this->actingAs($user)->get(acceptanceBrowserUrl('DOCUMENT-INVOICE'));
        $invoiceRevisionOne->assertSuccessful()->assertSee('50000', escape: false);
        $invoiceRevisionOneEtag = $invoiceRevisionOne->headers->get('ETag');
        expect($stageC1->lifecycleStage)->toBe('invoice_accepted')
            ->and($stageC1->nextLifecycleStage)->toBe('payment_evidence_submitted')
            ->and($stageC1->lifecycleGaps)->toBe([])
            ->and($stageC1->resolvedDocuments)->toBe([
                'DOCUMENT-APPLICATION',
                'DOCUMENT-BROCHURE',
                'DOCUMENT-INVOICE',
            ])
            ->and($stageC1->pendingDocuments)->toBe([
                'DOCUMENT-RECEIPT' => ['PaymentApproval'],
                'DOCUMENT-RESERVATION-CERTIFICATE' => ['Receipt'],
            ])
            ->and($stageC1->selectedArtifactRevisions['ARTIFACT-INVOICE-ACCEPTANCE-000001'])->toBe(1);

        $repository->acceptInvoiceRevision(2, 51000);
        $stageC2 = propertyReservationAcceptanceSnapshot($repository, 'invoice_revision_2', 'DOCUMENT-INVOICE');
        $invoiceRevisionTwo = $this->actingAs($user)
            ->withHeader('If-None-Match', $invoiceRevisionOneEtag)
            ->get(acceptanceBrowserUrl('DOCUMENT-INVOICE'));
        $invoiceRevisionTwo->assertSuccessful()
            ->assertSee('51000', escape: false)
            ->assertDontSee('50000', escape: false);
        expect($stageC2->selectedArtifactRevisions['ARTIFACT-INVOICE-ACCEPTANCE-000001'])->toBe(2)
            ->and($stageC2->lifecycleStage)->toBe('invoice_accepted')
            ->and($stageC2->nextLifecycleStage)->toBe('payment_evidence_submitted')
            ->and($stageC2->lifecycleGaps)->toBe([])
            ->and($stageC2->resolvedDocuments)->toBe($stageC1->resolvedDocuments)
            ->and($stageC2->pendingDocuments)->toBe($stageC1->pendingDocuments)
            ->and($stageC2->browserDocumentIdentifier)->not->toBe($stageC1->browserDocumentIdentifier)
            ->and($stageC2->browserChecksum)->not->toBe($stageC1->browserChecksum)
            ->and($stageC2->browserEtag)->not->toBe($stageC1->browserEtag)
            ->and($repository->checksum('acceptance-invoice-r1.yaml'))->toBe($invoiceRevisionOneChecksum)
            ->and($repository->artifactPath('acceptance-invoice-r1.yaml'))->toBeFile()
            ->and($repository->artifactPath('acceptance-invoice-r2.yaml'))->toBeFile();

        $unrelatedBefore = app(BuildResolvedDocumentSet::class)->handle(
            $repository->root,
            app(ValidateRepository::class)->handle($repository->root),
            new CompilationSubject('RESERVATION-000002', 'PropertyReservation'),
        );
        $unrelatedFingerprint = $unrelatedBefore->fingerprint;
        $repository->writeArtifact('unrelated-invoice-000002-r2.yaml', [
            'identifier' => 'ARTIFACT-INVOICE-000002',
            'type' => 'Invoice',
            'revision' => 2,
            'status' => 'accepted',
            'accepted_at' => '2026-08-02T13:00:00+08:00',
            'profile' => 'PROFILE-PROPERTY-RESERVATION',
            'scenario' => 'SCENARIO-MANUAL-PAYMENT-RESERVATION',
            'subject' => ['identifier' => 'RESERVATION-000002', 'type' => 'PropertyReservation'],
            'references' => [
                ['relationship' => 'supersedes', 'identifier' => 'ARTIFACT-INVOICE-000002', 'revision' => 1],
                ['relationship' => 'produced_by', 'identifier' => 'ARTIFACT-ASSESSMENT-000002', 'revision' => 1],
            ],
            'payload' => ['amount' => 76000, 'currency' => 'PHP', 'correction_reason' => 'Unrelated fictional correction'],
        ]);
        $afterUnrelatedChange = propertyReservationAcceptanceSnapshot($repository, 'unrelated_subject_changed', 'DOCUMENT-INVOICE');
        expect($afterUnrelatedChange->documentSetFingerprint)->toBe($stageC2->documentSetFingerprint)
            ->and($afterUnrelatedChange->browserEtag)->toBe($stageC2->browserEtag)
            ->and($afterUnrelatedChange->browserChecksum)->toBe($stageC2->browserChecksum);

        $repository->submitPaymentEvidence();
        $stageD = propertyReservationAcceptanceSnapshot($repository, 'payment_evidence_submitted', 'DOCUMENT-INVOICE');
        expect($stageD->lifecycleStage)->toBe('payment_evidence_submitted')
            ->and($stageD->nextLifecycleStage)->toBe('payment_approved')
            ->and($stageD->lifecycleGaps)->toBe([])
            ->and($stageD->resolvedDocuments)->toBe($stageC2->resolvedDocuments)
            ->and($stageD->pendingDocuments)->toBe([
                'DOCUMENT-RECEIPT' => ['PaymentApproval'],
                'DOCUMENT-RESERVATION-CERTIFICATE' => ['Receipt'],
            ])
            ->and($stageD->browserEtag)->toBe($stageC2->browserEtag);
        $this->actingAs($user)->get(acceptanceBrowserUrl('DOCUMENT-RECEIPT'))->assertUnprocessable();

        $repository->acceptPaymentApproval();
        $stageE = propertyReservationAcceptanceSnapshot($repository, 'payment_approved', 'DOCUMENT-INVOICE');
        expect($stageE->lifecycleStage)->toBe('payment_approved')
            ->and($stageE->nextLifecycleStage)->toBe('receipt_accepted')
            ->and($stageE->lifecycleGaps)->toBe([])
            ->and($stageE->resolvedDocuments)->toBe($stageD->resolvedDocuments)
            ->and($stageE->pendingDocuments)->toBe([
                'DOCUMENT-RECEIPT' => ['Receipt'],
                'DOCUMENT-RESERVATION-CERTIFICATE' => ['Receipt'],
            ])
            ->and($stageE->browserEtag)->toBe($stageD->browserEtag);
        $this->actingAs($user)->get(acceptanceBrowserUrl('DOCUMENT-RECEIPT'))->assertUnprocessable();

        $repository->acceptReceipt();
        $stageF = propertyReservationAcceptanceSnapshot($repository, 'official_receipt_produced', 'DOCUMENT-RECEIPT');
        $receipt = $this->actingAs($user)->get(acceptanceBrowserUrl('DOCUMENT-RECEIPT'));
        $receipt->assertSuccessful()
            ->assertSee('51000', escape: false)
            ->assertSee('approved', escape: false);
        $receiptEtag = $receipt->headers->get('ETag');
        $head = $this->actingAs($user)->head(acceptanceBrowserUrl('DOCUMENT-RECEIPT'));
        $head->assertSuccessful()->assertContent('')->assertHeader('ETag', $receiptEtag);
        expect($stageF->lifecycleStage)->toBe('receipt_accepted')
            ->and($stageF->nextLifecycleStage)->toBe('reservation_certified')
            ->and($stageF->lifecycleGaps)->toBe([])
            ->and($stageF->resolvedDocuments)->toBe([
                'DOCUMENT-APPLICATION',
                'DOCUMENT-BROCHURE',
                'DOCUMENT-INVOICE',
                'DOCUMENT-RECEIPT',
            ])
            ->and($stageF->pendingDocuments)->toBe([
                'DOCUMENT-RESERVATION-CERTIFICATE' => ['ReservationCertificate'],
            ])
            ->and($head->headers->get('Content-Length'))->toBe((string) mb_strlen($receipt->getContent(), '8bit'));
        $this->actingAs($user)->withHeader('If-None-Match', $receiptEtag)->get(acceptanceBrowserUrl('DOCUMENT-RECEIPT'))->assertNotModified();
        $this->actingAs($user)->withHeader('If-None-Match', 'W/'.$receiptEtag)->get(acceptanceBrowserUrl('DOCUMENT-RECEIPT'))->assertNotModified();

        $repository->acceptReservationCertificate();
        $stageG = propertyReservationAcceptanceSnapshot($repository, 'reservation_certified', 'DOCUMENT-RESERVATION-CERTIFICATE');
        $certificate = $this->actingAs($user)->get(acceptanceBrowserUrl('DOCUMENT-RESERVATION-CERTIFICATE'));
        $certificate->assertSuccessful()
            ->assertSee(PropertyReservationAcceptanceRepository::Applicant, escape: false)
            ->assertSee(PropertyReservationAcceptanceRepository::Lot, escape: false)
            ->assertSee('51000', escape: false)
            ->assertDontSee('Ben Example', escape: false)
            ->assertDontSee('75000', escape: false);
        expect($stageG->lifecycleStage)->toBe('reservation_certified')
            ->and($stageG->nextLifecycleStage)->toBeNull()
            ->and($stageG->lifecycleGaps)->toBe([])
            ->and($stageG->resolvedDocuments)->toHaveCount(5)
            ->and($stageG->pendingDocuments)->toBe([])
            ->and($stageG->browserEtag)->toBe($certificate->headers->get('ETag'));

        $unrelatedAfter = app(BuildResolvedDocumentSet::class)->handle(
            $repository->root,
            app(ValidateRepository::class)->handle($repository->root),
            new CompilationSubject('RESERVATION-000002', 'PropertyReservation'),
        );
        expect($unrelatedAfter->fingerprint)->not->toBe($unrelatedFingerprint)
            ->and($certificate->getContent())->not->toContain('RESERVATION-000002')
            ->and($repository->checksum('acceptance-application-r1.yaml'))->toBe($applicationChecksum)
            ->and($repository->checksum('acceptance-invoice-r1.yaml'))->toBe($invoiceRevisionOneChecksum)
            ->and(canonicalBusinessChecksum(base_path()))->toBe($canonicalChecksumBefore)
            ->and(collect([$stageA, $stageB, $stageC1, $stageC2, $stageD, $stageE, $stageF, $stageG])->pluck('repositoryFingerprint')->unique())->toHaveCount(8)
            ->and(collect([$stageA, $stageB, $stageC1, $stageC2, $stageD, $stageE, $stageF, $stageG])->pluck('documentSetFingerprint')->unique())->toHaveCount(8);
    } finally {
        $repository->cleanup();
    }
});

it('rejects cross-subject references before acceptance compilation', function () {
    $repository = PropertyReservationAcceptanceRepository::create(base_path());

    try {
        $repository->begin();
        $repository->writeArtifact('cross-subject-payment-evidence.yaml', [
            'identifier' => 'ARTIFACT-PAYMENT-EVIDENCE-CROSS-SUBJECT',
            'type' => 'PaymentEvidence',
            'revision' => 1,
            'status' => 'accepted',
            'profile' => 'PROFILE-PROPERTY-RESERVATION',
            'scenario' => 'SCENARIO-MANUAL-PAYMENT-RESERVATION',
            'subject' => [
                'identifier' => PropertyReservationAcceptanceRepository::SubjectIdentifier,
                'type' => PropertyReservationAcceptanceRepository::SubjectType,
            ],
            'references' => [
                ['relationship' => 'payment_evidence_for', 'identifier' => 'ARTIFACT-INVOICE-000002', 'revision' => 1],
            ],
            'payload' => ['reference' => 'INVALID-CROSS-SUBJECT-PROOF', 'amount' => 75000],
        ]);

        $manifest = app(ValidateRepository::class)->handle($repository->root);

        expect($manifest->hasErrors())->toBeTrue()
            ->and(collect($manifest->findings)->pluck('code'))->toContain('artifact.cross_subject_reference');
    } finally {
        $repository->cleanup();
    }
});

it('rejects duplicate revisions in an isolated acceptance repository', function () {
    $repository = PropertyReservationAcceptanceRepository::create(base_path());

    try {
        $repository->begin();
        expect(fn () => $repository->writeArtifact('acceptance-application-r1.yaml', []))
            ->toThrow(LogicException::class, 'Acceptance artifact already exists');
        $repository->duplicate('acceptance-application-r1.yaml', 'duplicate-application-r1.yaml');

        $manifest = app(ValidateRepository::class)->handle($repository->root);

        expect($manifest->hasErrors())->toBeTrue()
            ->and(collect($manifest->findings)->pluck('code'))->toContain('artifact.duplicate_identifier');
    } finally {
        $repository->cleanup();
    }
});

it('rejects ambiguous accepted evidence instead of selecting by filename order', function () {
    $repository = PropertyReservationAcceptanceRepository::create(base_path());

    try {
        $repository->begin();
        $repository->acceptAssessment();
        $repository->acceptInvoiceRevision(1, 50000);
        $repository->writeArtifact('aaa-alternate-charge.yaml', [
            'identifier' => 'ARTIFACT-INVOICE-ACCEPTANCE-ALTERNATE',
            'type' => 'Invoice',
            'revision' => 1,
            'status' => 'accepted',
            'accepted_at' => '2026-08-02T12:00:00+08:00',
            'profile' => 'PROFILE-PROPERTY-RESERVATION',
            'scenario' => 'SCENARIO-MANUAL-PAYMENT-RESERVATION',
            'subject' => [
                'identifier' => PropertyReservationAcceptanceRepository::SubjectIdentifier,
                'type' => PropertyReservationAcceptanceRepository::SubjectType,
            ],
            'references' => [
                ['relationship' => 'produced_by', 'identifier' => 'ARTIFACT-ASSESSMENT-ACCEPTANCE-000001', 'revision' => 1],
            ],
            'payload' => ['amount' => 99999, 'currency' => 'PHP'],
        ]);
        $manifest = app(ValidateRepository::class)->handle($repository->root);

        expect(fn () => app(BuildResolvedDocumentSet::class)->handle(
            $repository->root,
            $manifest,
            new CompilationSubject(
                PropertyReservationAcceptanceRepository::SubjectIdentifier,
                PropertyReservationAcceptanceRepository::SubjectType,
            ),
        ))->toThrow(AmbiguousArtifactSelection::class);
    } finally {
        $repository->cleanup();
    }
});

it('rejects approval and certification evidence with missing prerequisites', function (string $filename, array $artifact) {
    $repository = PropertyReservationAcceptanceRepository::create(base_path());

    try {
        $repository->begin();
        $repository->writeArtifact($filename, $artifact);

        $manifest = app(ValidateRepository::class)->handle($repository->root);

        expect($manifest->hasErrors())->toBeTrue()
            ->and(collect($manifest->findings)->pluck('code'))->toContain('artifact.reference_missing');
    } finally {
        $repository->cleanup();
    }
})->with([
    'approval before payment evidence' => [
        'invalid-approval.yaml',
        [
            'identifier' => 'ARTIFACT-PAYMENT-APPROVAL-PREMATURE',
            'type' => 'PaymentApproval',
            'revision' => 1,
            'status' => 'accepted',
            'profile' => 'PROFILE-PROPERTY-RESERVATION',
            'scenario' => 'SCENARIO-MANUAL-PAYMENT-RESERVATION',
            'subject' => ['identifier' => PropertyReservationAcceptanceRepository::SubjectIdentifier, 'type' => PropertyReservationAcceptanceRepository::SubjectType],
            'references' => [['relationship' => 'approved_by', 'identifier' => 'ARTIFACT-PAYMENT-EVIDENCE-MISSING', 'revision' => 1]],
            'payload' => ['outcome' => 'approved', 'reviewer_role' => 'demonstration-reviewer'],
        ],
    ],
    'certificate before receipt' => [
        'invalid-certificate.yaml',
        [
            'identifier' => 'ARTIFACT-RESERVATION-CERTIFICATE-PREMATURE',
            'type' => 'ReservationCertificate',
            'revision' => 1,
            'status' => 'accepted',
            'profile' => 'PROFILE-PROPERTY-RESERVATION',
            'scenario' => 'SCENARIO-MANUAL-PAYMENT-RESERVATION',
            'subject' => ['identifier' => PropertyReservationAcceptanceRepository::SubjectIdentifier, 'type' => PropertyReservationAcceptanceRepository::SubjectType],
            'references' => [['relationship' => 'supports', 'identifier' => 'ARTIFACT-RECEIPT-MISSING', 'revision' => 1]],
            'payload' => ['lot_identifier' => PropertyReservationAcceptanceRepository::Lot],
        ],
    ],
]);

it('keeps the acceptance harness outside production runtime boundaries', function () {
    $controller = file_get_contents(app_path('Http/Controllers/ShowCompiledBrowserDocumentController.php'));
    $workbench = file_get_contents(resource_path('js/pages/DocumentSetWorkbench.vue'));
    $acceptanceBoundary = implode("\n", [
        $controller,
        $workbench,
        file_get_contents(app_path('Domain/Compilation/BuildResolvedDocumentSet.php')),
        file_get_contents(app_path('Integration/XDocument/ResolveXDocumentBrowserRepresentation.php')),
    ]);

    expect(PropertyReservationAcceptanceRepository::class)->toStartWith('Tests\\Support\\')
        ->and($controller)->not->toContain('write', 'put(', 'approvePayment', 'issueReceipt', 'certifyReservation')
        ->and($workbench)->toContain("entry.readiness === 'resolved'", 'entry.missing_evidence')
        ->not->toContain('approvePayment', 'issueReceipt', 'certifyReservation')
        ->and($acceptanceBoundary)->not->toContain(
            'PaymentGateway',
            'x-change',
            'Settlement',
            'AcroForm',
            'approvePayment',
            'issueReceipt',
            'certifyReservation',
        );
});
