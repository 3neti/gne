<?php

use App\Application\Storyboard\BuildStoryboard;
use App\Application\Storyboard\LoadStoryboardDefinition;
use App\Application\Storyboard\PropertyReservationStoryboardStateProvider;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;

function storyboardBusinessChecksum(): string
{
    return hash('sha256', collect((new Filesystem)->allFiles(base_path('business')))
        ->sortBy(fn (SplFileInfo $file): string => $file->getRelativePathname())
        ->map(fn (SplFileInfo $file): string => $file->getRelativePathname()."\0".$file->getContents())->implode("\0"));
}

it('prepares isolated deterministic lifecycle snapshots without changing canonical business source', function () {
    $before = storyboardBusinessChecksum();
    $provider = app(PropertyReservationStoryboardStateProvider::class);

    $invoiceOne = $provider->snapshot(base_path(), 'invoice-r1');
    $invoiceOneAgain = $provider->snapshot(base_path(), 'invoice-r1');
    $certificate = $provider->snapshot(base_path(), 'certificate');

    expect($invoiceOne)->toBe($invoiceOneAgain)
        ->and($invoiceOne['lifecycle']['current_stage'])->toBe('invoice_accepted')
        ->and($certificate['lifecycle']['current_stage'])->toBe('reservation_certified')
        ->and(storyboardBusinessChecksum())->toBe($before);
});

it('builds a deterministic manifest narration movie package and 26-page PDF', function () {
    $this->artisan('gne:storyboard property-reservation-mvp')->assertSuccessful();
    $root = base_path('.gne/storyboards/property-reservation-mvp');
    $manifest = json_decode(file_get_contents($root.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['frames'])->toHaveCount(25)
        ->and($manifest['frames'][24]['snapshot']['lifecycle']['current_stage'])->toBe('reservation_certified')
        ->and($root.'/narration.md')->toBeFile()
        ->and($root.'/movie/manifest.json')->toBeFile()
        ->and($root.'/pdf/property-reservation-mvp.pdf')->toBeFile();
});

it('serves generated storyboard observations only to authenticated users', function () {
    $definition = app(LoadStoryboardDefinition::class)->handle(base_path(), 'property-reservation-mvp');
    app(BuildStoryboard::class)->handle(base_path(), $definition, 'http://gne.test');
    $path = '/storyboards/property-reservation-mvp/frames/invoice-r2';

    $this->get($path)->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get($path)
        ->assertSuccessful()
        ->assertSee('51000', escape: false)
        ->assertSee('invoice_accepted', escape: false)
        ->assertSee('Storyboard observation only', escape: false);
});

it('keeps storyboard definitions out of canonical business source and generated outputs disposable', function () {
    expect(base_path('docs/mvp/storyboards/property-reservation-mvp.yaml'))->toBeFile()
        ->and(base_path('business/profiles/property-reservation/storyboards'))->not->toBeDirectory()
        ->and(file_get_contents(base_path('.gitignore')))->toContain('/.gne/');
});
