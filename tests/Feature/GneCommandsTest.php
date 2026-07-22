<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('validates the canonical repository', function () {
    $this->artisan('gne:validate')->assertSuccessful()->expectsOutputToContain('Repository validation passed');
});

it('creates deterministic semantic indexes that can be deleted and rebuilt', function () {
    $this->artisan('gne:index')->assertSuccessful();
    $first = file_get_contents(base_path('.gne/semantic/artifacts.json'));
    unlink(base_path('.gne/semantic/artifacts.json'));
    $this->artisan('gne:index')->assertSuccessful();
    expect(file_get_contents(base_path('.gne/semantic/artifacts.json')))->toBe($first);
});

it('materializes idempotent projections with stable repository identities', function () {
    $this->artisan('gne:materialize')->assertSuccessful();
    $count = DB::table('gne_artifacts')->count();
    $this->artisan('gne:materialize')->assertSuccessful();
    expect(DB::table('gne_artifacts')->count())->toBe($count)
        ->and(DB::table('gne_artifacts')->where('repository_identifier', 'ARTIFACT-APPLICATION-000001')->exists())->toBeTrue()
        ->and(DB::table('gne_materialization_runs')->count())->toBe(2)
        ->and(json_decode(DB::table('gne_artifacts')->where('repository_identifier', 'ARTIFACT-INVOICE-000001')->where('revision', '2')->value('metadata'), true)['payload']['amount'])->toBe(50000);
});

it('rebuilds disposable projections non-interactively', function () {
    $this->artisan('gne:rebuild --force')->assertSuccessful();
    expect(base_path('.gne/semantic/repository.json'))->toBeFile()
        ->and(DB::table('gne_profiles')->count())->toBe(2);
});

it('explains and plans compilation honestly', function () {
    $this->artisan('gne:explain --profile=property-reservation')->assertSuccessful()->expectsOutputToContain('The business belongs to the repository');
    $this->artisan('gne:compile')->assertSuccessful()->expectsOutputToContain('x-document not installed')->expectsOutputToContain('x-change not configured');
});

it('returns a failing exit code for malformed configuration', function () {
    $original = file_get_contents(base_path('gne.yaml'));
    file_put_contents(base_path('gne.yaml'), "gne: [\n");
    try {
        expect(Artisan::call('gne:validate', ['--json' => true]))->toBe(1);
    } finally {
        file_put_contents(base_path('gne.yaml'), $original);
    }
});
