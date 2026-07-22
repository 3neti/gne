<?php

namespace App\Domain\Compilation;

interface DocumentProjectionDriver
{
    public function project(ResolvedDocument $document): DocumentProjection;
}
