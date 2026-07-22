<?php

use App\Domain\Repository\CanonicalRepositoryFingerprint;
use App\Domain\Repository\DiscoverRepository;
use App\Domain\Repository\RepositorySourceLoader;
use App\Domain\Repository\ValidateArtifactPayloads;
use Illuminate\Filesystem\Filesystem;

it('does not convert an unexpected source-loader failure into a validation finding', function () {
    $files = new Filesystem;
    $manifest = (new DiscoverRepository($files, new CanonicalRepositoryFingerprint($files)))->handle(dirname(__DIR__, 2));
    $loader = new class($files) extends RepositorySourceLoader
    {
        public function jsonObject(string $path): object
        {
            throw new LogicException('simulated implementation defect');
        }
    };

    (new ValidateArtifactPayloads($loader))->handle(dirname(__DIR__, 2), $manifest);
})->throws(LogicException::class, 'simulated implementation defect');
