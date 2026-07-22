<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('rebuilds the manual-payment reservation profile without changing accepted artifacts', function () {
    $artifactPaths = glob(base_path('business/profiles/property-reservation/examples/artifacts/*.yaml'));
    sort($artifactPaths);
    $before = hash('sha256', collect($artifactPaths)->map(fn (string $path): string => file_get_contents($path))->implode("\0"));

    $this->artisan('gne:validate')->assertSuccessful();
    $this->artisan('gne:index')->assertSuccessful();
    $this->artisan('gne:materialize')->assertSuccessful();
    $this->artisan('gne:explain --json --profile=property-reservation')->assertSuccessful()->expectsOutputToContain('SCENARIO-MANUAL-PAYMENT-RESERVATION');
    $this->artisan('gne:rebuild --force')->assertSuccessful();

    $after = hash('sha256', collect($artifactPaths)->map(fn (string $path): string => file_get_contents($path))->implode("\0"));
    expect($after)->toBe($before)
        ->and(DB::table('gne_artifacts')->count())->toBe(10)
        ->and(DB::table('gne_artifact_relationships')->count())->toBeGreaterThanOrEqual(8)
        ->and(json_decode(file_get_contents(base_path('.gne/semantic/glossary.json')), true))->not->toBeEmpty();
});

it('validates profiles from their declarations instead of reservation-specific filenames', function () {
    $this->artisan('gne:validate --json')->assertSuccessful()
        ->expectsOutputToContain('business/profiles/civic-permit/documents/acknowledgement-note.yaml')
        ->doesntExpectOutputToContain('profile.baseline_missing');
});
