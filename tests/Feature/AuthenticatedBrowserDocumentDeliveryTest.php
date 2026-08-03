<?php

use App\Application\Authorization\GrantSubjectAccess;
use App\Domain\Authorization\SubjectPermission;
use App\Domain\Compilation\CompilationSubject;
use App\Integration\XDocument\BrowserDocumentRepresentationResolver;
use App\Integration\XDocument\ResolveXDocumentBrowserRepresentation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\XDocument\Browser\Host\BrowserHostResponse;
use LBHurtado\XDocument\Browser\Host\BrowserRepresentation;

uses(RefreshDatabase::class);

function authenticatedDocumentUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);

    foreach (['RESERVATION-000001', 'RESERVATION-000002'] as $identifier) {
        app(GrantSubjectAccess::class)->handle(
            $user,
            new CompilationSubject($identifier, 'PropertyReservation'),
            SubjectPermission::View,
        );
    }

    return $user;
}

function browserDocumentUrl(string $document = 'DOCUMENT-INVOICE', string $subject = 'RESERVATION-000001', array $query = []): string
{
    return route('documents.browser', ['subject' => $subject, 'document' => $document, ...$query]);
}

it('requires the existing authenticated and verified GNE session', function () {
    $this->get(browserDocumentUrl())->assertRedirect(route('login'));
});

it('delivers exact styled composition bytes through the Laravel response factory', function () {
    $expected = app(ResolveXDocumentBrowserRepresentation::class)->handle(
        base_path(),
        'DOCUMENT-INVOICE',
        'RESERVATION-000001',
    );
    $response = $this->actingAs(authenticatedDocumentUser())->get(browserDocumentUrl());

    $response->assertSuccessful()
        ->assertHeader('Content-Type', 'text/html; charset=utf-8')
        ->assertHeader('ETag', $expected->etag)
        ->assertHeader('Content-Length', (string) $expected->output->byteLength)
        ->assertHeader('Cache-Control', 'no-cache, private')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->getContent())->toBe($expected->output->inlineContent)
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline;')
        ->and($response->headers->get('Content-Disposition'))->toContain($expected->output->filename)
        ->and($response->getContent())->toContain(
            'RESERVATION-000001',
            'Ana Example',
            'LOT-037',
            '50000',
            'data-x-document-interaction-format=',
            '<style ',
        )
        ->not->toContain(
            '<script',
            '<form',
            '<button',
            'RESERVATION-000002',
            'Ben Example',
            '75000',
        );
});

it('supports HEAD with one runtime resolution and GET representation metadata', function () {
    $delegate = app(ResolveXDocumentBrowserRepresentation::class);
    $recording = new class($delegate) implements BrowserDocumentRepresentationResolver
    {
        public int $invocations = 0;

        public function __construct(private ResolveXDocumentBrowserRepresentation $delegate) {}

        public function handle(string $repositoryRoot, string $documentIdentifier, string $subjectIdentifier, BrowserRepresentation $representation = BrowserRepresentation::CompositionStyledHtml): BrowserHostResponse
        {
            $this->invocations++;

            return $this->delegate->handle($repositoryRoot, $documentIdentifier, $subjectIdentifier, $representation);
        }
    };
    app()->instance(BrowserDocumentRepresentationResolver::class, $recording);
    $response = $this->actingAs(authenticatedDocumentUser())->call('HEAD', browserDocumentUrl());

    $response->assertSuccessful()->assertContent('');
    expect($recording->invocations)->toBe(1)
        ->and((int) $response->headers->get('Content-Length'))->toBeGreaterThan(0)
        ->and($response->headers->get('Content-Type'))->toBe('text/html; charset=utf-8')
        ->and($response->headers->get('ETag'))->toMatch('/^"sha256:[a-f0-9]{64}"$/');
});

