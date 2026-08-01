<?php

use LBHurtado\XDocument\Contract\ValidateDocumentCompilationRequest;
use LBHurtado\XDocument\Exceptions\InvalidDocumentContract;

it('loads the real x-document contract validator', function () {
    expect(class_exists(ValidateDocumentCompilationRequest::class))->toBeTrue();
});

it('allows unexpected external contract defects to propagate', function () {
    (new ValidateDocumentCompilationRequest)->handleJson('{malformed');
})->throws(InvalidDocumentContract::class);
