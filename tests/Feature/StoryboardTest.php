<?php

use App\Application\Storyboard\BuildStoryboard;
use App\Application\Storyboard\LoadStoryboardDefinition;
use App\Application\Storyboard\PropertyReservationStoryboardStateProvider;
use App\Infrastructure\Storyboard\StoryboardArtifactException;
use App\Infrastructure\Storyboard\StoryboardHtmlRenderer;
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

it('builds a deterministic draft manifest without claiming final renditions', function () {
    $this->artisan('gne:storyboard property-reservation-mvp')->assertSuccessful();
    $root = base_path('.gne/storyboards/property-reservation-mvp');
    $manifest = json_decode(file_get_contents($root.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['frames'])->toHaveCount(25)
        ->and($manifest['frames'][24]['snapshot']['lifecycle']['current_stage'])->toBe('reservation_certified')
        ->and($root.'/narration.md')->toBeFile()
        ->and($root.'/movie/manifest.json')->toBeFile()
        ->and($manifest['capture_status'])->toBe('not_requested')
        ->and($manifest['outputs']['html']['status'])->toBe('planned')
        ->and($manifest['outputs']['pdf']['status'])->toBe('planned')
        ->and($root.'/pdf/property-reservation-mvp.pdf')->not->toBeFile();
});

it('renders a deterministic offline HTML site only from finalized captured frames', function () {
    $definition = app(LoadStoryboardDefinition::class)->handle(base_path(), 'property-reservation-mvp');
    $manifest = app(BuildStoryboard::class)->handle(base_path(), $definition, 'http://gne.test');
    $root = base_path('.gne/storyboards/property-reservation-mvp');
    $renderer = app(StoryboardHtmlRenderer::class);

    expect(fn () => $renderer->render($root, $manifest))->toThrow(StoryboardArtifactException::class);

    foreach ($manifest['frames'] as &$frame) {
        $path = $root.'/'.$frame['capture_filename'];
        file_put_contents($path, 'fictional captured frame '.$frame['sequence'].' '.$frame['expected']);
        $frame['capture_status'] = 'captured';
        $frame['capture_checksum'] = hash_file('sha256', $path);
        $frame['capture_byte_length'] = filesize($path);
    }
    unset($frame);
    $manifest['finalized_frame_fingerprint'] = hash('sha256', 'test-finalized-frame-inventory');
    $manifest['frames'][0]['action'] = 'Observe <script>alert("unsafe")</script>';
    file_put_contents($root.'/pdf/property-reservation-mvp.pdf', 'test PDF target');

    $first = $renderer->render($root, $manifest);
    $firstBytes = file_get_contents($root.'/html/index.html');
    $second = $renderer->render($root, $manifest);
    $index = file_get_contents($root.'/html/index.html');
    $invoiceOne = file_get_contents($root.'/html/frames/011-invoice-r1.html');
    $invoiceTwo = file_get_contents($root.'/html/frames/012-invoice-r2.html');
    $final = file_get_contents($root.'/html/frames/025-completed-summary.html');
    $login = file_get_contents($root.'/html/frames/001-login.html');
    $assetManifest = json_decode(file_get_contents($root.'/html/manifest.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($first)->toBe($second)
        ->and($firstBytes)->toBe($index)
        ->and($first['format'])->toBe('gne-storyboard-html/1.0')
        ->and($first['page_count'])->toBe(31)
        ->and($first['frame_page_count'])->toBe(25)
        ->and($assetManifest['aggregate_fingerprint'])->toBe($first['aggregate_fingerprint'])
        ->and((new Filesystem)->files($root.'/html/frames'))->toHaveCount(25)
        ->and((new Filesystem)->files($root.'/html/acts'))->toHaveCount(5)
        ->and($index)->toContain('Property Reservation MVP', 'PROPERTY-RESERVATION-MVP-ACCEPTANCE-000001')
        ->and($invoiceOne)->toContain('50000', '../assets/frames/011-invoice-r1.png')
        ->and($invoiceTwo)->toContain('51000', '../assets/frames/012-invoice-r2.png')
        ->and($final)->toContain('reservation_certified')
        ->and($login)->toContain('&lt;script&gt;alert(&quot;unsafe&quot;)&lt;/script&gt;')
        ->and($login)->not->toContain('<script>')
        ->and($index)->not->toContain('/Users/rli/', 'Capture: planned', 'http://', 'https://', 'APP_KEY', 'laravel_session');

    foreach ((new Filesystem)->allFiles($root.'/html') as $file) {
        if ($file->getExtension() !== 'html') {
            continue;
        }
        preg_match_all('/(?:href|src)="([^"]+)"/', $file->getContents(), $references);
        foreach ($references[1] as $reference) {
            expect(realpath($file->getPath().'/'.$reference))->not->toBeFalse();
        }
    }
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
