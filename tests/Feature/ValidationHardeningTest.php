<?php

use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

require_once dirname(__DIR__).'/Support/ValidationRepositoryFixture.php';

uses(RefreshDatabase::class);

it('emits stable structured validation JSON with warning-only readiness findings', function () {
    expect(Artisan::call('gne:validate', ['--json' => true]))->toBe(0);
    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($output)->toHaveKeys(['valid', 'fingerprint', 'summary', 'findings'])
        ->and($output['valid'])->toBeTrue()
        ->and($output['summary'])->toBe(['errors' => 0, 'warnings' => 2, 'info' => 0])
        ->and(array_column($output['findings'], 'code'))->toBe(['DOCUMENT_EVIDENCE_ABSENT', 'DOCUMENT_EVIDENCE_ABSENT']);
});

it('fails validation and refuses compilation for invalid authored payloads', function () {
    $root = validationRepositoryFixture();
    $files = new Filesystem;
    $path = $root.'/business/profiles/property-reservation/examples/artifacts/invoice-000002-r1.yaml';
    $files->put($path, str_replace('amount: 75000', 'amount: wrong', $files->get($path)));

    try {
        expect(Artisan::call('gne:validate', ['--repository' => $root, '--json' => true]))->toBe(1)
            ->and(Artisan::call('gne:compile', ['--repository' => $root]))->toBe(1);
    } finally {
        $files->deleteDirectory($root);
    }
});

it('shows structured validation findings in the authenticated workbench', function () {
    $this->withoutVite();

    $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
        ->get(route('repository'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('RepositoryWorkbench')
            ->has('findings', 2)
            ->where('findings.0.code', 'DOCUMENT_EVIDENCE_ABSENT')
            ->where('findings.0.source_path', 'business/profiles/civic-permit/documents/acknowledgement-note.yaml')
            ->where('findings.0.location', '/primary_artifact_type')
        );
});
