<?php

namespace App\Integration\XDocument;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

final readonly class ValidateXDocumentCompilationRequest
{
    public function __construct(private Validator $validator = new Validator(max_errors: 20, stop_at_first_error: false)) {}

    public function handle(XDocumentCompilationRequest $request): void
    {
        $schemaPath = dirname(__DIR__, 3).'/resources/gne/contracts/x-document/1.0/compilation-request.schema.json';
        $schema = json_decode(file_get_contents($schemaPath) ?: throw new InvalidXDocumentCompilationRequest('The x-document request schema is unavailable.'), false, flags: JSON_THROW_ON_ERROR);
        $payload = json_decode(json_encode($request->toArray(), JSON_THROW_ON_ERROR), false, flags: JSON_THROW_ON_ERROR);
        $result = $this->validator->validate($payload, $schema);
        if (! $result->isValid()) {
            $errors = (new ErrorFormatter)->format($result->error());
            throw new InvalidXDocumentCompilationRequest('Generated x-document request violates contract 1.0: '.json_encode($errors, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }
    }
}
