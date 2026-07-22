<?php

namespace App\Domain\Compilation;

interface DocumentProjectionDriver
{
    public function compile(DocumentCompilationRequest $request): DocumentCompilationResult;
}
