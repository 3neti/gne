<?php

use App\Integration\XDocument\PackageBaselineMismatch;
use App\Integration\XDocument\ResolveInstalledPackageGitHead;
use App\Integration\XDocument\XDocumentPackageBaselineAttestor;

it('fails baseline smoke attestation when installed source does not match', function () {
    $gitHead = new class extends ResolveInstalledPackageGitHead
    {
        public function handle(string $sourcePath): ?string
        {
            return str_repeat('0', 40);
        }
    };

    (new XDocumentPackageBaselineAttestor($gitHead))->assertMatches();
})->throws(PackageBaselineMismatch::class);

it('reports a mismatched expected commit without normalizing it into compatibility', function () {
    $attestation = app(XDocumentPackageBaselineAttestor::class)->attest(
        '3neti/x-document',
        str_repeat('0', 40),
    );

    expect($attestation->installed)->toBeTrue()
        ->and($attestation->gitAvailable)->toBeTrue()
        ->and($attestation->matches())->toBeFalse()
        ->and($attestation->actualCommit)->toBe(XDocumentPackageBaselineAttestor::XDocumentCommit);
});
