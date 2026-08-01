<?php

namespace App\Integration\XDocument;

use Composer\InstalledVersions;

final readonly class XDocumentPackageBaselineAttestor
{
    public const XDocumentCommit = '29853fae23939cba0b440db3ae04e351c499a78e';

    public const XDocumentLaravelCommit = 'b299d5bfbe7bdf93ecaf840431349804b676a6c7';

    public function __construct(private ResolveInstalledPackageGitHead $resolveGitHead) {}

    /** @return array<string, InstalledPackageBaseline> */
    public function attestAll(): array
    {
        return [
            'x_document' => $this->attest('3neti/x-document', self::XDocumentCommit),
            'x_document_laravel' => $this->attest('3neti/x-document-laravel', self::XDocumentLaravelCommit),
        ];
    }

    public function attest(string $package, string $expectedCommit): InstalledPackageBaseline
    {
        $installed = InstalledVersions::isInstalled($package);
        $installPath = $installed ? InstalledVersions::getInstallPath($package) : null;
        $sourcePath = is_string($installPath) ? realpath($installPath) : false;
        $actualCommit = is_string($sourcePath) ? $this->resolveGitHead->handle($sourcePath) : null;

        return new InstalledPackageBaseline(
            package: $package,
            expectedCommit: $expectedCommit,
            actualCommit: $actualCommit,
            sourcePath: is_string($sourcePath) ? $sourcePath : null,
            version: $installed ? InstalledVersions::getPrettyVersion($package) : null,
            installed: $installed,
            gitAvailable: $actualCommit !== null,
        );
    }

    /** @return array<string, InstalledPackageBaseline> */
    public function assertMatches(): array
    {
        $baselines = $this->attestAll();

        foreach ($baselines as $baseline) {
            if (! $baseline->matches()) {
                throw new PackageBaselineMismatch("{$baseline->package} does not match reviewed commit {$baseline->expectedCommit}.");
            }
        }

        return $baselines;
    }
}
