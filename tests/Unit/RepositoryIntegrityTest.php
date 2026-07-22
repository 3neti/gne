<?php

use App\Domain\Repository\CanonicalRepositoryFingerprint;
use App\Domain\Repository\DiscoverRepository;
use Illuminate\Filesystem\Filesystem;

function createPortableRepositoryFixture(): string
{
    $root = sys_get_temp_dir().'/gne-portable-'.bin2hex(random_bytes(6));
    $files = new Filesystem;
    foreach (['lifecycles', 'scenarios', 'policies', 'documents', 'schemas', 'examples/artifacts'] as $directory) {
        $files->ensureDirectoryExists($root.'/business/profiles/external/'.$directory);
    }

    foreach (['schemas', 'policies', 'workflows', 'lifecycles', 'scenarios', 'documents', 'artifacts', 'decisions', 'analytics'] as $directory) {
        $files->ensureDirectoryExists($root.'/business/'.$directory);
    }

    $files->put($root.'/gne.yaml', "gne:\n  version: 1\n  repository:\n    business_path: business\n    generated_path: .gne\n  profiles:\n    enabled: [external]\n");
    $files->put($root.'/GENEI.md', '# External GNE Repository');
    $files->put($root.'/business/profiles/external/profile.yaml', "identifier: PROFILE-EXTERNAL\nslug: external\ntitle: External\nvocabulary: terms.yaml\nlifecycles: [lifecycles/progress.yaml]\nscenarios: [scenarios/example.yaml]\npolicies: [policies/rule.yaml]\ndocuments: [documents/note.yaml]\nschemas: [schemas/fact.schema.json]\n");
    $files->put($root.'/business/profiles/external/terms.yaml', "terms:\n  - { term: Fact, definition: External fact }\n");
    $files->put($root.'/business/profiles/external/lifecycles/progress.yaml', "identifier: LIFE-EXTERNAL\nsubject_type: ExternalCase\nstates: [recorded]\ntransitions: []\n");
    $files->put($root.'/business/profiles/external/scenarios/example.yaml', "identifier: SCENARIO-EXTERNAL\nprofile: PROFILE-EXTERNAL\ntitle: External scenario\nlifecycle: LIFE-EXTERNAL\n");
    $files->put($root.'/business/profiles/external/policies/rule.yaml', "identifier: POLICY-EXTERNAL\n");
    $files->put($root.'/business/profiles/external/documents/note.yaml', "identifier: DOCUMENT-EXTERNAL\n");
    $files->put($root.'/business/profiles/external/schemas/fact.schema.json', '{"type":"object"}');
    $files->put($root.'/business/profiles/external/examples/artifacts/fact.yaml', "identifier: FACT-001\ntype: Fact\nrevision: 1\nstatus: accepted\nprofile: PROFILE-EXTERNAL\nscenario: SCENARIO-EXTERNAL\nsubject: { identifier: EXTERNAL-001, type: ExternalCase }\npayload: { amount: 100 }\n");

    return $root;
}

it('discovers repositories outside the Laravel application with repository-relative evidence paths', function () {
    $root = createPortableRepositoryFixture();
    $files = new Filesystem;

    try {
        $manifest = (new DiscoverRepository($files, new CanonicalRepositoryFingerprint($files)))->handle($root);
        $paths = collect([$manifest->profiles, $manifest->scenarios, $manifest->artifacts])->flatten(1)->pluck('path');

        expect($manifest->hasErrors())->toBeFalse()
            ->and($paths)->each->not->toStartWith('/')
            ->and($paths)->each->toStartWith('business/')
            ->and($manifest->canonicalFiles)->each->not->toStartWith('/');
    } finally {
        $files->deleteDirectory($root);
    }
});

it('changes the canonical fingerprint when artifact payload bytes change and preserves payload inventory', function () {
    $root = createPortableRepositoryFixture();
    $files = new Filesystem;
    $discovery = new DiscoverRepository($files, new CanonicalRepositoryFingerprint($files));

    try {
        $before = $discovery->handle($root);
        $unchanged = $discovery->handle($root);
        $artifactPath = $root.'/business/profiles/external/examples/artifacts/fact.yaml';
        $files->put($artifactPath, str_replace('amount: 100', 'amount: 125', $files->get($artifactPath)));
        $after = $discovery->handle($root);

        expect($unchanged->fingerprint)->toBe($before->fingerprint)
            ->and($after->fingerprint)->not->toBe($before->fingerprint)
            ->and($after->artifacts[0]['payload'])->toBe(['amount' => 125]);
    } finally {
        $files->deleteDirectory($root);
    }
});

it('rejects accepted artifacts without explicit subject membership', function () {
    $root = createPortableRepositoryFixture();
    $files = new Filesystem;
    $artifactPath = $root.'/business/profiles/external/examples/artifacts/fact.yaml';
    $files->put($artifactPath, str_replace('subject: { identifier: EXTERNAL-001, type: ExternalCase }'.PHP_EOL, '', $files->get($artifactPath)));

    try {
        $manifest = (new DiscoverRepository($files, new CanonicalRepositoryFingerprint($files)))->handle($root);

        expect($manifest->hasErrors())->toBeTrue()
            ->and(collect($manifest->findings)->pluck('code'))->toContain('artifact.invalid');
    } finally {
        $files->deleteDirectory($root);
    }
});
