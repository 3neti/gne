<?php

use App\Application\Authorization\GrantSubjectAccess;
use App\Application\Storyboard\BuildStoryboard;
use App\Application\Storyboard\LoadStoryboardDefinition;
use App\Application\Storyboard\PropertyReservationStoryboardStateProvider;
use App\Application\Storyboard\ResolveStoryboardRepositoryRoot;
use App\Domain\Authorization\SubjectPermission;
use App\Domain\Compilation\CompilationSubject;
use App\Infrastructure\Storyboard\StoryboardArtifactException;
use App\Infrastructure\Storyboard\StoryboardHtmlRenderer;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;

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
        ->and(collect($manifest['frames'])->where('production_surface', true))->toHaveCount(22)
        ->and(collect($manifest['frames'])->where('capture_type', 'explanation'))->toHaveCount(3)
        ->and($manifest['authentication']['mode'])->toBe('interactive_login')
        ->and($manifest['authentication']['login_submitted'])->toBeFalse()
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
        $frame['capture_status'] = 'captured_and_verified';
        $frame['capture_checksum'] = hash_file('sha256', $path);
        $frame['capture_byte_length'] = filesize($path);
        $frame['application_status'] = 'captured_and_verified';
        $frame['application_final_route'] = $frame['expected_final_route'];
        $frame['application_http_status'] = 200;
        $frame['application_expected_marker_verified'] = true;
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

it('labels authenticated storyboard explanations as non-production surfaces', function () {
    $definition = app(LoadStoryboardDefinition::class)->handle(base_path(), 'property-reservation-mvp');
    app(BuildStoryboard::class)->handle(base_path(), $definition, 'http://gne.test');
    $path = '/storyboards/property-reservation-mvp/frames/proof-submitted';

    $this->get($path)->assertRedirect('/login');
    $user = User::factory()->create(['is_operator' => true]);
    app(GrantSubjectAccess::class)->handle(
        $user,
        new CompilationSubject('PROPERTY-RESERVATION-MVP-ACCEPTANCE-000001', 'PropertyReservation'),
        SubjectPermission::View,
    );
    $this->actingAs($user)->get($path)
        ->assertSuccessful()
        ->assertSee('payment_evidence_submitted', escape: false)
        ->assertSee('Not a production application screen', escape: false)
        ->assertSee('Narrative persona', escape: false)
        ->assertSee('Authenticated user', escape: false);
});

it('exposes isolated storyboard state only to an authenticated local capture request', function () {
    $definition = app(LoadStoryboardDefinition::class)->handle(base_path(), 'property-reservation-mvp');
    app(BuildStoryboard::class)->handle(base_path(), $definition, 'http://gne.test');
    $headers = [
        'X-GNE-Storyboard' => 'property-reservation-mvp',
        'X-GNE-Storyboard-Stage' => 'invoice-r2',
    ];

    $this->withHeaders($headers)->get('/document-sets')->assertRedirect('/login');

    $user = User::factory()->create();
    app(GrantSubjectAccess::class)->handle(
        $user,
        new CompilationSubject('PROPERTY-RESERVATION-MVP-ACCEPTANCE-000001', 'PropertyReservation'),
        SubjectPermission::View,
    );
    $this->actingAs($user)->withHeaders($headers)->get('/document-sets')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('DocumentSetWorkbench')
            ->where('documentSets.0.subject.identifier', 'PROPERTY-RESERVATION-MVP-ACCEPTANCE-000001'));

    expect(app(ResolveStoryboardRepositoryRoot::class)->handle(Request::create('/document-sets')))->toBe(base_path());
});

it('keeps the capture runner on one interactive browser context without session injection', function () {
    $script = file_get_contents(base_path('scripts/gne-storyboard-capture.mjs'));
    $capture = file_get_contents(app_path('Infrastructure/Storyboard/CaptureStoryboard.php'));

    expect(substr_count($script, 'browser.newContext('))->toBe(1)
        ->and($script)->toContain('loginFrame.capture_route')
        ->and($script)->toContain("page.locator('[data-test=\"login-button\"]').click()")
        ->and($script)->toContain("status: 'captured_and_verified'")
        ->and($script)->not->toContain('storageState', 'laravel_session', 'session cookie', 'csrf')
        ->and($capture)->toContain("forceFill(['is_operator' => true])", 'GrantSubjectAccess', 'RevokeSubjectAccess')
        ->not->toContain("subject_identifier' => '*'");
});

it('keeps storyboard definitions out of canonical business source and generated outputs disposable', function () {
    expect(base_path('docs/mvp/storyboards/property-reservation-mvp.yaml'))->toBeFile()
        ->and(base_path('business/profiles/property-reservation/storyboards'))->not->toBeDirectory()
        ->and(file_get_contents(base_path('.gitignore')))->toContain('/.gne/');
});
