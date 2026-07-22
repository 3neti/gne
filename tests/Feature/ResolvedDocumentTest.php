<?php

use App\Domain\Compilation\CompilationSubject;
use App\Domain\Compilation\DocumentResolutionRequest;
use App\Domain\Compilation\ResolveDocument;
use App\Domain\Repository\ValidateRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('resolves the supported property-reservation documents', function (string $identifier, int $primaryRevision, string $expectedField) {
    $manifest = app(ValidateRepository::class)->handle(base_path());
    $document = app(ResolveDocument::class)->handle(base_path(), $manifest, new DocumentResolutionRequest($identifier, new CompilationSubject('RESERVATION-000001', 'PropertyReservation')));
    $fieldIdentifiers = collect($document->sections)->flatMap(fn ($section) => $section->fields)->map(fn ($field) => $field->identifier);

    expect($document->primaryArtifact->revision)->toBe($primaryRevision)
        ->and($document->profile)->toBe('PROFILE-PROPERTY-RESERVATION')
        ->and($fieldIdentifiers)->toContain($expectedField)
        ->and($document->evidence)->not->toBeEmpty();
})->with([
    'application' => ['DOCUMENT-APPLICATION', 1, 'applicant_alias'],
    'invoice' => ['DOCUMENT-INVOICE', 2, 'amount'],
    'receipt' => ['DOCUMENT-RECEIPT', 1, 'approval'],
    'reservation certificate' => ['DOCUMENT-RESERVATION-CERTIFICATE', 1, 'receipt_amount'],
]);

it('reports resolved documents and browser projections during compilation', function () {
    expect(Artisan::call('gne:compile', ['--json' => true]))->toBe(0);
    $plan = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($plan['resolved_documents'])->toBe(6)
        ->and($plan['browser_projections'])->toBe(6)
        ->and($plan['drivers']['document']['reason'])->toBe('x-document is not installed');
});

it('protects and displays a browser projection with field evidence', function () {
    $parameters = ['document' => 'DOCUMENT-INVOICE', 'subject' => 'RESERVATION-000001'];
    $this->get(route('documents.show', $parameters))->assertRedirect(route('login'));

    $this->withoutVite();
    $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
        ->get(route('documents.show', $parameters))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ResolvedDocumentWorkbench')
            ->where('projection.title', 'Reservation Invoice')
            ->where('projection.sections.1.fields.0.value', 50000)
            ->where('projection.sections.1.fields.0.evidence.artifact_revision', 2)
            ->where('projection.sections.1.fields.0.evidence.value_path', 'payload.amount')
            ->where('projection.metadata.compilation_subject.identifier', 'RESERVATION-000001')
        );
});

it('distinguishes missing definitions from definitions with missing evidence', function () {
    $this->withoutVite();
    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

    $this->get(route('documents.show', ['document' => 'DOCUMENT-NOT-FOUND', 'subject' => 'RESERVATION-000001']))->assertNotFound();
    $this->get(route('documents.show', ['document' => 'DOCUMENT-INVOICE', 'subject' => 'RESERVATION-NOT-FOUND']))->assertNotFound();
    $this->get(route('documents.show', ['document' => 'DOCUMENT-BROCHURE', 'subject' => 'RESERVATION-000001']))->assertUnprocessable();
    $this->get(route('documents.show', ['document' => 'DOCUMENT-RECEIPT', 'subject' => 'RESERVATION-000002']))->assertUnprocessable();
});
