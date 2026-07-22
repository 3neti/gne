<?php

use App\Domain\Artifacts\ArtifactRevision;
use App\Domain\Artifacts\BusinessIdentifier;
use App\Domain\Repository\RepositoryPath;
use App\Domain\Repository\ValidationFinding;
use App\Domain\Repository\ValidationSeverity;

it('accepts safe repository-relative paths', function () {
    $path = new RepositoryPath('business/profiles');
    expect((string) $path)->toBe('business/profiles');
});

it('rejects unsafe paths', function (string $path) {
    expect(fn () => new RepositoryPath($path))->toThrow(InvalidArgumentException::class);
})->with(['../business', '/tmp/business', 'C:/business', '']);

it('keeps business identity independent from database keys', function () {
    expect((string) new BusinessIdentifier('LOT-037'))->toBe('LOT-037');
});

it('requires valid artifact revisions', function () {
    expect((new ArtifactRevision(1))->value)->toBe(1)
        ->and(fn () => new ArtifactRevision(0))->toThrow(InvalidArgumentException::class);
});

it('serializes structured validation findings', function () {
    $finding = new ValidationFinding(ValidationSeverity::Error, 'path.unsafe', 'Unsafe path', 'gne.yaml', 'line 4', 'Use a relative path.');
    expect($finding->toArray())->toMatchArray(['severity' => 'error', 'code' => 'path.unsafe', 'source_path' => 'gne.yaml']);
});