it('honors strong and weak validators for conditional GET and HEAD', function (string $method, bool $weak) {
    $user = authenticatedDocumentUser();
    $first = $this->actingAs($user)->get(browserDocumentUrl());
    $etag = $first->headers->get('ETag');
    $validator = $weak ? 'W/'.$etag : $etag;
    $request = $this->actingAs($user)->withHeader('If-None-Match', $validator);
    $response = $method === 'HEAD'
        ? $request->head(browserDocumentUrl())
        : $request->get(browserDocumentUrl());

    $response->assertStatus(304)->assertContent('')->assertHeader('ETag', $etag);
})->with([
    'strong GET' => ['GET', false],
    'weak GET' => ['GET', true],
    'strong HEAD' => ['HEAD', false],
    'weak HEAD' => ['HEAD', true],
]);

it('returns the full GET or metadata-only HEAD for non-matching validators', function (string $method) {
    $request = $this->actingAs(authenticatedDocumentUser())
        ->withHeader('If-None-Match', 'W/"sha256:different"');
    $response = $method === 'HEAD'
        ? $request->head(browserDocumentUrl())
        : $request->get(browserDocumentUrl());

    $response->assertSuccessful()->assertHeader('Content-Type', 'text/html; charset=utf-8');
    expect((int) $response->headers->get('Content-Length'))->toBeGreaterThan(0)
        ->and($response->getContent() === '')->toBe($method === 'HEAD');
})->with(['GET', 'HEAD']);

it('delivers resolved documents for both subjects and rejects pending documents', function () {
    $user = authenticatedDocumentUser();

    $this->actingAs($user)
        ->get(browserDocumentUrl('DOCUMENT-INVOICE', 'RESERVATION-000002'))
        ->assertSuccessful()
        ->assertSee('Ben Example', escape: false)
        ->assertDontSee('Ana Example', escape: false);
    $this->actingAs($user)
        ->get(browserDocumentUrl('DOCUMENT-RECEIPT', 'RESERVATION-000002'))
        ->assertUnprocessable();
    $this->actingAs($user)
        ->get(browserDocumentUrl('DOCUMENT-RESERVATION-CERTIFICATE', 'RESERVATION-000002'))
        ->assertUnprocessable();
});

it('maps known request failures without concealing integration defects', function () {
    $user = authenticatedDocumentUser();

    $this->actingAs($user)->get(browserDocumentUrl('DOCUMENT-NOT-FOUND'))->assertNotFound();
    $this->actingAs($user)->get(browserDocumentUrl(subject: 'RESERVATION-NOT-FOUND'))->assertNotFound();
    $this->actingAs($user)->get(browserDocumentUrl(query: ['representation' => 'pdf']))->assertBadRequest();
    $this->actingAs($user)->withHeader('If-None-Match', 'malformed')->get(browserDocumentUrl())->assertBadRequest();
});

it('supports only the allowlisted MVP composition representations', function (string $representation, string $mediaType) {
    $this->actingAs(authenticatedDocumentUser())
        ->get(browserDocumentUrl(query: ['representation' => $representation]))
        ->assertSuccessful()
        ->assertHeader('Content-Type', $mediaType);
})->with([
    'composition JSON' => ['browser-composition', 'application/vnd.3neti.x-document.browser-composition+json'],
    'composition HTML' => ['browser-composition-html', 'text/html; charset=utf-8'],
    'styled composition HTML' => ['browser-composition-html-styled', 'text/html; charset=utf-8'],
]);

it('keeps prior invoice revisions immutable while delivering revision two', function () {
    expect(base_path('business/profiles/property-reservation/examples/artifacts/invoice-000001-r1.yaml'))->toBeFile()
        ->and(base_path('business/profiles/property-reservation/examples/artifacts/invoice-000001-r2.yaml'))->toBeFile();

    $response = app(ResolveXDocumentBrowserRepresentation::class)->handle(
        base_path(),
        'DOCUMENT-INVOICE',
        'RESERVATION-000001',
    );

    expect($response->output->inlineContent)->toContain('50000');
});
