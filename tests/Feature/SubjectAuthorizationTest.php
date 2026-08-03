<?php

use App\Application\Authorization\GrantSubjectAccess;
use App\Application\Authorization\RevokeSubjectAccess;
use App\Domain\Authorization\SubjectPermission;
use App\Domain\Compilation\CompilationSubject;
use App\Domain\Repository\ValidateRepository;
use App\Integration\XDocument\BrowserDocumentRepresentationResolver;
use App\Models\SubjectAccessGrant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use LBHurtado\XDocument\Browser\Host\BrowserHostResponse;
use LBHurtado\XDocument\Browser\Host\BrowserRepresentation;

function grantSubject(User $user, string $identifier = 'RESERVATION-000001'): SubjectAccessGrant
{
    return app(GrantSubjectAccess::class)->handle(
        $user,
        new CompilationSubject($identifier, 'PropertyReservation'),
        SubjectPermission::View,
    );
}

it('filters inventory before serialization and isolates subjects', function () {
    $user = User::factory()->create();
    grantSubject($user);

    $this->actingAs($user)->get('/document-sets')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('DocumentSetWorkbench')
            ->has('documentSets', 1)
            ->where('documentSets.0.subject.identifier', 'RESERVATION-000001'));

    $this->actingAs($user)->get('/document-sets/RESERVATION-000001')->assertSuccessful();
    $this->actingAs($user)->get('/document-sets/RESERVATION-000002')->assertForbidden();
});

it('protects subject detail resolved document and browser GET and HEAD', function () {
    $user = User::factory()->create();
    grantSubject($user);

    $this->actingAs($user)->get('/document-sets/RESERVATION-000001')->assertSuccessful();
    $this->actingAs($user)->get('/documents/DOCUMENT-INVOICE/RESERVATION-000001')->assertSuccessful();
    $this->actingAs($user)->get('/subjects/RESERVATION-000001/documents/DOCUMENT-INVOICE/browser')->assertSuccessful();
    $this->actingAs($user)->head('/subjects/RESERVATION-000001/documents/DOCUMENT-INVOICE/browser')->assertSuccessful();
});

it('denies before invoking the x-document runtime or disclosing readiness', function () {
    $resolver = new class implements BrowserDocumentRepresentationResolver
    {
        public int $invocations = 0;

        public function handle(string $repositoryRoot, string $documentIdentifier, string $subjectIdentifier, BrowserRepresentation $representation = BrowserRepresentation::CompositionStyledHtml): BrowserHostResponse
        {
            $this->invocations++;
            throw new RuntimeException('Denied requests must not invoke x-document.');
        }
    };
    app()->instance(BrowserDocumentRepresentationResolver::class, $resolver);

    $response = $this->actingAs(User::factory()->create())
        ->get('/subjects/RESERVATION-000002/documents/DOCUMENT-RECEIPT/browser');

    $response->assertForbidden()->assertDontSee('PaymentApproval');
    expect($resolver->invocations)->toBe(0);
});

it('applies expiry and revocation immediately in the same session', function () {
    Carbon::setTestNow('2026-08-03 09:00:00');
    $user = User::factory()->create();
    $grant = grantSubject($user);

    $this->actingAs($user)->get('/document-sets/RESERVATION-000001')->assertSuccessful();
    app(RevokeSubjectAccess::class)->handle($user, 'RESERVATION-000001', SubjectPermission::View);
    $this->actingAs($user)->get('/document-sets/RESERVATION-000001')->assertForbidden();

    $grant = grantSubject($user);
    $grant->update(['expires_at' => now()->subSecond()]);
    $this->actingAs($user)->get('/document-sets/RESERVATION-000001')->assertForbidden();
    Carbon::setTestNow();
});

it('reserves global repository workbenches for deliberate operators', function () {
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertForbidden();
    $this->actingAs(User::factory()->create(['is_operator' => true]))->get('/dashboard')->assertSuccessful();
});

it('keeps grants independent from projection rebuilds and repository fingerprints', function () {
    $grant = grantSubject(User::factory()->create());
    $fingerprint = app(ValidateRepository::class)->handle(base_path())->fingerprint;

    $this->artisan('gne:rebuild --force')->assertSuccessful();

    expect($grant->fresh())->not->toBeNull()
        ->and(app(ValidateRepository::class)->handle(base_path())->fingerprint)->toBe($fingerprint);
});
