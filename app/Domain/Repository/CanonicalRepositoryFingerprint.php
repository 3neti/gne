<?php

namespace App\Domain\Repository;

use Illuminate\Filesystem\Filesystem;

final readonly class CanonicalRepositoryFingerprint
{
    public function __construct(private Filesystem $files) {}

    /** @return array{fingerprint: string, files: list<string>} */
    public function build(RepositoryAddress $repository, RepositoryPath $businessPath): array
    {
        $canonicalFiles = ['gne.yaml'];

        if ($this->files->exists($repository->absolute('GENEI.md'))) {
            $canonicalFiles[] = 'GENEI.md';
        }

        foreach ($this->files->allFiles($repository->absolute($businessPath)) as $file) {
            if ($file->getFilename() !== '.gitkeep') {
                $canonicalFiles[] = $repository->relative($file->getPathname());
            }
        }

        sort($canonicalFiles, SORT_STRING);
        $hash = hash_init('sha256');

        foreach ($canonicalFiles as $relativePath) {
            hash_update($hash, $relativePath."\0");
            hash_update_file($hash, $repository->absolute($relativePath));
            hash_update($hash, "\0");
        }

        return ['fingerprint' => hash_final($hash), 'files' => $canonicalFiles];
    }
}
